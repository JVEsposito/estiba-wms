import test from 'node:test';
import assert from 'node:assert/strict';
import {
    buildManeuverInterventionRequest,
    buildManeuverSupervisionModel,
    operationLocationLabel,
} from '../../resources/js/shared/operation-supervision-drawer.js';

test('presenta nombres operacionales y posición sin exponer el identificador interno', () => {
    const label = operationLocationLabel({
        id: 'uuid-camara',
        codigo: 'CAM-09',
        nombre: 'Cámara de tránsito 07',
        posicion: {
            id: 'uuid-posicion',
            etiqueta: 'B02-P04-N1',
        },
    });

    assert.equal(label, 'Cámara de tránsito 07 · B02-P04-N1');
    assert.doesNotMatch(label, /uuid|CAM-09/);
});

test('normaliza el detalle completo de supervisión sin publicar UUID de conflictos', () => {
    const model = buildManeuverSupervisionModel({
        maniobra_id: 'uuid-maniobra',
        titulo: 'Mover pallet a despacho',
        decision: 'excluida_conflicto',
        estado: 'pendiente',
        prioridad: 'alta',
        motivo: 'Comparte una posición con otra labor prioritaria.',
        puntaje: 1200,
        beneficio_neto: 450,
        costo_movimientos: 2,
        riesgo_operacional: 30,
        objetivo: { tipo: 'concentracion_carga', titulo: 'Concentrar carga CAR-900' },
        progreso: { pasos_completados: 0, pasos_total: 2, porcentaje: 0 },
        responsable: { id: 'uuid-usuario', nombre: 'María Supervisora' },
        dispositivo: { id: 'uuid-tablet', codigo: 'TAB-03', nombre: 'Tablet cámara norte' },
        conflictos: [
            { recurso: 'posicion:uuid-posicion', maniobra_id: 'uuid-otra' },
            { recurso: 'posicion:uuid-posicion', maniobra_id: 'uuid-otra' },
        ],
        paso_actual: {
            id: 'uuid-paso-1',
            secuencia: 1,
            estado: 'pendiente',
            instruccion: 'Retirar pallet',
            folio: { id: 'uuid-folio', numero_folio: 'PAL-058321' },
            origen: { nombre: 'Cámara de tránsito 07', posicion: { etiqueta: 'B02-P04-N1' } },
            destino: { nombre: 'Andén de despacho 2' },
        },
        pasos: [
            {
                id: 'uuid-paso-1',
                secuencia: 1,
                estado: 'pendiente',
                instruccion: 'Retirar pallet',
                folio: { id: 'uuid-folio', numero_folio: 'PAL-058321' },
                origen: { nombre: 'Cámara de tránsito 07', posicion: { etiqueta: 'B02-P04-N1' } },
                destino: { nombre: 'Andén de despacho 2' },
            },
            {
                id: 'uuid-paso-2',
                secuencia: 2,
                estado: 'bloqueada',
                instruccion: 'Confirmar entrega',
                folio: { numero_folio: 'PAL-058321' },
                origen: { nombre: 'Andén de despacho 2' },
                destino: null,
            },
        ],
    });

    assert.equal(model.decision, 'Excluida por conflicto');
    assert.equal(model.currentStep.route, 'Cámara de tránsito 07 · B02-P04-N1 → Andén de despacho 2');
    assert.equal(model.assignment.device, 'Tablet cámara norte');
    assert.deepEqual(model.conflicts, ['Posición física compartida con otra maniobra']);
    assert.equal(model.steps.length, 2);
    assert.equal(model.steps[0].current, true);
    assert.equal(model.steps[1].status, 'Bloqueada');
    assert.doesNotMatch(JSON.stringify(model), /uuid-/);
});

test('conserva el paso actual como secuencia mínima para ciclos antiguos', () => {
    const model = buildManeuverSupervisionModel({
        titulo: 'Maniobra compatible',
        paso_actual: {
            id: 'paso',
            secuencia: 1,
            estado: 'en_proceso',
            folio: { numero_folio: 'PAL-01' },
            origen: { codigo: 'CAM-01' },
            destino: { nombre: 'Andén principal' },
        },
    });

    assert.equal(model.steps.length, 1);
    assert.equal(model.currentStep.status, 'En proceso');
    assert.equal(model.currentStep.route, 'CAM-01 → Andén principal');
});

test('construye comandos versionados solo para acciones autorizadas', () => {
    const decision = {
        maniobra_id: '5cde3d8c-478b-4be0-8480-bc62c43a5775',
        version: 7,
        acciones_autorizadas: {
            permitidas: ['pausar', 'repriorizar'],
        },
    };

    const pause = buildManeuverInterventionRequest(
        decision,
        'pausar',
        { reason: 'Esperar confirmación del andén.' },
        'f3c2239a-c35d-45bc-af84-44bb4229d477',
    );
    assert.equal(pause.method, 'POST');
    assert.match(pause.path, /\/pausar$/);
    assert.equal(pause.body.version_maniobra, 7);
    assert.equal(pause.body.motivo, 'Esperar confirmación del andén.');

    const priority = buildManeuverInterventionRequest(
        decision,
        'repriorizar',
        { reason: 'Salida adelantada.', priority: 'critica' },
        'af9631ab-1bbb-40a1-83da-bcf30abb2a48',
    );
    assert.equal(priority.method, 'PATCH');
    assert.match(priority.path, /\/prioridad$/);
    assert.equal(priority.body.prioridad, 'critica');

    assert.throws(
        () => buildManeuverInterventionRequest(
            decision,
            'reanudar',
            { reason: 'No corresponde.' },
            '5be8bd0b-0d28-4ada-b807-e745a6c0d01c',
        ),
        /ya no autoriza/,
    );
});

test('presenta la pausa supervisada y las acciones que habilitó el servidor', () => {
    const model = buildManeuverSupervisionModel({
        titulo: 'Maniobra en espera',
        estado: 'pausada_supervision',
        version: 4,
        acciones_autorizadas: {
            version_requerida: 4,
            permitidas: ['reanudar', 'repriorizar'],
            restricciones: {
                pausar: 'estado_no_pendiente',
                reanudar: null,
                repriorizar: null,
            },
        },
    });

    assert.equal(model.status, 'Pausada por supervisión');
    assert.equal(model.version, 4);
    assert.deepEqual(model.actions.allowed, ['reanudar', 'repriorizar']);
});
