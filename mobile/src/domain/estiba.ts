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
  ambito_camaras: 'productos' | 'materiales' | 'ambos' | 'ninguno';
  capacidades: UserCapabilities;
};

export type UserCapabilities = {
  ambito_camaras: 'productos' | 'materiales' | 'ambos' | 'ninguno';
  puede_supervisar: boolean;
  puede_operar_productos: boolean;
  puede_operar_materiales: boolean;
  puede_consultar_cargas: boolean;
  puede_consultar_catalogo_cargas: boolean;
  puede_gestionar_cargas: boolean;
  puede_gestionar_andenes: boolean;
  puede_consultar_despachos_materiales: boolean;
  puede_gestionar_despachos_materiales: boolean;
  puede_retirar_materiales: boolean;
  puede_cancelar_despachos_materiales: boolean;
  puede_consultar_kardex_materiales: boolean;
  puede_validar_pallets: boolean;
  puede_rechazar_pallets: boolean;
  puede_consultar_validaciones_pallet: boolean;
  puede_administrar_catalogos_validacion?: boolean;
  puede_consultar_prefrio?: boolean;
  puede_operar_prefrio?: boolean;
  puede_supervisar_prefrio?: boolean;
  puede_administrar_tuneles_prefrio?: boolean;
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
  condicion_termica?:
    | 'pendiente_prefrio'
    | 'en_proceso'
    | 'prefrio_aprobado'
    | 'requiere_reproceso'
    | 'condicion_heredada'
    | 'retenido'
    | null;
  habilitacion_almacenamiento?: 'no_habilitado' | 'habilitado' | 'retenido' | null;
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

export type Dock = {
  id: string;
  codigo: string;
  nombre: string;
  activo: boolean;
};

export type LoadFolioState = 'pendiente' | 'con_incidencia' | 'en_anden';
