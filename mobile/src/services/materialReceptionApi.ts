import {
  CreateMaterialReceptionPayload,
  MaterialReception,
  MaterialReceptionCatalog,
  MaterialReceptionState,
  PendingReceptionFolio,
} from '../domain/materialReception';

export type MaterialReceptionApi = ReturnType<typeof createMaterialReceptionApi>;

type ApiItem<T> = { data: T };
type ApiList<T> = { data: T[] };
type PaginatedApiList<T> = { data: T[] };

export function createMaterialReceptionApi(baseUrl: string, token: string) {
  async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('Authorization', `Bearer ${token}`);
    if (init.body) headers.set('Content-Type', 'application/json');

    let response: Response;
    try {
      response = await fetch(`${baseUrl}${path}`, { ...init, headers });
    } catch {
      throw new Error(`No fue posible conectar con ${baseUrl}. Revisa Laravel, la IP y el firewall.`);
    }

    const data = response.status === 204
      ? null
      : await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(validationMessage(data, 'La operación no pudo completarse.'));
    }

    return data as T;
  }

  return {
    async catalog(): Promise<MaterialReceptionCatalog> {
      return request<MaterialReceptionCatalog>('/api/materiales/recepciones/catalogos');
    },

    async list(state?: MaterialReceptionState): Promise<MaterialReception[]> {
      const query = state ? `?estado=${encodeURIComponent(state)}&per_page=100` : '?per_page=100';
      return (await request<PaginatedApiList<MaterialReception>>(
        `/api/materiales/recepciones${query}`,
      )).data;
    },

    async show(id: string): Promise<MaterialReception> {
      return (await request<ApiItem<MaterialReception>>(
        `/api/materiales/recepciones/${encodeURIComponent(id)}`,
      )).data;
    },

    async create(payload: CreateMaterialReceptionPayload): Promise<MaterialReception> {
      return (await request<ApiItem<MaterialReception>>('/api/materiales/recepciones', {
        method: 'POST',
        body: JSON.stringify(payload),
      })).data;
    },

    async confirm(id: string, operationId: string, knownVersion: number): Promise<MaterialReception> {
      return (await request<ApiItem<MaterialReception>>(
        `/api/materiales/recepciones/${encodeURIComponent(id)}/confirmar`,
        {
          method: 'POST',
          body: JSON.stringify({
            operacion_id: operationId,
            version_conocida: knownVersion,
          }),
        },
      )).data;
    },

    async annul(id: string, operationId: string, reason: string): Promise<MaterialReception> {
      return (await request<ApiItem<MaterialReception>>(
        `/api/materiales/recepciones/${encodeURIComponent(id)}/anular`,
        {
          method: 'POST',
          body: JSON.stringify({ operacion_id: operationId, motivo: reason }),
        },
      )).data;
    },

    async pendingFolios(): Promise<PendingReceptionFolio[]> {
      return (await request<ApiList<PendingReceptionFolio>>(
        '/api/materiales/recepciones/folios-pendientes?limit=1000',
      )).data;
    },
  };
}

function validationMessage(data: unknown, fallback: string): string {
  if (!data || typeof data !== 'object') return fallback;

  const response = data as {
    message?: string;
    codigo?: string;
    errors?: Record<string, string[]>;
  };
  const firstValidationMessage = response.errors
    ? Object.values(response.errors).flat()[0]
    : null;

  return firstValidationMessage ?? response.message ?? fallback;
}
