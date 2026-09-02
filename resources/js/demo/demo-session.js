export const DEMO_SCENARIO_VERSION = 1;
export const DEMO_SESSION_KEY = 'estiba_wms_demo_session_v1';
export const DEMO_DATA_KEY = 'estiba_wms_demo_data_v1';

function clone(value) {
    return typeof structuredClone === 'function'
        ? structuredClone(value)
        : JSON.parse(JSON.stringify(value));
}

function isoAt(base, minutes) {
    return new Date(base.getTime() + minutes * 60000).toISOString();
}

function percent(occupied, total) {
    return Math.round((occupied / total) * 1000) / 10;
}

export function createDemoDataset(clock = () => new Date()) {
    const now = clock();
    const cameras = [
        { id: 'cam-demo-01', name: 'Cámara Producto 1', content: 'Producto terminado', occupied: 78, total: 96, temperature: -0.6 },
        { id: 'cam-demo-02', name: 'Cámara Producto 2', content: 'Producto terminado', occupied: 84, total: 104, temperature: -0.4 },
        { id: 'cam-demo-03', name: 'Cámara Materia Prima', content: 'Materia prima', occupied: 51, total: 72, temperature: 1.2 },
        { id: 'cam-demo-04', name: 'Cámara Materiales', content: 'Materiales', occupied: 37, total: 64, temperature: null },
        { id: 'cam-demo-05', name: 'Cámara USDA', content: 'Producto terminado', occupied: 65, total: 104, temperature: -0.5 },
    ].map((camera) => ({ ...camera, occupancy: percent(camera.occupied, camera.total) }));
    const totalPositions = cameras.reduce((total, camera) => total + camera.total, 0);
    const occupiedPositions = cameras.reduce((total, camera) => total + camera.occupied, 0);

    return {
        meta: {
            scenarioVersion: DEMO_SCENARIO_VERSION,
            scenarioName: 'Temporada frutícola Demo 2026-27',
            seasonCode: 'DEMO-26/27',
            generatedAt: now.toISOString(),
            cut: 1,
        },
        summary: {
            totalPositions,
            occupiedPositions,
            occupancy: percent(occupiedPositions, totalPositions),
            activeFolios: 286,
            fullPallets: 242,
            balances: 44,
            foliosWithoutLocation: 9,
            activeLoads: 4,
            pendingPrecooling: 31,
            netKilogramsReceived: 164520,
            materialItems: 68,
            unreadAlerts: 3,
        },
        alerts: [
            { id: 'alert-demo-01', level: 'critical', title: 'Carga próxima a ventana de salida', detail: 'CAR-DEMO-004 · faltan 2 pallets · Andén Norte', area: 'Cargas' },
            { id: 'alert-demo-02', level: 'warning', title: 'Túnel sobre tiempo objetivo', detail: 'Túnel 4 · 8 h 36 min · objetivo 8 h', area: 'Prefrío' },
            { id: 'alert-demo-03', level: 'info', title: 'Diferencia de envases pendiente', detail: 'REC-DEMO-0108 · 2 bins por revisar', area: 'Materia Prima' },
        ],
        rawMaterial: {
            receivedToday: 12,
            pendingValidation: 3,
            hydrocoolerLots: 4,
            availableForProcess: 17,
            receptions: [
                { number: 'REC-DEMO-0112', time: isoAt(now, -18), client: 'Agrícola Valle Sur', guide: 'GD-84512', containers: '66 bins', kilograms: 13860, status: 'En validación' },
                { number: 'REC-DEMO-0111', time: isoAt(now, -52), client: 'Frutícola Andes', guide: 'GD-33084', containers: '72 bins', kilograms: 15120, status: 'Hidrocooler' },
                { number: 'REC-DEMO-0110', time: isoAt(now, -96), client: 'Exportadora Cordillera', guide: 'GD-77501', containers: '60 bins', kilograms: 12600, status: 'Lotizada' },
                { number: 'REC-DEMO-0109', time: isoAt(now, -142), client: 'Agrícola Valle Sur', guide: 'GD-84503', containers: '68 bins', kilograms: 14280, status: 'Disponible' },
            ],
            hydrocoolers: [
                { name: 'Hidrocooler 1', lot: 'LOT-DEMO-241', variety: 'Santina', containers: 36, processed: 24, pumpsWorking: 2, pumpsTotal: 2, waterTemperature: 0.2, status: 'En proceso' },
                { name: 'Hidrocooler 2', lot: 'LOT-DEMO-242', variety: 'Lapins', containers: 30, processed: 18, pumpsWorking: 2, pumpsTotal: 2, waterTemperature: 0.1, status: 'En proceso' },
            ],
            lots: [
                { number: 'LOT-DEMO-238', client: 'Frutícola Andes', variety: 'Santina', containers: 42, condition: 'Hidrocooler aprobado', destination: 'Proceso' },
                { number: 'LOT-DEMO-239', client: 'Agrícola Valle Sur', variety: 'Royal Dawn', containers: 38, condition: 'Hidrocooler aprobado', destination: 'Cámara Materia Prima' },
                { number: 'LOT-DEMO-240', client: 'Exportadora Cordillera', variety: 'Lapins', containers: 44, condition: 'Pendiente de proceso', destination: 'Packing' },
            ],
        },
        refrigerated: {
            tunnels: 6,
            activeProcesses: 5,
            averageCycleHours: 7.9,
            cameras,
            precooling: [
                { tunnel: 'Túnel 1', process: 'PREF-DEMO-081', folios: 20, packages: 3680, elapsed: '6 h 42 min', pulp: 0.8, target: -0.5, status: 'En proceso' },
                { tunnel: 'Túnel 2', process: 'PREF-DEMO-082', folios: 18, packages: 3312, elapsed: '5 h 18 min', pulp: 1.4, target: -0.5, status: 'En proceso' },
                { tunnel: 'Túnel 3', process: 'PREF-DEMO-083', folios: 22, packages: 4048, elapsed: '7 h 51 min', pulp: 0.1, target: -0.5, status: 'Verificación' },
                { tunnel: 'Túnel 4', process: 'PREF-DEMO-079', folios: 21, packages: 3864, elapsed: '8 h 36 min', pulp: 0.4, target: -0.5, status: 'Atención' },
                { tunnel: 'Túnel 5', process: 'PREF-DEMO-084', folios: 16, packages: 2944, elapsed: '3 h 25 min', pulp: 2.8, target: -0.5, status: 'En proceso' },
            ],
            loads: [
                { code: 'CAR-DEMO-001', client: 'Exportadora Cordillera', destination: 'Long Beach', folios: 22, ready: 22, window: 'Hoy · 18:00', status: 'Lista' },
                { code: 'CAR-DEMO-002', client: 'Frutícola Andes', destination: 'Shanghái', folios: 24, ready: 19, window: 'Hoy · 21:30', status: 'Preparación' },
                { code: 'CAR-DEMO-003', client: 'Agrícola Valle Sur', destination: 'Rotterdam', folios: 20, ready: 14, window: 'Mañana · 07:00', status: 'Preparación' },
                { code: 'CAR-DEMO-004', client: 'Exportadora Cordillera', destination: 'Los Ángeles', folios: 18, ready: 16, window: 'Hoy · 16:30', status: 'Urgente' },
            ],
        },
        materials: {
            activeItems: 68,
            stockUnits: 184320,
            reservedUnits: 42600,
            openDispatches: 5,
            items: [
                { code: 'MAT-DEMO-001', client: 'Agrícola Valle Sur', name: 'Caja exportación 5 kg', unit: 'unidades', stock: 48200, reserved: 12400, available: 35800, warehouse: 'Bodega Central' },
                { code: 'MAT-DEMO-002', client: 'Frutícola Andes', name: 'Bolsa atmósfera modificada', unit: 'unidades', stock: 35600, reserved: 8100, available: 27500, warehouse: 'Bodega Central' },
                { code: 'MAT-DEMO-003', client: 'Exportadora Cordillera', name: 'Etiqueta trazabilidad PT', unit: 'rollos', stock: 680, reserved: 120, available: 560, warehouse: 'Bodega Insumos' },
                { code: 'MAT-DEMO-004', client: 'Agrícola Valle Sur', name: 'Esquinero pallet', unit: 'unidades', stock: 16200, reserved: 4800, available: 11400, warehouse: 'Cámara Materiales' },
                { code: 'MAT-DEMO-005', client: 'Frutícola Andes', name: 'Pallet exportación', unit: 'unidades', stock: 1240, reserved: 360, available: 880, warehouse: 'Patio Techado' },
            ],
            dispatches: [
                { code: 'DES-DEMO-041', destination: 'Packing Línea 1', items: 4, units: 8200, status: 'Preparando' },
                { code: 'DES-DEMO-040', destination: 'Packing Línea 2', items: 3, units: 6750, status: 'Confirmado' },
                { code: 'DES-DEMO-039', destination: 'Mantención', items: 2, units: 48, status: 'Entregado' },
            ],
        },
        traceability: [
            {
                key: 'DEMO-6000031846', type: 'Folio PT', title: 'DEMO-6000031846 · Pallet completo', subtitle: 'Agrícola Valle Sur · Cereza Santina · 184 cajas',
                events: [
                    { time: isoAt(now, -510), area: 'Validación', detail: 'Pallet validado y folio creado.' },
                    { time: isoAt(now, -438), area: 'Prefrío', detail: 'Asignado al proceso PREF-DEMO-081.' },
                    { time: isoAt(now, -36), area: 'Cámaras', detail: 'Ubicado en Cámara Producto 1 · B07-P03-N1.' },
                    { time: isoAt(now, -12), area: 'Cargas', detail: 'Reservado para CAR-DEMO-004.' },
                ],
            },
            {
                key: 'LOT-DEMO-238', type: 'Lote MP', title: 'LOT-DEMO-238 · Materia prima', subtitle: 'Frutícola Andes · Cereza Santina · 42 bins',
                events: [
                    { time: isoAt(now, -690), area: 'Romana', detail: 'Recepción REC-DEMO-0107 cerrada.' },
                    { time: isoAt(now, -652), area: 'Validación MP', detail: 'Cantidad física confirmada sin diferencias.' },
                    { time: isoAt(now, -590), area: 'Hidrocooler', detail: 'Enfriamiento completado con 4 bombas operativas.' },
                    { time: isoAt(now, -545), area: 'Materia Prima', detail: 'Lote liberado para proceso.' },
                ],
            },
            {
                key: 'MAT-DEMO-001', type: 'Material', title: 'MAT-DEMO-001 · Caja exportación 5 kg', subtitle: 'Agrícola Valle Sur · saldo disponible 35.800 unidades',
                events: [
                    { time: isoAt(now, -1440), area: 'Recepción Materiales', detail: 'Ingreso de 48.200 unidades confirmado.' },
                    { time: isoAt(now, -360), area: 'Inventario', detail: '12.400 unidades reservadas para Packing.' },
                    { time: isoAt(now, -45), area: 'Despacho', detail: 'Incluido en DES-DEMO-041.' },
                ],
            },
        ],
        audit: [
            { time: now.toISOString(), action: 'Escenario preparado', detail: 'Datos ficticios cargados únicamente en la sesión del navegador.' },
        ],
    };
}

