export const PLANT_SIZE = 10_000;
export const MIN_ELEMENT_SIZE = 300;
export const GRID_SIZE = 100;

export function clamp(value, minimum, maximum) {
    return Math.max(minimum, Math.min(maximum, Number(value) || 0));
}

export function snap(value, grid = GRID_SIZE) {
    return Math.round((Number(value) || 0) / grid) * grid;
}

export function moveElement(element, deltaX, deltaY) {
    return {
        ...element,
        x: snap(clamp(Number(element.x) + deltaX, 0, PLANT_SIZE - Number(element.ancho))),
        y: snap(clamp(Number(element.y) + deltaY, 0, PLANT_SIZE - Number(element.alto))),
    };
}

export function resizeElement(element, deltaX, deltaY) {
    return {
        ...element,
        ancho: snap(clamp(Number(element.ancho) + deltaX, MIN_ELEMENT_SIZE, PLANT_SIZE - Number(element.x))),
        alto: snap(clamp(Number(element.alto) + deltaY, MIN_ELEMENT_SIZE, PLANT_SIZE - Number(element.y))),
    };
}

export function autoLayout(catalog = []) {
    const groups = ['camara', 'tunel', 'anden', 'almacen'];
    const items = groups.flatMap((type) => catalog.filter((item) => item.tipo === type));
    if (!items.length) return [];

    const columns = Math.min(5, Math.max(2, Math.ceil(Math.sqrt(items.length * 1.7))));
    const rows = Math.ceil(items.length / columns);
    const gap = 180;
    const width = Math.floor((PLANT_SIZE - gap * (columns + 1)) / columns);
    const height = Math.min(1700, Math.floor((PLANT_SIZE - gap * (rows + 1)) / rows));

    return items.map((item, index) => ({
        id: globalThis.crypto?.randomUUID?.() || `auto-${item.tipo}-${item.id}`,
        tipo: item.tipo,
        referencia_id: item.id,
        nombre: item.nombre || item.codigo,
        categoria: null,
        x: gap + (index % columns) * (width + gap),
        y: gap + Math.floor(index / columns) * (height + gap),
        ancho: width,
        alto: height,
        rotacion: 0,
    }));
}

export function catalogKey(type, id) {
    return `${type}:${id}`;
}

export function availableCatalog(catalog = [], elements = []) {
    const placed = new Set(elements
        .filter((element) => element.tipo !== 'zona')
        .map((element) => catalogKey(element.tipo, element.referencia_id)));
    return catalog.filter((item) => !placed.has(catalogKey(item.tipo, item.id)));
}

export function reconcilePlantSnapshot(incoming, current, requestRevision, currentRevision) {
    if (requestRevision === currentRevision || !current?.planta) return incoming;

    return {
        ...incoming,
        planta: current.planta,
    };
}
