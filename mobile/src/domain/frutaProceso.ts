export type ProcessDelivery = {
  id: string;
  cantidad_envases: number;
  saldo_anterior: number;
  saldo_posterior: number;
  linea_proceso: string;
  turno: 'A' | 'B';
  numero_orden: string;
  observacion: string | null;
  entregado_por: { id: string; nombre: string } | null;
  dispositivo: { id: string; codigo: string; nombre: string } | null;
  entregado_at: string;
  anulado: boolean;
  anulado_por: string | null;
  anulado_at: string | null;
  motivo_anulacion: string | null;
  puede_anular: boolean;
};

export type ProcessLot = {
  id: string;
  numero_lote: string;
  estado: 'asignado_camara' | 'entrega_parcial_proceso' | 'entregado_proceso';
  version: number;
  cliente: { id: string; codigo: string; nombre: string } | null;
  recepcion: { id: string; numero_recepcion: string; numero_guia_despacho: string } | null;
  producto: {
    especie: string;
    variedad: string;
    calibre: string | null;
    csg: string;
    predio: string;
    cuartel: string | null;
    tipo: string;
  };
  camara: { id: string; codigo: string; nombre: string } | null;
  envase_primario: string;
  progreso: { total: number; entregados: number; disponibles: number; porcentaje: number };
  entregas: ProcessDelivery[];
  ultima_entrega_at: string | null;
};

export type ProcessSummary = {
  temporada: { id: string; codigo: string; nombre: string } | null;
  lotes_abiertos: number;
  lotes_completados: number;
  bins_disponibles: number;
  bins_entregados: number;
};

export type CreateProcessDelivery = {
  operacion_id: string;
  cantidad_envases: number;
  linea_proceso: string;
  turno: 'A' | 'B';
  numero_orden: string;
  observacion: string | null;
};
