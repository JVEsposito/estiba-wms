import { CreateProcessDelivery, ProcessLot, ProcessSummary } from '../domain/frutaProceso';
import { ApiError } from './apiError';

async function request<T>(baseUrl: string, path: string, token: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  headers.set('Authorization', `Bearer ${token}`);
  if (init.body) headers.set('Content-Type', 'application/json');
  let response: Response;
  try { response = await fetch(`${baseUrl}${path}`, { ...init, headers }); }
  catch { throw new ApiError('No hay conexión con el servidor. Reintenta cuando vuelva la red.', 0); }
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const detail = data as { message?: string; errors?: Record<string, string[]> };
    throw new ApiError(Object.values(detail.errors ?? {}).flat()[0] ?? detail.message ?? 'No fue posible completar Fruta a proceso.', response.status, data);
  }
  return data as T;
}

export async function getProcessSummary(baseUrl: string, token: string) {
  return request<ProcessSummary>(baseUrl, '/api/materia-prima/fruta-proceso/resumen', token);
}

export async function listProcessLots(baseUrl: string, token: string, search = '', status = 'abiertos') {
  const params = new URLSearchParams({ per_page: '200', estado: status });
  if (search.trim()) params.set('buscar', search.trim());
  return (await request<{ data: ProcessLot[] }>(baseUrl, `/api/materia-prima/fruta-proceso/lotes?${params}`, token)).data;
}

export async function createProcessDelivery(baseUrl: string, token: string, lotId: string, input: CreateProcessDelivery) {
  return (await request<{ data: ProcessLot }>(baseUrl, `/api/materia-prima/fruta-proceso/lotes/${lotId}/entregas`, token, {
    method: 'POST', body: JSON.stringify(input),
  })).data;
}

export async function annulProcessDelivery(baseUrl: string, token: string, deliveryId: string, operationId: string, reason: string) {
  return (await request<{ data: ProcessLot }>(baseUrl, `/api/materia-prima/fruta-proceso/entregas/${deliveryId}/anular`, token, {
    method: 'POST', body: JSON.stringify({ operacion_id: operationId, motivo: reason }),
  })).data;
}
