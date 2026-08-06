import {
  CreateRepalletizing,
  Repalletizing,
  RepalletizingFolio,
} from '../domain/repaletizaje';

async function request<T>(
  baseUrl: string,
  token: string,
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const response = await fetch(`${baseUrl}${path}`, {
    ...init,
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
      ...(init.body ? { 'Content-Type': 'application/json' } : {}),
      ...(init.headers ?? {}),
    },
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const errors = Object.values(data.errors ?? {}).flat();
    throw new Error(String(errors[0] ?? data.message ?? 'No fue posible completar la operación.'));
  }

  return data as T;
}

export function findRepalletizingFolio(
  baseUrl: string,
  token: string,
  number: string,
): Promise<RepalletizingFolio> {
  return request(
    baseUrl,
    token,
    `/api/validacion/repaletizajes/folios/${encodeURIComponent(number)}`,
  );
}

export async function createRepalletizing(
  baseUrl: string,
  token: string,
  payload: CreateRepalletizing,
): Promise<Repalletizing> {
  const response = await request<{ data: Repalletizing }>(
    baseUrl,
    token,
    '/api/validacion/repaletizajes',
    { method: 'POST', body: JSON.stringify(payload) },
  );

  return response.data;
}

export async function listRepalletizings(
  baseUrl: string,
  token: string,
): Promise<Repalletizing[]> {
  const response = await request<{ data: Repalletizing[] }>(
    baseUrl,
    token,
    '/api/validacion/repaletizajes?per_page=20',
  );

  return response.data;
}
