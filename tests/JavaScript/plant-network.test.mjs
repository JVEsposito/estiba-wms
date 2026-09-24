import test from 'node:test';
import assert from 'node:assert/strict';
import {
    connectionProblem,
    contactPoint,
    describeConnections,
    removeElement,
    resizeElement,
    unconnectedPlaces,
} from '../../resources/js/shared/plant-layout.js';

const box = (x, y, ancho, alto) => ({ x, y, ancho, alto });

// Mismos casos que tests/Unit/Services/Operacion/ServicioRedPlantaTest.php.
const cases = [
    ['bordes verticales pegados', box(0, 0, 1000, 1000), box(1000, 200, 1000, 400), { x: 1000, y: 400 }],
    ['separación dentro de tolerancia', box(0, 0, 1000, 1000), box(1080, 0, 500, 1000), { x: 1040, y: 500 }],
    ['separación excesiva', box(0, 0, 1000, 1000), box(1200, 0, 500, 1000), null],
    ['solo tocan una esquina', box(0, 0, 1000, 1000), box(1000, 1000, 500, 500), null],
    ['borde compartido muy corto', box(0, 0, 1000, 1000), box(1000, 950, 500, 500), null],
    ['pasillo bajo la cámara', box(0, 0, 1000, 2000), box(0, 2000, 4000, 200), { x: 500, y: 2000 }],
    ['andén dentro del patio', box(100, 5000, 3000, 4000), box(300, 6000, 500, 3000), { x: 550, y: 6000 }],
];

for (const [name, a, b, expected] of cases) {
    test(`contacto: ${name}`, () => {
        assert.deepEqual(contactPoint(a, b), expected);
        assert.equal(contactPoint(b, a) === null, expected === null);
    });
}

test('un pasillo puede ser angosto pero conserva un largo mínimo', () => {
    const corridor = { tipo: 'pasillo', x: 0, y: 0, ancho: 3000, alto: 300 };
    assert.deepEqual(resizeElement(corridor, 0, -250), { ...corridor, alto: 100 });
    assert.deepEqual(resizeElement(corridor, -5000, -5000), { ...corridor, ancho: 300, alto: 100 });
    assert.equal(resizeElement({ tipo: 'camara', x: 0, y: 0, ancho: 1000, alto: 1000 }, 0, -900).alto, 300);
});

test('el editor explica por qué rechaza una conexión', () => {
    const elements = [
        { id: 'a', tipo: 'camara', nombre: 'CAM-01', ...box(0, 0, 1000, 1000) },
        { id: 'p', tipo: 'pasillo', nombre: 'Pasillo', ...box(0, 1000, 4000, 200) },
        { id: 'b', tipo: 'camara', nombre: 'CAM-02', ...box(3000, 0, 1000, 1000) },
        { id: 'm', tipo: 'zona', categoria: 'no_operativo', nombre: 'Sala', ...box(0, 1200, 1000, 1000) },
    ];
    const connections = [{ id: 'c1', desde: 'a', hacia: 'p' }];

    assert.equal(connectionProblem(elements, 'b', 'p', connections), null);
    assert.match(connectionProblem(elements, 'p', 'a', connections), /ya están conectados/);
    assert.match(connectionProblem(elements, 'a', 'b', connections), /CAM-01 y CAM-02 no comparten un borde/);
    assert.match(connectionProblem(elements, 'p', 'm', connections), /no operativas/);
    assert.deepEqual(unconnectedPlaces(elements, connections), ['b']);
});

test('mover un recinto invalida su puerta y quitarlo borra sus conexiones', () => {
    const elements = [
        { id: 'a', tipo: 'camara', nombre: 'CAM-01', ...box(0, 0, 1000, 1000) },
        { id: 'p', tipo: 'pasillo', nombre: 'Pasillo', ...box(0, 1000, 4000, 200) },
    ];
    const connections = [{ id: 'c1', desde: 'a', hacia: 'p' }];
    assert.equal(describeConnections(elements, connections)[0].valid, true);

    const moved = [{ ...elements[0], y: 3000 }, elements[1]];
    assert.equal(describeConnections(moved, connections)[0].valid, false);
    assert.deepEqual(removeElement(elements, connections, 'a'), { elementos: [elements[1]], conexiones: [] });
});
