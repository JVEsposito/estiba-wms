import test from 'node:test';
import assert from 'node:assert/strict';
import {
    buildCycleComparison,
    renderCycleComparison,
} from '../../resources/js/shared/operation-cycle-comparison.js';

test('presenta diferencias operacionales sin conservar identificadores internos', () => {
    const model = buildCycleComparison({
        disponible: true,
        actual: {
            id: 'uuid-ciclo-actual',
            generado_at: '2026-09-15T12:05:00Z',
            capacidad_ejecucion: 3,
            frontera_max: 4,
        },
        anterior: {
            id: 'uuid-ciclo-anterior',
            generado_at: '2026-09-15T12:00:00Z',
            capacidad_ejecucion: 3,
            frontera_max: 4,
        },
        resumen: {
            incorporadas: 1,
            retiradas: 0,
            modificadas: 1,
            sin_cambios: 2,
            total_cambios: 2,
        },
        cambios: [{
            tipo: 'modificada',
            maniobra_id: 'uuid-maniobra',
            maniobra: {
                titulo: 'Despachar pallet prioritario',
                objetivo: 'Concentrar carga CAR-900',
                folio: 'PAL-058321',
                ruta: 'Cámara de tránsito 07 · B02-P04-N1 → Andén 2',
            },
            anterior: {
                orden: 3,
                decision: 'alternativa',
                prioridad: 'alta',
                puntaje: 100,
                beneficio_neto: 20,
                factor_decisivo: 'Alternativa sin reserva',
            },
            actual: {
                orden: 1,
                decision: 'seleccionada',
                prioridad: 'urgente',
                puntaje: 150,
                beneficio_neto: 40,
                factor_decisivo: 'Cupo de ejecución disponible',
            },
            diferencias: [
                { campo: 'decision', anterior: 'alternativa', actual: 'seleccionada' },
                { campo: 'orden', anterior: 3, actual: 1 },
            ],
            razon_anterior: 'Esperaba un cupo de ejecución.',
            razon_actual: 'Subió de prioridad y obtuvo un cupo.',
        }],
        detalle: 'Se comparó el ciclo vigente con su antecedente confirmado inmediato.',
        truncada: false,
        limite: 100,
    });

    assert.equal(model.available, true);
    assert.equal(model.summary.modified, '1');
    assert.equal(model.changes[0].label, 'Cambió su decisión');
    assert.equal(model.changes[0].before.decision, 'Alternativa');
    assert.equal(model.changes[0].after.decision, 'Seleccionada');
    assert.deepEqual(model.changes[0].differences.map((difference) => difference.label), [
        'Decisión',
        'Orden',
    ]);
    assert.equal(model.changes[0].route, 'Cámara de tránsito 07 · B02-P04-N1 → Andén 2');
    assert.doesNotMatch(JSON.stringify(model), /uuid-/);

    const html = renderCycleComparison(model);
    assert.match(html, /Despachar pallet prioritario/);
    assert.match(html, /Subió de prioridad y obtuvo un cupo/);
    assert.match(html, /Ciclo anterior/);
    assert.match(html, /Ciclo vigente/);
});

test('explica cuando todavía no existe un ciclo anterior', () => {
    const model = buildCycleComparison({
        disponible: false,
        motivo: 'sin_ciclo_anterior',
        detalle: 'Este es el primer ciclo confirmado de la temporada.',
        resumen: {},
        cambios: [],
    });

    assert.equal(model.available, false);
    assert.equal(model.reason, 'sin_ciclo_anterior');
    assert.match(renderCycleComparison(model), /primer ciclo confirmado/);
});

test('escapa títulos y razones antes de construir el detalle visual', () => {
    const model = buildCycleComparison({
        disponible: true,
        actual: {},
        anterior: {},
        resumen: { incorporadas: 1, total_cambios: 1 },
        cambios: [{
            tipo: 'incorporada',
            maniobra: { titulo: '<img src=x onerror=alert(1)>' },
            actual: { orden: 1, decision: 'seleccionada' },
            razon_actual: '<script>alert(1)</script>',
        }],
    });
    const html = renderCycleComparison(model);

    assert.doesNotMatch(html, /<script>|<img/);
    assert.match(html, /&lt;script&gt;/);
    assert.match(html, /&lt;img/);
});
