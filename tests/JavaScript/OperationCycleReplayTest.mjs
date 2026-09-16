import test from 'node:test';
import assert from 'node:assert/strict';
import {
    buildCycleReplay,
    renderCycleReplay,
} from '../../resources/js/shared/operation-cycle-replay.js';

test('presenta una reproducción coincidente sin conservar identificadores internos', () => {
    const model = buildCycleReplay({
        disponible: true,
        estado: 'coincide',
        detalle: 'El motor reprodujo todas las decisiones.',
        referencia: 'actual',
        ciclo: {
            id: 'uuid-ciclo',
            generado_at: '2026-09-16T10:00:00Z',
            reglas: 'arbitraje_global_v4_explicabilidad',
            capacidad_ejecucion: 3,
            frontera_max: 4,
        },
        resumen: { decisiones: 1, coinciden: 1, difieren: 0, insuficientes: 0 },
        verificaciones: [{
            estado: 'coincide',
            maniobra_id: 'uuid-maniobra',
            maniobra: {
                titulo: 'Evacuar pallet prioritario',
                objetivo: 'Evacuación de Cámara Norte',
                folio: 'PAL-058321',
                ruta: 'Cámara Norte → Andén 2',
            },
            persistido: {
                orden: 1,
                decision: 'seleccionada',
                peso_prioridad: 40,
                peso_objetivo: 40,
                beneficio_neto: 495,
                puntaje: 40400000495,
                factor_decisivo: 'cupo_disponible',
            },
            reproducido: {
                orden: 1,
                decision: 'seleccionada',
                peso_prioridad: 40,
                peso_objetivo: 40,
                beneficio_neto: 495,
                puntaje: 40400000495,
                factor_decisivo: 'cupo_disponible',
            },
            diferencias: [],
        }],
    });

    assert.equal(model.statusLabel, 'Coincide');
    assert.equal(model.reference, 'Ciclo vigente');
    assert.equal(model.checks[0].persisted.decision, 'Seleccionada');
    assert.doesNotMatch(JSON.stringify(model), /uuid-/);

    const html = renderCycleReplay(model);
    assert.match(html, /Replay aislado/);
    assert.match(html, /REPRODUCCIÓN COINCIDENTE/);
    assert.match(html, /Evacuar pallet prioritario/);
    assert.match(html, /Volver a la comparación/);
});

test('destaca las diferencias entre el resultado persistido y el reproducido', () => {
    const model = buildCycleReplay({
        disponible: true,
        estado: 'difiere',
        referencia: 'anterior',
        resumen: { decisiones: 1, coinciden: 0, difieren: 1, insuficientes: 0 },
        verificaciones: [{
            estado: 'difiere',
            maniobra: { titulo: 'Preparar despacho' },
            persistido: { orden: 2, decision: 'alternativa', puntaje: 10 },
            reproducido: { orden: 1, decision: 'seleccionada', puntaje: 20 },
            diferencias: [
                { campo: 'orden', persistido: 2, reproducido: 1 },
                { campo: 'decision', persistido: 'alternativa', reproducido: 'seleccionada' },
            ],
        }],
    });

    assert.equal(model.reference, 'Ciclo anterior');
    assert.deepEqual(model.checks[0].differences.map((item) => item.label), ['Orden', 'Decisión']);

    const html = renderCycleReplay(model);
    assert.match(html, /DIFERENCIA ENCONTRADA/);
    assert.match(html, /Alternativa/);
    assert.match(html, /Seleccionada/);
});

test('explica la información insuficiente y escapa contenido no confiable', () => {
    const insufficient = buildCycleReplay({
        disponible: false,
        estado: 'informacion_insuficiente',
        detalle: 'El ciclo no conserva todos los insumos.',
        referencia: 'actual',
        resumen: { insuficientes: 2 },
        verificaciones: [],
    });
    assert.match(renderCycleReplay(insufficient), /Información insuficiente/);

    const unsafe = buildCycleReplay({
        disponible: true,
        estado: 'difiere',
        resumen: { decisiones: 1, difieren: 1 },
        verificaciones: [{
            estado: 'difiere',
            maniobra: { titulo: '<img src=x onerror=alert(1)>' },
            persistido: {},
            reproducido: {},
            diferencias: [],
        }],
    });
    const html = renderCycleReplay(unsafe);

    assert.doesNotMatch(html, /<img/);
    assert.match(html, /&lt;img/);
});
