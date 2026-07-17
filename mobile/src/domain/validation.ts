export type ValidationResult = 'aprobado' | 'observado' | 'rechazado';
export type ValidationAttemptState = 'aceptada' | 'conflicto';

export type ValidationSeason = {
  id: string;
  codigo: string;
  nombre: string;
  fecha_inicio: string | null;
  fecha_fin: string | null;
  activa: boolean;
  version_catalogo: number;
};

export type ValidationArticle = {
  id: string;
  temporada_id: string;
  especie: string;
  variedad: string;
  calibre: string;
  envase: string;
  codigo_externo: string | null;
  activo: boolean;
};

export type ValidationOrigin = {
  id: string;
  temporada_id: string;
  cliente: string;
  marca: string;
  csg: string;
  predio: string | null;
  codigo_externo: string | null;
  activo: boolean;
};

export type ValidationCombination = {
  id: string;
  articulo_validacion_id: string;
  origen_validacion_id: string;
  codigo_externo: string | null;
};

export type ValidationCatalog = {
  temporada: ValidationSeason;
  articulos: ValidationArticle[];
  origenes: ValidationOrigin[];
  combinaciones: ValidationCombination[];
  tipos_bulto: Array<'pallet' | 'saldo'>;
  resultados: ValidationResult[];
  motivos: string[];
  generado_at: string;
};

export type RegisterValidationPayload = {
  operacion_id: string;
  numero_folio: string;
  tipo_bulto: 'pallet' | 'saldo';
  cantidad_cajas: number;
  temporada_id: string;
  catalogo_version: number;
  articulo_validacion_id: string;
  origen_validacion_id: string;
  resultado: ValidationResult;
  motivo?: string;
  observacion?: string;
  generado_dispositivo_at: string;
};

export type ValidationAttempt = {
  id: string;
  operacion_id: string;
  numero_folio: string;
  numero_intento: number;
  tipo_bulto: 'pallet' | 'saldo';
  cantidad_cajas: number;
  resultado: ValidationResult;
  estado: ValidationAttemptState;
  motivo: string | null;
  observacion: string | null;
  catalogo: {
    version_dispositivo: number;
    version_servidor: number;
    desactualizado: boolean;
    temporada: { codigo: string; nombre: string } | null;
    articulo: { especie: string; variedad: string; calibre: string; envase: string } | null;
    origen: { cliente: string; marca: string; csg: string; predio: string | null } | null;
  };
  folio: { id: string; numero_folio: string; estado_operacional: string } | null;
  usuario: { id: string; nombre: string };
  dispositivo: { id: string; codigo: string; nombre: string };
  conflicto_con: { id: string; numero_folio: string; numero_intento: number; resultado: ValidationResult } | null;
  generado_dispositivo_at: string;
  recibido_servidor_at: string;
};

export type ValidationOutboxStatus = 'pendiente' | 'conflicto' | 'error';

export type ValidationOutboxItem = {
  id: string;
  payload: RegisterValidationPayload;
  status: ValidationOutboxStatus;
  attempts: number;
  created_at: string;
  last_attempt_at: string | null;
  message: string | null;
};
