export const PLANT_SIZE = 10_000;
export const MIN_ELEMENT_SIZE = 300;
export const MIN_CORRIDOR_THICKNESS = 100;
export const GRID_SIZE = 100;
// Misma regla de contacto que App\Services\Operacion\ServicioRedPlanta.
export const CONTACT_TOLERANCE = 100;
export const MIN_SHARED_EDGE = 100;
export const NO_TRANSIT_CATEGORIES = ['no_operativo'];

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
    const width = snap(clamp(Number(element.ancho) + deltaX, 0, PLANT_SIZE - Number(element.x)));
    const height = snap(clamp(Number(element.alto) + deltaY, 0, PLANT_SIZE - Number(element.y)));
    if (element.tipo !== 'pasillo') {
        return { ...element, ancho: Math.max(MIN_ELEMENT_SIZE, width), alto: Math.max(MIN_ELEMENT_SIZE, height) };
    }

    // Un pasillo puede ser angosto en un eje, pero su largo sigue siendo visible.
    const safeWidth = Math.max(MIN_CORRIDOR_THICKNESS, width);
    const safeHeight = Math.max(MIN_CORRIDOR_THICKNESS, height);
    if (Math.max(safeWidth, safeHeight) < MIN_ELEMENT_SIZE) {
        return safeWidth >= safeHeight
            ? { ...element, ancho: MIN_ELEMENT_SIZE, alto: safeHeight }
            : { ...element, ancho: safeWidth, alto: MIN_ELEMENT_SIZE };
    }
    return { ...element, ancho: safeWidth, alto: safeHeight };
}

export function contactPoint(a, b) {
    const ax2 = a.x + a.ancho;
    const ay2 = a.y + a.alto;
    const bx2 = b.x + b.ancho;
    const by2 = b.y + b.alto;
    const overlapX = Math.min(ax2, bx2) - Math.max(a.x, b.x);
    const overlapY = Math.min(ay2, by2) - Math.max(a.y, b.y);
    const gapX = Math.max(0, -overlapX);
    const gapY = Math.max(0, -overlapY);
    const middleY = Math.round((Math.max(a.y, b.y) + Math.min(ay2, by2)) / 2);
    const middleX = Math.round((Math.max(a.x, b.x) + Math.min(ax2, bx2)) / 2);

    // Elementos superpuestos (un andén dentro del patio): la puerta va en el
    // borde superior de la intersección para no tapar el contenido.
    if (overlapX > 0 && overlapY > 0) return { x: middleX, y: Math.max(a.y, b.y) };
    if (gapX <= CONTACT_TOLERANCE && overlapY >= MIN_SHARED_EDGE) {
        return { x: Math.round(a.x < b.x ? (ax2 + b.x) / 2 : (bx2 + a.x) / 2), y: middleY };
    }
    if (gapY <= CONTACT_TOLERANCE && overlapX >= MIN_SHARED_EDGE) {
        return { x: middleX, y: Math.round(a.y < b.y ? (ay2 + b.y) / 2 : (by2 + a.y) / 2) };
    }
    return null;
}

export function allowsTransit(element) {
    return !(element?.tipo === 'zona' && NO_TRANSIT_CATEGORIES.includes(element.categoria));
}

export function connectionProblem(elements, from, to, connections = []) {
    const a = elements.find((element) => element.id === from);
    const b = elements.find((element) => element.id === to);
    if (!a || !b) return 'La conexión referencia un elemento que ya no está en el plano.';
    if (a.id === b.id) return 'Un elemento no puede conectarse consigo mismo.';
    if (!allowsTransit(a) || !allowsTransit(b)) return 'Las áreas no operativas no admiten conexiones de tránsito.';
    const duplicate = connections.some((connection) => (connection.desde === from && connection.hacia === to)
        || (connection.desde === to && connection.hacia === from));
    if (duplicate) return 'Los mismos elementos ya están conectados.';
    if (!contactPoint(a, b)) return `${a.nombre} y ${b.nombre} no comparten un borde; acércalos o conecta ambos a un pasillo.`;
    return null;
}

export function describeConnections(elements = [], connections = []) {
    const byId = new Map(elements.map((element) => [element.id, element]));
    return connections.map((connection) => {
        const from = byId.get(connection.desde);
        const to = byId.get(connection.hacia);
        const point = from && to ? contactPoint(from, to) : null;
        return { ...connection, point, valid: Boolean(point && allowsTransit(from) && allowsTransit(to)) };
    });
}

export function unconnectedPlaces(elements = [], connections = []) {
    const linked = new Set(connections.flatMap((connection) => [connection.desde, connection.hacia]));
    return elements
        .filter((element) => !['zona', 'pasillo'].includes(element.tipo))
        .filter((element) => !linked.has(element.id))
        .map((element) => element.id);
}

export function removeElement(elements = [], connections = [], id) {
    return {
        elementos: elements.filter((element) => element.id !== id),
        conexiones: connections.filter((connection) => connection.desde !== id && connection.hacia !== id),
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
