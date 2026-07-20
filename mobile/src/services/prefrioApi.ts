import {
  CreatePrefrioProcessPayload,
  PrefrioActionPayload,
  PrefrioFolioCandidate,
  PrefrioProcess,
  PrefrioTunnel,
} from '../domain/prefrio';
import { ApiError } from './apiError';

function responseMessage(data: unknown, fallback: string) {
  if (!data || typeof data !== 'object') return fallback;
  const response = data as { message?: string; errors?: Record<string, string[]> };
  const first = response.errors ? Object.values(response.errors).flat()[0] : null;
  return first ?? response.message ?? fallback;
}

async function request<T>(
  baseUrl: string,
  path: string,
  token: string,
  init: RequestInit = {},
): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  headers.set('Authorization', `Bearer ${token}`);
  if (init.body) headers.set('Content-Type', 'application/json');

  let response: Response;
  try {
    response = await fetch(`${baseUrl}${path}`, { ...init, headers });
  } catch {
    throw new ApiError('La PDA no puede alcanzar el servidor. La operación permanece en la bandeja.', 0);
  }

  const data = response.status === 204
    ? null
    : await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new ApiError(
      responseMessage(data, 'No fue posible completar la operación de prefrío.'),
      response.status,
      data,
    );
  }

  return data as T;
}

export async function listPrefrioTunnels(baseUrl: string, token: string) {
  const response = await request<{ data: PrefrioTunnel[] }>(
    baseUrl,
    '/api/prefrio/tuneles',
    token,
  );
  return response.data;
}

export async function listPrefrioProcesses(baseUrl: string, token: string) {
  const response = await request<{ data: PrefrioProcess[] }>(
    baseUrl,
    '/api/prefrio/procesos?per_page=50',
    token,
  );
  return response.data;
}

export async function getPrefrioProcess(
  baseUrl: string,
  token: string,
  processId: string,
) {
  const response = await request<{ data: PrefrioProcess }>(
    baseUrl,
    `/api/prefrio/procesos/${processId}`,
    token,
  );
  return response.data;
}

export async function listEligiblePrefrioFolios(
  baseUrl: string,
  token: string,
  limit = 500,
) {
  const response = await request<{ data: PrefrioFolioCandidate[] }>(
    baseUrl,
    `/api/prefrio/folios-disponibles?limit=${limit}`,
    token,
  );
  return response.data;
}

export async function findEligiblePrefrioFolios(
  baseUrl: string,
  token: string,
  folio: string,
) {
  const response = await request<{ data: PrefrioFolioCandidate[] }>(
    baseUrl,
    `/api/prefrio/folios-disponibles?folio=${encodeURIComponent(folio)}&limit=100`,
    token,
  );
  return response.data;
}

export async function createPrefrioProcess(
  baseUrl: string,
  token: string,
  payload: CreatePrefrioProcessPayload,
) {
  const response = await request<{ data: PrefrioProcess }>(
    baseUrl,
    '/api/prefrio/procesos',
    token,
    { method: 'POST', body: JSON.stringify(payload) },
  );
  return response.data;
}

export async function executePrefrioCommand(
  baseUrl: string,
  token: string,
  route: string,
  payload: PrefrioActionPayload | Record<string, unknown>,
) {
  const response = await request<{ data: PrefrioProcess }>(
    baseUrl,
    route,
    token,
    { method: 'POST', body: JSON.stringify(payload) },
  );
  return response.data;
}
