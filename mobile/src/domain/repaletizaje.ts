export type RepalletizingFolio = {
  existe: boolean;
  id: string;
  numero_folio: string;
  tipo_bulto: string;
  cantidad_cajas: number;
  activo: boolean;
  estado_operacional: string;
  condicion_termica: string;
  cliente: string | null;
  especie: string | null;
  marca: string | null;
  variedad: string | null;
  calibre: string | null;
  envase: string | null;
  categoria: string | null;
  csg: string | null;
  predio: string | null;
  cuartel: string | null;
};

export type CreateRepalletizing = {
  operacion_id: string;
  tipo_resultado: 'pallet' | 'saldo';
  estrategia_folio: 'conservar' | 'nuevo';
  numero_folio_resultante: string;
  folio_conservado_id: string | null;
  cantidad_objetivo: number | null;
  origenes: Array<{ folio_id: string; cantidad_aportada: number }>;
  observacion: string | null;
};

export type Repalletizing = {
  id: string;
  codigo: string;
  tipo_resultado: 'pallet' | 'saldo';
  estrategia_folio: 'conservar' | 'nuevo';
  cantidad_objetivo: number | null;
  cantidad_resultante: number;
  condicion_termica: string;
  estado: string;
  campos_mix: string[];
  advertencias: Array<{ campo: string; mensaje: string }>;
  folio_resultante: {
    id: string;
    numero_folio: string;
    tipo_bulto: string;
    cantidad_cajas: number;
    estado_operacional: string;
    condicion_termica: string;
  };
  origenes: Array<{
    id: string;
    orden: number;
    cajas_antes: number;
    cajas_aportadas: number;
    cajas_despues: number;
    folio: { id: string; numero_folio: string };
  }>;
  confirmado_at: string;
};
