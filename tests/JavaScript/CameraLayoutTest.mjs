import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import {
    bandNumberingLabel,
    orderBandsForCamera,
} from '../../resources/js/shared/camera-layout.js';

const root = new URL('../../', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');

test('ordena las bandas de izquierda a derecha por defecto', () => {
    assert.deepEqual(
        orderBandsForCamera({}, [3, 1, 2]),
        [1, 2, 3],
    );
    assert.equal(bandNumberingLabel({}), 'B01 a la izquierda');
});

test('invierte solo la representación visual de una cámara numerada desde la derecha', () => {
    const camera = { sentido_numeracion_bandas: 'derecha_a_izquierda' };

    assert.deepEqual(orderBandsForCamera(camera, [1, 3, 2]), [3, 2, 1]);
    assert.equal(bandNumberingLabel(camera), 'B01 a la derecha');
});

test('ordena objetos de banda sin alterar su identidad', () => {
    const bands = [{ numero: 2 }, { numero: 1 }, { numero: 3 }];
    const ordered = orderBandsForCamera(
        { sentido_numeracion_bandas: 'derecha_a_izquierda' },
        bands,
        (band) => band.numero,
    );

    assert.deepEqual(ordered.map((band) => band.numero), [3, 2, 1]);
    assert.deepEqual(bands.map((band) => band.numero), [2, 1, 3]);
});

test('las vistas web y tablet consumen el sentido configurado por cámara', () => {
    const webOperation = read('resources/js/app.js');
    const webConfiguration = read('resources/js/office-cameras.js');
    const mobileMap = read('mobile/src/components/PositionMap.tsx');
    const mobileDestination = read('mobile/src/components/OperationModals.tsx');

    assert.ok((webOperation.match(/orderBandsForCamera/g) ?? []).length >= 3);
    assert.ok((webConfiguration.match(/orderBandsForCamera/g) ?? []).length >= 3);
    assert.match(mobileMap, /orderBandsForCamera/);
    assert.match(mobileDestination, /orderBandsForCamera/);
});
