export type MaterialTransformationState =
  | 'borrador'
  | 'planificada'
  | 'en_proceso'
  | 'pendiente_cierre'
  | 'cerrada'
  | 'cancelada';

export type MaterialTransformationItem = {
  id: string;
  codigo: string;
  nombre: string;
  unidad_medida: string;
};

export type MaterialTransformationReservation = {
  id: string;
  estado: 'activa' | 'consumida' | 'liberada';
  cantidad: string;
  cantidad_consumida: string;
  cantidad_pendiente: string;
  orden_fifo: number;
  item_material_id: string;
  folio: {
    id: string;
    numero_folio: string;
    cantidad_actual: string;
    cantidad_reservada: string;
    ubicacion: { camara: string; posicion: string } | null;
  } | null;
};

export type MaterialTransformationConsumption = {
  id: string;
  folio_id: string;
  numero_folio: string;
  item: MaterialTransformationItem;
  cantidad_consumida: string;
  cantidad_anterior: string;
  cantidad_resultante: string;
  siguio_fifo: boolean;
  motivo_desviacion_fifo: string | null;
  ocurrido_at: string;
};

export type MaterialTransformationOutput = {
  id: string;
  folio_id: string;
  numero_folio: string;
  item: MaterialTransformationItem;
  cantidad_producida: string;
  es_salida_principal: boolean;
};

export type MaterialTransformationLot = {
  id: string;
  numero_lote: number;
  estado: 'abierto' | 'cerrado' | 'anulado';
  cantidad_planificada_salida: string;
  cantidad_real_salida: string | null;
  salida_teorica: string | null;
  merma_estandar: string | null;
  merma_real: string | null;
  desviacion_merma: string | null;
  iniciado_at: string;
  cerrado_at: string | null;
  reversado_at: string | null;
  motivo_reversa: string | null;
  reversado_por: { id: number; nombre: string } | null;
  consumos: MaterialTransformationConsumption[];
  salidas: MaterialTransformationOutput[];
};

export type MaterialTransformationRecipeSnapshot = {
  salida: {
    item_id: string;
    codigo: string;
    nombre: string;
    cantidad_base: string;
    unidad_medida: string;
  };
  componentes: {
    item_id: string;
    codigo: string;
    nombre: string;
    cantidad_estandar: string;
    unidad_medida: string;
    es_componente_principal: boolean;
    factor_conversion: string;
    merma_estandar_porcentaje: string;
    tolerancia_porcentaje: string;
  }[];
};

export type MaterialTransformationOrder = {
  id: string;
  estado: MaterialTransformationState;
  version: number;
  cantidad_planificada_salida: string;
  cantidad_real_salida: string | null;
  linea: string | null;
  turno: string | null;
  fecha_operacional: string;
  observacion: string | null;
  receta_snapshot: MaterialTransformationRecipeSnapshot;
  cliente: { id: string; codigo: string; nombre: string };
  version_receta: {
    id: string;
    numero_version: number;
    estado: string;
    receta: {
      id: string;
      nombre: string;
      item_salida: MaterialTransformationItem;
    };
  };
  reservas: MaterialTransformationReservation[];
  lotes: MaterialTransformationLot[];
  iniciado_at: string | null;
  cerrado_at: string | null;
  created_at: string;
  updated_at: string;
};

export type MaterialTransformationOrderSummary = Omit<
  MaterialTransformationOrder,
  'receta_snapshot' | 'reservas' | 'lotes'
> & {
  reservas_count: number;
  lotes_count: number;
  tiene_salidas: boolean;
};

export type StartMaterialTransformationPayload = {
  operacion_id: string;
  version_conocida: number;
};

export type OpenMaterialTransformationLotPayload = StartMaterialTransformationPayload & {
  cantidad_planificada_salida: number;
};

export type CloseMaterialTransformationLotPayload = StartMaterialTransformationPayload & {
  cantidad_real_salida: number;
  consumos: {
    folio_id: string;
    cantidad: number;
    motivo_desviacion_fifo?: string;
  }[];
};

export type CloseMaterialTransformationOrderPayload = StartMaterialTransformationPayload & {
  motivo_desviacion?: string;
};

export type ReverseMaterialTransformationLotPayload = StartMaterialTransformationPayload & {
  motivo: string;
};
