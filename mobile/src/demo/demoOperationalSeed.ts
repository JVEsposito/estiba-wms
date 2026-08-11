import {
  CameraPlan,
  EditSession,
  Folio,
  Movement,
  Position,
  SagCondition,
} from '../domain/estiba';

export const DEMO_OPERATIONAL_STATE_KEY = 'primary';

export const demoSagConditions: SagCondition[] = [
  { id: 'sag-apta', codigo: 'APTA', nombre: 'Apta para exportación' },
  { id: 'sag-pendiente', codigo: 'PENDIENTE', nombre: 'Pendiente de inspección' },
  { id: 'sag-observada', codigo: 'OBSERVADA', nombre: 'Con observación SAG' },
];

export const demoOperator = { id: 'user-demo', nombre: 'Administrador Demo' };
export const demoDevice = { id: 'device-demo', nombre: 'Tablet autónoma Demo' };

export type DemoOperationalState = {
  schemaVersion: 1;
  plans: CameraPlan[];
  movements: Movement[];
  operationFingerprints: Record<string, string>;
};

function createFolio(index: number, now: string, type: 'pallet' | 'saldo' = 'pallet'): Folio {
  return {
    id: `operational-folio-${index}`,
    numero_folio: `FOL-${String(1188 + index * 137).padStart(4, '0')}`,
    tipo_bulto: type,
    estado_operacional: 'en_camara',
    condicion_sag: demoSagConditions[index % demoSagConditions.length],
    fecha_ingreso: now,
    variedad: ['Santina', 'Lapins', 'Regina'][index % 3],
    calibre: ['2J', '3J', 'J'][index % 3],
    marca: 'Demo Frío',
    exportadora: 'Exportadora Demo',
    material: null,
    ubicado_at: now,
  };
}

function createPositions(cameraId: string, occupiedIndexes: number[], now: string): Position[] {
  let index = 0;
  const positions: Position[] = [];

  for (const level of [1, 2]) {
    for (const band of [1, 2, 3]) {
      for (const position of [1, 2, 3, 4]) {
        const occupied = occupiedIndexes.includes(index);
        positions.push({
          id: `${cameraId}-B${band}-P${position}-N${level}`,
          banda: band,
          posicion: position,
          nivel: level,
          etiqueta: `B${String(band).padStart(2, '0')}-P${String(position).padStart(2, '0')}-N${level}`,
          estado: index === 22 ? 'bloqueada' : 'activa',
          ocupada: occupied,
          folio: occupied ? createFolio(index, now, index % 5 === 0 ? 'saldo' : 'pallet') : null,
        });
        index += 1;
      }
    }
  }

  return positions;
}

function externalSession(cameraId: string, now: string): EditSession {
  return {
    id: `session-other-${cameraId}`,
    es_propia: false,
    usuario: { id: 'user-maria', nombre: 'María P.' },
    dispositivo: { id: 'tablet-02', nombre: 'Tablet cámara 02' },
    iniciada_at: now,
    ultima_actividad_at: now,
  };
}

function createPlan(
  id: string,
  code: string,
  name: string,
  occupied: number[],
  now: string,
  locked = false,
): CameraPlan {
  return syncDemoOccupancy({
    id,
    codigo: code,
    nombre: name,
    tipo: code.startsWith('DES') ? 'despacho' : 'transito',
    contenido: 'productos',
    estado: 'activa',
    version_plano: 3,
    ocupacion: { ocupadas: 0, sin_posicion: 0, total: 0, porcentaje: 0 },
    acceso: locked
      ? { modo: 'solo_lectura', bloqueada: true, sesion: externalSession(id, now) }
      : { modo: 'disponible', bloqueada: false, sesion: null },
    folios_sin_posicion: [],
    posiciones: createPositions(id, occupied, now),
  });
}

export function syncDemoOccupancy(plan: CameraPlan): CameraPlan {
  const total = plan.posiciones.length;
  const occupied = plan.posiciones.filter((position) => position.ocupada).length;
  plan.ocupacion = {
    ocupadas: occupied,
    sin_posicion: plan.folios_sin_posicion.length,
    total,
    porcentaje: total === 0 ? 0 : Math.round((occupied / total) * 1000) / 10,
  };
  return plan;
}

export function demoMovementEnd(
  plan: CameraPlan,
  position: Position,
  previousVersion = Math.max(0, plan.version_plano - 1),
  resultingVersion = plan.version_plano,
): NonNullable<Movement['destino']> {
  return {
    camara: { id: plan.id, codigo: plan.codigo, nombre: plan.nombre },
    posicion: {
      id: position.id,
      banda: position.banda,
      posicion: position.posicion,
      nivel: position.nivel,
      etiqueta: position.etiqueta,
    },
    version_anterior: previousVersion,
    version_resultante: resultingVersion,
  };
}

export function createInitialOperationalState(): DemoOperationalState {
  const now = new Date().toISOString();
  const plans = [
    createPlan('camera-01', 'CAM-01', 'Cámara de tránsito 01', [0, 2, 5, 7, 9, 13, 16], now),
    createPlan('camera-02', 'CAM-02', 'Cámara de tránsito 02', [1, 3, 4, 8, 10, 12, 15, 18], now, true),
    createPlan('dispatch-01', 'DES-01', 'Zona de despacho', [6, 11], now),
  ];
  const firstPlan = plans[0];
  const occupied = firstPlan.posiciones.filter((position) => position.folio).slice(0, 3);
  const movements = occupied.map((position, index): Movement => ({
    id: `movement-seed-${index}`,
    operacion_id: `operation-seed-${index}`,
    tipo_movimiento: 'ubicacion_inicial',
    folio: {
      id: position.folio!.id,
      numero_folio: position.folio!.numero_folio,
      tipo_bulto: position.folio!.tipo_bulto,
    },
    origen: null,
    destino: demoMovementEnd(firstPlan, position),
    usuario: demoOperator,
    generado_dispositivo_at: now,
    recibido_servidor_at: now,
    created_at: now,
  }));

  return {
    schemaVersion: 1,
    plans,
    movements,
    operationFingerprints: {},
  };
}