export function enableDemoSession(storage, administrator, authorization, clock = () => new Date()) {
    if (administrator?.rol !== 'administrador' || administrator?.puede_habilitar_demo !== true) {
        throw new Error('Solo un administrador puede habilitar la versión demo.');
    }
    if (authorization?.autorizado !== true
        || authorization?.version_escenario !== DEMO_SCENARIO_VERSION
        || authorization?.administrador?.id !== administrator.id) {
        throw new Error('La autorización de la versión demo no es válida.');
    }

    const enabledAt = clock().toISOString();
    const session = {
        version: DEMO_SCENARIO_VERSION,
        administratorId: administrator.id,
        administratorName: administrator.nombre,
        enabledAt,
    };
    const dataset = createDemoDataset(clock);
    storage.setItem(DEMO_SESSION_KEY, JSON.stringify(session));
    storage.setItem(DEMO_DATA_KEY, JSON.stringify(dataset));

    return { session: clone(session), dataset: clone(dataset) };
}

export function readDemoSession(storage, administratorId = null) {
    try {
        const session = JSON.parse(storage.getItem(DEMO_SESSION_KEY) || 'null');
        const dataset = JSON.parse(storage.getItem(DEMO_DATA_KEY) || 'null');
        if (!session
            || !dataset
            || session.version !== DEMO_SCENARIO_VERSION
            || dataset.meta?.scenarioVersion !== DEMO_SCENARIO_VERSION) return null;
        if (administratorId && session.administratorId !== administratorId) return null;
        return { session, dataset };
    } catch {
        return null;
    }
}

