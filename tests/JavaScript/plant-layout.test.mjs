import test from 'node:test';
import assert from 'node:assert/strict';
import {
    autoLayout,
    availableCatalog,
    moveElement,
    reconcilePlantSnapshot,
    resizeElement,
} from '../../resources/js/shared/plant-layout.js';

const element = { x: 1000, y: 1000, ancho: 2000, alto: 1500 };

test('mueve elementos sobre la grilla sin salir de la planta', () => {
    assert.deepEqual(moveElement(element, 155, -4000), {
        ...element,
        x: 1200,
        y: 0,
    });
    assert.equal(moveElement(element, 20_000, 0).x, 8000);
});

test('redimensiona elementos respetando mínimo y borde del plano', () => {
    assert.equal(resizeElement(element, -5000, -5000).ancho, 300);
    assert.equal(resizeElement(element, 20_000, 20_000).ancho, 9000);
    assert.equal(resizeElement(element, 20_000, 20_000).alto, 9000);
});

test('crea una distribución automática completa y dentro de límites', () => {
    const catalog = Array.from({ length: 11 }, (_, index) => ({
        tipo: index < 6 ? 'camara' : 'anden',
        id: `id-${index}`,
        codigo: `R-${index}`,
        nombre: `Recinto ${index}`,
    }));
    const layout = autoLayout(catalog);

    assert.equal(layout.length, catalog.length);
    assert.ok(layout.every((item) => item.x >= 0 && item.y >= 0));
    assert.ok(layout.every((item) => item.x + item.ancho <= 10_000));
    assert.ok(layout.every((item) => item.y + item.alto <= 10_000));
});

test('el catálogo disponible excluye recintos ya dibujados', () => {
    const catalog = [
        { tipo: 'camara', id: 'c1' },
        { tipo: 'anden', id: 'a1' },
    ];
    const placed = [{ tipo: 'camara', referencia_id: 'c1' }];

    assert.deepEqual(availableCatalog(catalog, placed), [{ tipo: 'anden', id: 'a1' }]);
});

test('una lectura iniciada antes del guardado no reemplaza el plano confirmado', () => {
    const current = { planta: { configurado: true, version: 2 }, generado_at: 'nuevo' };
    const stale = { planta: { configurado: false, version: 0 }, generado_at: 'anterior' };

    assert.deepEqual(reconcilePlantSnapshot(stale, current, 0, 1), {
        ...stale,
        planta: current.planta,
    });
    assert.equal(reconcilePlantSnapshot(stale, current, 1, 1), stale);
});
