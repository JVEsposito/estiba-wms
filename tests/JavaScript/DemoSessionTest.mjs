import assert from 'node:assert/strict';
import test from 'node:test';

import {
    DEMO_DATA_KEY,
    DEMO_SESSION_KEY,
    advanceDemoScenario,
    disableDemoSession,
    enableDemoSession,
    readDemoSession,
} from '../../resources/js/demo/demo-session.js';

class MemoryStorage {
    constructor() {
        this.values = new Map();
    }

    getItem(key) {
        return this.values.get(key) ?? null;
    }

    setItem(key, value) {
        this.values.set(key, String(value));
    }

    removeItem(key) {
        this.values.delete(key);
    }
}

const administrator = {
    id: 7,
    nombre: 'Administradora Demo',
    rol: 'administrador',
    puede_habilitar_demo: true,
};
const authorization = {
    autorizado: true,
    version_escenario: 1,
    administrador: { id: administrator.id },
};
const firstCut = () => new Date('2026-11-05T13:30:00.000Z');

test('habilita el escenario únicamente en el almacenamiento de la sesión indicada', () => {
    const sessionStorage = new MemoryStorage();
    const productionStorage = new MemoryStorage();
    productionStorage.setItem('temporada_activa', '2026-2027');

    const result = enableDemoSession(sessionStorage, administrator, authorization, firstCut);

    assert.equal(result.dataset.meta.seasonCode, 'DEMO-26/27');
    assert.equal(result.dataset.rawMaterial.hydrocoolers[0].pumpsWorking, 2);
    assert.ok(sessionStorage.getItem(DEMO_SESSION_KEY));
    assert.ok(sessionStorage.getItem(DEMO_DATA_KEY));
    assert.equal(productionStorage.getItem('temporada_activa'), '2026-2027');
    assert.equal(productionStorage.getItem(DEMO_SESSION_KEY), null);
});

test('rechaza la habilitación para cualquier rol distinto de administrador', () => {
    const storage = new MemoryStorage();

    assert.throws(
        () => enableDemoSession(storage, { ...administrator, rol: 'consulta' }, authorization, firstCut),
        /Solo un administrador/,
    );
    assert.equal(storage.getItem(DEMO_SESSION_KEY), null);
});

test('rechaza una autorización emitida para otro administrador o versión', () => {
    const storage = new MemoryStorage();

    assert.throws(
        () => enableDemoSession(storage, administrator, {
            ...authorization,
            administrador: { id: 99 },
        }, firstCut),
        /autorización.*no es válida/,
    );
    assert.equal(storage.getItem(DEMO_SESSION_KEY), null);
});

test('mantiene cada pestaña aislada al simular un nuevo corte', () => {
    const firstTab = new MemoryStorage();
    const secondTab = new MemoryStorage();
    enableDemoSession(firstTab, administrator, authorization, firstCut);
    enableDemoSession(secondTab, administrator, authorization, firstCut);

    const advanced = advanceDemoScenario(firstTab, () => new Date('2026-11-05T14:00:00.000Z'));
    const unchanged = readDemoSession(secondTab, administrator.id);

    assert.equal(advanced.meta.cut, 2);
    assert.equal(advanced.rawMaterial.receivedToday, 13);
    assert.equal(unchanged.dataset.meta.cut, 1);
    assert.equal(unchanged.dataset.rawMaterial.receivedToday, 12);
});

test('no permite reutilizar la sesión con otro administrador y la elimina al salir', () => {
    const storage = new MemoryStorage();
    enableDemoSession(storage, administrator, authorization, firstCut);

    assert.ok(readDemoSession(storage, administrator.id));
    assert.equal(readDemoSession(storage, 99), null);

    disableDemoSession(storage);
    assert.equal(storage.getItem(DEMO_SESSION_KEY), null);
    assert.equal(storage.getItem(DEMO_DATA_KEY), null);
});
