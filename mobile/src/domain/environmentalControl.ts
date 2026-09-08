export type EnvironmentalControlStatus = 'pendiente' | 'vigente' | 'vencido';

export type EnvironmentalTemperatures = {
  inicio_c: number;
  medio_c: number;
  fondo_c: number;
};

export type EnvironmentalControlRecord = {
  id: string;
  operacion_id: string;
  temperaturas: EnvironmentalTemperatures;
  frecuencia_minutos: number;
  estado_vigencia: 'vigente' | 'vencido';
  vigente_hasta: string;
  version: number;
  registrado_por?: { id: string; nombre: string };
  dispositivo?: { id: string; codigo: string; nombre: string };
  capturado_at: string;
  recibido_servidor_at: string;
};

export type EnvironmentalCameraState = {
  camara: { id: string; codigo: string; nombre: string };
  estado: EnvironmentalControlStatus;
  requiere_control: boolean;
  vencido_desde: string | null;
  ultimo_registro: EnvironmentalControlRecord | null;
};

export type EnvironmentalControlState = {
  frecuencia_minutos: number;
  generado_at: string;
  camaras: EnvironmentalCameraState[];
};

export type EnvironmentalControlPayload = {
  operacion_id: string;
  temperatura_inicio_c: number;
  temperatura_medio_c: number;
  temperatura_fondo_c: number;
  capturado_at: string;
};

export type EnvironmentalControlDraft = {
  cameraId: string | null;
  cameraCode: string | null;
  start: string;
  middle: string;
  end: string;
  operationId: string;
  capturedAt: string | null;
};
