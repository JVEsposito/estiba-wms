import {
  EnvironmentalControlPayload,
  EnvironmentalControlRecord,
  EnvironmentalControlState,
} from '../domain/environmentalControl';
import { ApiError } from './apiError';
import { fetchWithTimeout } from './httpClient';

function responseMessage(data: unknown, fallback: string) {
  if (!data || typeof data !== 'object') return fallback;
  const response = data as { message?: string; errors?: Record<string, string[]> };
  return response.errors
    ? Object.values(response.errors).flat()[0] ?? response.message ?? fallback
    : response.message ?? fallback;
}

async function request<T>(baseUrl: string, path: string, token: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  headers.set('Authorization', `Bearer ${token}`);
  if (init.body) headers.set('Content-Type', 'application/json');

  let response: Response;
  try {
    response = await fetchWithTimeout(`${baseUrl}${path}`, { ...init, headers });
  } catch {
    throw new ApiError('No fue posible alcanzar el servidor. El intento quedó guardado para reintentar.', 0);
  }

  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new ApiError(
      responseMessage(data, 'No fue posible completar el control ambiental.'),
      response.status,
      data,
    );
  }
  return data as T;
}

export function getEnvironmentalControlState(baseUrl: string, token: string) {
  return request<EnvironmentalControlState>(baseUrl, '/api/control-ambiental/estado', token);
}

export async function registerEnvironmentalControl(
  baseUrl: string,
  token: string,
  cameraId: string,
  payload: EnvironmentalControlPayload,
) {
  const response = await request<{ data: EnvironmentalControlRecord }>(
    baseUrl,
    `/api/control-ambiental/camaras/${encodeURIComponent(cameraId)}/registros`,
    token,
    { method: 'POST', body: JSON.stringify(payload) },
  );
  return response.data;
}