export function restoreDemoScenario(storage, clock = () => new Date()) {
    const current = readDemoSession(storage);
    if (!current) throw new Error('La sesión demo ya no se encuentra activa.');
    const dataset = createDemoDataset(clock);
    dataset.audit[0].action = 'Escenario restaurado';
    storage.setItem(DEMO_DATA_KEY, JSON.stringify(dataset));
    return clone(dataset);
}

export function advanceDemoScenario(storage, clock = () => new Date()) {
    const current = readDemoSession(storage);
    if (!current) throw new Error('La sesión demo ya no se encuentra activa.');
    const dataset = current.dataset;
    const now = clock();

    dataset.meta.cut += 1;
    dataset.meta.generatedAt = now.toISOString();
    dataset.summary.activeFolios += 2;
    dataset.summary.occupiedPositions += 2;
    dataset.summary.occupancy = percent(dataset.summary.occupiedPositions, dataset.summary.totalPositions);
    dataset.summary.netKilogramsReceived += 13860;
    dataset.rawMaterial.receivedToday += 1;
    dataset.rawMaterial.hydrocoolers[0].processed = Math.min(
        dataset.rawMaterial.hydrocoolers[0].containers,
        dataset.rawMaterial.hydrocoolers[0].processed + 6,
    );
    dataset.refrigerated.cameras[0].occupied += 2;
    dataset.refrigerated.cameras[0].occupancy = percent(
        dataset.refrigerated.cameras[0].occupied,
        dataset.refrigerated.cameras[0].total,
    );
    dataset.refrigerated.loads[1].ready = Math.min(
        dataset.refrigerated.loads[1].folios,
        dataset.refrigerated.loads[1].ready + 2,
    );
    dataset.audit.unshift({
        time: now.toISOString(),
        action: `Corte demo ${dataset.meta.cut}`,
        detail: 'Se simuló una recepción, un ciclo de hidrocooler y dos pallets preparados para carga.',
    });
    storage.setItem(DEMO_DATA_KEY, JSON.stringify(dataset));
    return clone(dataset);
}

export function disableDemoSession(storage) {
    storage.removeItem(DEMO_SESSION_KEY);
    storage.removeItem(DEMO_DATA_KEY);
}
