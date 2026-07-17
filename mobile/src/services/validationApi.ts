import {
  RegisterValidationPayload,
  ValidationAttempt,
  ValidationCatalog,
} from '../domain/validation';
import { ApiError } from './apiError';

function validationMessage(data: unknown, fallback: string) {
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
    throw new ApiError('La PDA no puede alcanzar el servidor. La captura quedó pendiente.', 0);
  }

  const data = response.status === 204
    ? null
    : await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new ApiError(
      validationMessage(data, 'No fue posible completar la validación.'),
      response.status,
      data,
    );
  }

  return data as T;
}

export function getValidationCatalog(baseUrl: string, token: string) {
  return request<ValidationCatalog>(baseUrl, '/api/validacion/catalogos', token);
}

export async function registerValidation(
  baseUrl: string,
  token: string,
  payload: RegisterValidationPayload,
) {
  const response = await request<{ data: ValidationAttempt }>(
    baseUrl,
    '/api/validacion/pallets',
    token,
    { method: 'POST', body: JSON.stringify(payload) },
  );
  return response.data;
}

export async function listRecentValidations(baseUrl: string, token: string) {
  const response = await request<{ data: ValidationAttempt[] }>(
    baseUrl,
    '/api/validacion/pallets?per_page=10',
    token,
  );
  return response.data;
}
