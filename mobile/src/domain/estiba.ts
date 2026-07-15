export type ApiMode = 'demo' | 'connected' | 'unconfigured';
export type AccessMode = 'disponible' | 'edicion' | 'solo_lectura';
export type MovementType =
  | 'ubicacion_inicial'
  | 'reubicacion'
  | 'traslado_entre_camaras'
  | 'retiro'
  | 'reversion';

export type UserIdentity = {
  id: string;
  nombre: string;
  email: string;
  rol: string;
};

export type DeviceIdentity = {
  id: string;
  codigo: string;
  nombre: string;
};

export type AuthSession = {
  token: string;
  token_type: 'Bearer';
  usuario: UserIdentity;
  dispositivo: DeviceIdentity;
};

export type LoginPayload = {
  email: string;
  password: string;
  codigo_dispositivo: string;
};

export type SagCondition = {
  id: string;
  codigo: string;
  nombre: string;
};

export type EditSession = {
  id: string;
  es_propia: boolean;
  usuario: { id: string; nombre: string };
  dispositivo: { id: string; nombre: string };
  iniciada_at: string;
  ultima_actividad_at: string;
};

export type CameraAccess = {
  modo: AccessMode;
  bloqueada: boolean;
  sesion: EditSession | null;
};

export type Occupancy = {
  ocupadas: number;
  total: number;
  porcentaje: number;
};

export type CameraSummary = {
  id: string;
  codigo: string;
  nombre: string;
  tipo: string;
  contenido: 'productos' | 'materiales';
  estado: string;
  version_plano: number;
  ocupacion: Occupancy;
  acceso: CameraAccess;
};

export type Folio = {
  id: string;
  numero_folio: string;
  tipo_bulto: 'pallet' | 'saldo' | 'material';
  estado_operacional: string;
  condicion_sag: SagCondition | null;
  fecha_ingreso: string | null;
  variedad: string | null;
  calibre: string | null;
  marca: string | null;
  exportadora: string | null;
  material: FolioMaterial | null;
  ubicado_at: string | null;
};

export type MaterialItem = {
  id: string;
  codigo: string;
  nombre: string;
  categoria: string | null;
  unidad_medida: string;
  activo: boolean;
};

export type MaterialDestination = {
  id: string;
  nombre: string;
  centro_costo: string;
  descripcion: string | null;
  activo: boolean;
};

export type FolioMaterial = {
  item: Omit<MaterialItem, 'unidad_medida' | 'activo'>;
  cantidad_inicial: string;
  cantidad_actual: string;
  cantidad_reservada: string;
  cantidad_disponible: string;
  unidad_medida: string;
  lote: string | null;
  proveedor: string | null;
  observacion: string | null;
};

export type MaterialCatalog = {
  items: MaterialItem[];
  destinos: MaterialDestination[];
};

export type MaterialDispatchItem = {
  detalle_id: string;
  item: Omit<MaterialItem, 'unidad_medida' | 'activo'>;
  cantidad_solicitada: string;
  cantidad_despachada: string;
  cantidad_pendiente: string;
  cantidad_reservada: string;
  unidad_medida: string;
  sugerencias_fifo: Array<{
    folio_id: string;
    numero_folio: string;
    cantidad: string;
    camara: { id: string; codigo: string; nombre: string } | null;
    posicion: { id: string; etiqueta: string } | null;
  }>;
};

export type MaterialDispatch = {
  id: string;
  codigo: string;
  origen: 'oficina' | 'tablet';
  estado: 'pendiente' | 'parcial' | 'completado' | 'cancelado';
  destino: { id: string; nombre: string; centro_costo: string };
  observacion: string | null;
  items: MaterialDispatchItem[];
  created_at: string;
};

export type Position = {
  id: string;
  banda: number;
  posicion: number;
  nivel: number;
  etiqueta: string | null;
  estado: string;
  ocupada: boolean;
  folio: Folio | null;
};

export type CameraPlan = CameraSummary & {
  posiciones: Position[];
};

export type MovementEnd = {
  camara: { id: string; codigo: string; nombre: string | null };
  posicion: {
    id: string;
    banda: number;
    posicion: number;
    nivel: number;
    etiqueta: string | null;
  };
  version_anterior: number;
  version_resultante: number;
};

export type Movement = {
  id: string;
  operacion_id: string;
  tipo_movimiento: MovementType;
  folio: { id: string; numero_folio: string; tipo_bulto: string };
  origen: MovementEnd | null;
  destino: MovementEnd | null;
  usuario: { id: string; nombre: string };
  generado_dispositivo_at: string;
  recibido_servidor_at: string;
  created_at: string;
};

export type OpenedSession = {
  id: string;
  camara_id: string;
  estado: string;
  version_inicial: number;
  version_final: number | null;
  iniciada_at: string;
  ultima_actividad_at: string;
  cerrada_at: string | null;
  motivo_cierre: string | null;
  usuario: { id: string; nombre: string };
  dispositivo: { id: string; nombre: string };
};

export type LocatePayload = {
  operacion_id: string;
  numero_folio: string;
  tipo_bulto: 'pallet' | 'saldo' | 'material';
  posicion_destino_id: string;
  sesion_destino_id: string;
  version_destino_conocida: number;
  generado_dispositivo_at: string;
  advertencias_confirmadas?: string[];
  datos_folio?: {
    condicion_sag_id?: string;
    variedad?: string;
    calibre?: string;
    marca?: string;
    exportadora?: string;
  };
  datos_material?: {
    item_material_id: string;
    cantidad: number;
    lote?: string;
    proveedor?: string;
    observacion?: string;
  };
};

export type CreateMaterialDispatchPayload = {
  operacion_id: string;
  destino_material_id: string;
  observacion?: string;
  items: Array<{ item_material_id: string; cantidad: number }>;
};

export type WithdrawMaterialPayload = {
  operacion_id: string;
  retiros: Array<{ folio_id: string; cantidad: number; sesion_estiba_id: string }>;
};

export type MovePayload = {
  operacion_id: string;
  folio_id: string;
  posicion_destino_id: string;
  sesion_origen_id: string;
  sesion_destino_id: string;
  version_origen_conocida: number;
  version_destino_conocida: number;
  generado_dispositivo_at: string;
  advertencias_confirmadas?: string[];
};

export type ApiList<T> = { data: T[] };
export type ApiItem<T> = { data: T };
