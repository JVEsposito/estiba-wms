import { ReceptionDefect, ReceptionDefectDraft, ReceptionDefectPhoto } from '../domain/receptionDefects';
import { ApiError } from './apiError';

const path = (receptionId: string) => `/api/validacion-mp/recepciones/${encodeURIComponent(receptionId)}/defectos`;

async function readResponse(response: Response): Promise<{ data?: ReceptionDefect; message?: string; errors?: Record<string, string[]> }> {
  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    const detail = body as { message?: string; errors?: Record<string, string[]> };
    throw new ApiError(Object.values(detail.errors ?? {}).flat()[0] ?? detail.message ?? 'No fue posible guardar el defecto.', response.status, body);
  }
  return body;
}

export async function listReceptionDefects(baseUrl: string, token: string, receptionId: string): Promise<ReceptionDefect[]> {
  let response: Response;
  try {
    response = await fetch(`${baseUrl}${path(receptionId)}`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
  } catch {
    throw new ApiError('Sin conexión. Intenta actualizar el registro de defectos.', 0);
  }
  const body = await readResponse(response) as { data?: ReceptionDefect[] };
  return body.data ?? [];
}

function attachPhoto(form: FormData, field: string, photo: ReceptionDefectPhoto) {
  // React Native transmite el archivo de la URI local. No enviar Content-Type en la
  // solicitud: fetch genera el boundary del multipart automáticamente.
  form.append(field, { uri: photo.uri, name: photo.name, type: photo.type } as unknown as Blob);
}

export async function createReceptionDefect(baseUrl: string, token: string, receptionId: string, draft: ReceptionDefectDraft): Promise<ReceptionDefect> {
  const form = new FormData();
  form.append('operacion_id', draft.operacionId);
  form.append('categoria', draft.categoria);
  form.append('descripcion', draft.descripcion.trim());
  if (draft.tipoEnvase) form.append('tipo_envase', draft.tipoEnvase);
  if (draft.cantidadAfectada.trim()) form.append('cantidad_afectada', draft.cantidadAfectada.trim());
  draft.fotografias.forEach((photo, index) => attachPhoto(form, `fotografias[${index}]`, photo));
  if (draft.fotografiaGuia) attachPhoto(form, 'fotografia_guia', draft.fotografiaGuia);

  let response: Response;
  try {
    response = await fetch(`${baseUrl}${path(receptionId)}`, {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      body: form,
    });
  } catch {
    throw new ApiError('No se pudo confirmar el envío. Reintenta sin cambiar los datos para evitar duplicados.', 0);
  }
  const body = await readResponse(response);
  if (!body.data) throw new ApiError('El servidor no devolvió el registro. Reintenta sin cambiar los datos.', 0);
  return body.data;
}
