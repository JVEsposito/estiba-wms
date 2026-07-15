import {
  ApiItem,
  ApiList,
  ApiMode,
  AuthSession,
  CameraPlan,
  CameraSummary,
  CreateMaterialDispatchPayload,
  LocatePayload,
  LoginPayload,
  Movement,
  MaterialCatalog,
  MaterialDispatch,
  MovePayload,
  OpenedSession,
  SagCondition,
  WithdrawMaterialPayload,
} from '../domain/estiba';
import { ApiError } from './apiError';
import { normalizeApiBaseUrl } from './apiConfiguration';
import { DemoEstibaApi } from './estibaApiDemo';

export interface EstibaApi {
  readonly mode: ApiMode;
  readonly baseUrl: string | null;
  readonly configurationError: string | null;
  login(payload: LoginPayload): Promise<AuthSession>;
  logout(token: string): Promise<void>;
  listCameras(token: string): Promise<CameraSummary[]>;
  listConditions(token: string): Promise<SagCondition[]>;
  getPlan(token: string, cameraId: string): Promise<CameraPlan>;
  listRecent(token: string, cameraId: string): Promise<Movement[]>;
  openSession(token: string, cameraId: string): Promise<OpenedSession>;
  closeSession(token: string, sessionId: string): Promise<void>;
  locate(token: string, payload: LocatePayload): Promise<void>;
  move(token: string, payload: MovePayload): Promise<void>;
  getMaterialCatalog(token: string): Promise<MaterialCatalog>;
  listMaterialDispatches(token: string): Promise<MaterialDispatch[]>;
  createMaterialDispatch(token: string, payload: CreateMaterialDispatchPayload): Promise<MaterialDispatch>;
  withdrawMaterial(token: string, dispatchId: string, payload: WithdrawMaterialPayload): Promise<MaterialDispatch>;
}

function validationMessage(data: unknown, fallback: string) {
  if (!data || typeof data !== 'object') return fallback;

  const response = data as { message?: string; errors?: Record<string, string[]> };
  const firstValidationMessage = response.errors
    ? Object.values(response.errors).flat()[0]
    : null;

  return firstValidationMessage ?? response.message ?? fallback;
}

class HttpEstibaApi implements EstibaApi {
  readonly mode = 'connected' as const;
  readonly configurationError = null;

  constructor(public readonly baseUrl: string) {}

  private async request<T>(path: string, token?: string, init: RequestInit = {}): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');

    if (token) headers.set('Authorization', `Bearer ${token}`);
    if (init.body) headers.set('Content-Type', 'application/json');

    let response: Response;

    try {
      response = await fetch(`${this.baseUrl}${path}`, { ...init, headers });
    } catch {
      throw new ApiError(
        `No fue posible conectar con ${this.baseUrl}. Revisa la IP, Laravel y el firewall.`,
        0,
      );
    }

    const data = response.status === 204
      ? null
      : await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new ApiError(
        validationMessage(data, 'La operación no pudo completarse.'),
        response.status,
        data,
      );
    }

    return data as T;
  }

  login(payload: LoginPayload) {
    return this.request<AuthSession>('/api/acceso-tablet', undefined, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async logout(token: string) {
    await this.request<null>('/api/acceso-tablet', token, { method: 'DELETE' });
  }

  async listCameras(token: string) {
    return (await this.request<ApiList<CameraSummary>>('/api/camaras', token)).data;
  }

  async listConditions(token: string) {
    return (await this.request<ApiList<SagCondition>>('/api/condiciones-sag', token)).data;
  }

  async getPlan(token: string, cameraId: string) {
    return (await this.request<ApiItem<CameraPlan>>(`/api/camaras/${cameraId}/plano`, token)).data;
  }

  async listRecent(token: string, cameraId: string) {
    const path = `/api/movimientos/recientes?camara_id=${encodeURIComponent(cameraId)}&limite=8`;
    return (await this.request<ApiList<Movement>>(path, token)).data;
  }

  async openSession(token: string, cameraId: string) {
    return (await this.request<ApiItem<OpenedSession>>(
      `/api/camaras/${cameraId}/sesiones`,
      token,
      { method: 'POST' },
    )).data;
  }

  async closeSession(token: string, sessionId: string) {
    await this.request(`/api/sesiones/${sessionId}/cerrar`, token, {
      method: 'POST',
      body: JSON.stringify({ motivo: 'Cierre desde aplicación Expo' }),
    });
  }

  async locate(token: string, payload: LocatePayload) {
    await this.request('/api/movimientos/ubicar', token, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async move(token: string, payload: MovePayload) {
    await this.request('/api/movimientos/mover', token, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  getMaterialCatalog(token: string) {
    return this.request<MaterialCatalog>('/api/materiales/catalogo', token);
  }

  async listMaterialDispatches(token: string) {
    const response = await this.request<ApiList<MaterialDispatch>>(
      '/api/materiales/despachos?estados=pendiente,parcial',
      token,
    );
    return response.data;
  }

  async createMaterialDispatch(token: string, payload: CreateMaterialDispatchPayload) {
    return (await this.request<ApiItem<MaterialDispatch>>('/api/materiales/despachos', token, {
      method: 'POST',
      body: JSON.stringify(payload),
    })).data;
  }

  async withdrawMaterial(token: string, dispatchId: string, payload: WithdrawMaterialPayload) {
    return (await this.request<ApiItem<MaterialDispatch>>(
      `/api/materiales/despachos/${dispatchId}/retirar`,
      token,
      { method: 'POST', body: JSON.stringify(payload) },
    )).data;
  }
}

export function createEstibaApi(
  runtimeUrl: string | null = process.env.EXPO_PUBLIC_API_URL?.trim() || null,
): EstibaApi {
  const demoEnabled = process.env.EXPO_PUBLIC_DEMO_MODE?.trim().toLowerCase() === 'true';

  if (demoEnabled) return new DemoEstibaApi();

  if (!runtimeUrl) {
    return createUnavailableApi(
      'La API no está configurada. Abre Configurar servidor e ingresa la IP de Laravel.',
    );
  }

  let configuredUrl: string;
  try {
    configuredUrl = normalizeApiBaseUrl(runtimeUrl);
  } catch {
    return createUnavailableApi(
      'La dirección configurada no es válida. Usa, por ejemplo, 192.168.1.100:8000.',
    );
  }

  return new HttpEstibaApi(configuredUrl);
}

function createUnavailableApi(message: string): EstibaApi {
  const unavailable = async (): Promise<never> => {
    throw new ApiError(message, 0);
  };

  return {
    mode: 'unconfigured',
    baseUrl: null,
    configurationError: message,
    login: unavailable,
    logout: unavailable,
    listCameras: unavailable,
    listConditions: unavailable,
    getPlan: unavailable,
    listRecent: unavailable,
    openSession: unavailable,
    closeSession: unavailable,
    locate: unavailable,
    move: unavailable,
    getMaterialCatalog: unavailable,
    listMaterialDispatches: unavailable,
    createMaterialDispatch: unavailable,
    withdrawMaterial: unavailable,
  };
}
