import assert from 'node:assert/strict';
import test from 'node:test';

import {
    beginOperationalCameraSnapshot,
    formatOperationalTemperature,
    operationalPositionDescription,
    operationalPositionTone,
    operationalTemperatureAverage,
} from '../../resources/js/shared/camera-operations.js';

test('distingue una temperatura cero de una lectura ausente', () => {
    assert.equal(formatOperationalTemperature(0), '0,0 °C');
    assert.equal(formatOperationalTemperature(null), 'SIN REGISTRO');
    assert.equal(formatOperationalTemperature(undefined), 'SIN REGISTRO');
    assert.equal(formatOperationalTemperature(''), 'SIN REGISTRO');
    assert.equal(formatOperationalTemperature('   '), 'SIN REGISTRO');
    assert.equal(formatOperationalTemperature('no-numérica'), 'SIN REGISTRO');
});

test('calcula el promedio solo con las tres lecturas informadas', () => {
    assert.equal(operationalTemperatureAverage({
        inicio_c: 1,
        medio_c: 2,
        fondo_c: 3,
    }), 2);
    assert.equal(operationalTemperatureAverage({
        inicio_c: 1,
        medio_c: null,
        fondo_c: 3,
    }), null);
});

test('representa las posiciones activas con su estado operacional real', () => {
    assert.equal(operationalPositionTone({ estado: 'activa' }), 'available');
    assert.equal(operationalPositionTone({ estado: 'activa', ocupada: true }), 'occupied');
    assert.equal(operationalPositionTone({ estado: 'activa', reservada: true }), 'reserved');
    assert.equal(operationalPositionTone({ estado: 'bloqueada' }), 'disabled');
    assert.equal(operationalPositionTone({ estado: 'fuera_servicio' }), 'disabled');

    assert.equal(operationalPositionDescription({ estado: 'activa' }), 'Disponible');
    assert.equal(operationalPositionDescription({ estado: 'bloqueada' }), 'Bloqueada');
    assert.equal(operationalPositionDescription({
        estado: 'activa',
        folios: [{ numero_folio: 'FOL-000001' }],
    }), 'Folio FOL-000001');
});

test('descarta la instantánea anterior al cambiar de cámara', () => {
    const state = {
        selectedOperationalCameraId: 'camara-anterior',
        selectedOperationalPlan: { id: 'plano-anterior' },
        operationalMovements: [{ id: 'movimiento-anterior' }],
        operationalEnvironment: { estado: 'vigente' },
    };

    beginOperationalCameraSnapshot(state, 'camara-nueva');

    assert.equal(state.selectedOperationalCameraId, 'camara-nueva');
    assert.equal(state.selectedOperationalPlan, null);
    assert.deepEqual(state.operationalMovements, []);
    assert.equal(state.operationalEnvironment, null);
});
