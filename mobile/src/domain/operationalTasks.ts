import { MovementType } from './estiba';

export type OperationalTaskPriority = 'normal' | 'alta' | 'urgente' | 'critica';
export type OperationalTaskState = 'pendiente' | 'asumida' | 'en_proceso' | 'completada' | 'cancelada';

export type OperationalTaskEndpoint = {
  camara: {
    id: string;
    nombre: string;
  };
  posicion: {
    id: string;
    etiqueta: string | null;
    banda: number;
    posicion: number;
    nivel: number;
  } | null;
} | null;

export type OperationalTaskReservation = {
  id: string;
  estado: string;
  tipo_compromiso: 'claim' | 'fisica';
  destino_reservado: boolean;
  reservada_at: string | null;
  renovada_at: string | null;
  vence_at: string | null;
  segundos_restantes: number | null;
  version: number;
};

export type OperationalTask = {
  id: string;
  plan: {
    id: string;
    tipo: string;
    estado: string;
    prioridad: OperationalTaskPriority;
    titulo: string;
    version: number;
    horizon?: 'batch' | 'rolling';
  };
  secuencia: number;
  tipo_movimiento: MovementType;
  estado: OperationalTaskState;
  prioridad: OperationalTaskPriority;
  punto_no_retorno: boolean;
  folio: {
    id: string;
    numero_folio: string;
    tipo_bulto: 'pallet';
  };
  origen: OperationalTaskEndpoint;
  destino: OperationalTaskEndpoint;
  responsable: {
    id: string | number;
    nombre: string;
  } | null;
  dispositivo: {
    id: string;
    codigo: string;
    nombre: string;
  } | null;
  reserva: OperationalTaskReservation | null;
  instruccion: string | null;
  contexto: Record<string, unknown>;
  asumida_at: string | null;
  iniciada_at: string | null;
  completada_at: string | null;
  cancelada_at: string | null;
  version: number;
  created_at: string;
};

export type PlannerMode = 'off' | 'shadow' | 'guided';
export type PlannerCompute = 'server' | 'tablet';
export type PlannerHorizon = 'batch' | 'rolling';

export type OperationalSnapshot = {
  snapshot_version: string;
  generado_at: string;
  planner: {
    mode: PlannerMode;
    compute: PlannerCompute;
    horizon: PlannerHorizon;
    frontier_max: number;
  };
  plan: {
    id: string;
    tipo: string;
    estado: string;
    prioridad: OperationalTaskPriority;
    titulo: string;
    version: number;
  };
  camaras: Array<{
    id: string;
    codigo: string;
    nombre: string;
    version_plano: number;
    revision_reservas: number;
  }>;
  tareas: Array<{
    id: string;
    version: number;
    estado: OperationalTaskState;
    folio_id: string;
    camara_origen_id: string | null;
    posicion_origen_id: string | null;
    camara_destino_id: string | null;
    posicion_destino_id: string | null;
    destino_reservado: boolean;
  }>;
};

export type OperationalFrontierProposal = {
  tarea_id: string;
  posicion_destino_id: string;
  tarea_version: number;
  plan_version: number;
  version_camara_conocida: number;
  score?: number;
  motivo?: string;
};

export type OperationalFrontierResult = {
  aceptadas: Array<{
    tarea: OperationalTask;
    score: number | null;
    motivo: string | null;
    planner_version: string;
  }>;
  rechazadas: Array<{
    tarea_id: string;
    posicion_destino_id?: string;
    motivo: string;
  }>;
  recalcular: boolean;
  snapshot: OperationalSnapshot;
};

export type OperationalTaskAssignment = 'disponibles' | 'mias';

export const OPERATIONAL_TASK_LABELS: Record<string, string> = {
  recepcion_tunel: 'Recibir túnel',
  almacenamiento_pallet: 'Guardar pallet',
  despacho_directo: 'Retirar a andén',
  preparacion_inspeccion: 'Preparar inspección',
  concentracion_carga: 'Concentrar carga',
  segregacion_retenido: 'Segregar retenido',
  movimiento_oportunidad: 'Movimiento de oportunidad',
  reordenamiento_camara: 'Reordenar cámara',
  desocupacion_camara: 'Desocupar cámara',
  evacuacion_emergencia: 'Emergencia',
  correccion_discrepancia: 'Corregir discrepancia',
};

export function operationalTaskLabel(type: string) {
  return OPERATIONAL_TASK_LABELS[type]
    ?? type.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
}

export function operationalTaskPositionLabel(endpoint: OperationalTaskEndpoint) {
  if (!endpoint) return 'Por calcular';
  if (!endpoint.posicion) return `${endpoint.camara.nombre} · posición por calcular`;

  const position = endpoint.posicion;
  const physical = position.etiqueta
    ?? `B${String(position.banda).padStart(2, '0')}-P${String(position.posicion).padStart(2, '0')}-N${position.nivel}`;

  return `${endpoint.camara.nombre} · ${physical}`;
}

export function positionScanMatches(task: OperationalTask, scannedValue: string) {
  const position = task.destino?.posicion;
  if (!position) return false;

  const normalized = normalizeScan(scannedValue);
  const generated = `B${String(position.banda).padStart(2, '0')}-P${String(position.posicion).padStart(2, '0')}-N${position.nivel}`;
  const candidates = [position.id, position.etiqueta, generated]
    .filter((value): value is string => Boolean(value))
    .map(normalizeScan);

  return candidates.includes(normalized);
}

export function folioScanMatches(task: OperationalTask, scannedValue: string) {
  return normalizeScan(scannedValue) === normalizeScan(task.folio.numero_folio);
}

function normalizeScan(value: string) {
  return value.trim().toUpperCase();
}
