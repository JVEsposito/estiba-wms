import {
  ApiItem,
  ApiList,
  ApiMode,
  AuthSession,
  CameraPlan,
  CameraSummary,
  CreateMaterialDispatchPayload,
  Dock,
  ExtractionPlan,
  FolioLookup,
  LocatePayload,
  LoginPayload,
  Movement,
  MaterialCatalog,
  MaterialDispatch,
  MovePayload,
  OpenedSession,
  OperationalNotification,
  OperationalNotificationFeed,
  RefrigeratedLoad,
  ReportLoadIncidentPayload,
  SagCondition,
  SendLoadFolioToDockPayload,
  WithdrawMaterialPayload,
} from '../domain/estiba';
import { ApiError } from './apiError';
import { normalizeApiBaseUrl } from './apiConfiguration';
import { DemoEstibaApi } from './estibaApiDemo';
import {
  CloseMaterialTransformationLotPayload,
  CloseMaterialTransformationOrderPayload,
  MaterialTransformationOrder,
  OpenMaterialTransformationLotPayload,
  ReverseMaterialTransformationLotPayload,
  StartMaterialTransformationPayload,
} from '../domain/materialTransformation';
import {
  GenerateMaterialLabelsPayload,
  LabelPrintProfile,
  MaterialPrintJob,
  MaterialPrintOutcomePayload,
} from '../domain/materialReception';

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
  lookupFolio(token: string, folioNumber: string): Promise<FolioLookup>;
  locate(token: string, payload: LocatePayload): Promise<void>;
  move(token: string, payload: MovePayload): Promise<void>;
  getMaterialCatalog(token: string): Promise<MaterialCatalog>;
  listMaterialDispatches(
    token: string,
    states?: MaterialDispatch['estado'][],
  ): Promise<MaterialDispatch[]>;
  createMaterialDispatch(token: string, payload: CreateMaterialDispatchPayload): Promise<MaterialDispatch>;
  withdrawMaterial(token: string, dispatchId: string, payload: WithdrawMaterialPayload): Promise<MaterialDispatch>;
  listMaterialTransformations(token: string): Promise<MaterialTransformationOrder[]>;
  startMaterialTransformation(token: string, orderId: string, payload: StartMaterialTransformationPayload): Promise<MaterialTransformationOrder>;
  openMaterialTransformationLot(token: string, orderId: string, payload: OpenMaterialTransformationLotPayload): Promise<MaterialTransformationOrder>;
  closeMaterialTransformationLot(token: string, lotId: string, payload: CloseMaterialTransformationLotPayload): Promise<MaterialTransformationOrder>;
  reverseMaterialTransformationLot(token: string, lotId: string, payload: ReverseMaterialTransformationLotPayload): Promise<MaterialTransformationOrder>;
  closeMaterialTransformationOrder(token: string, orderId: string, payload: CloseMaterialTransformationOrderPayload): Promise<MaterialTransformationOrder>;
  materialLabelProfiles(token: string): Promise<LabelPrintProfile[]>;
  materialTransformationPrintJobs(token: string, orderId: string): Promise<MaterialPrintJob[]>;
  generateMaterialTransformationLabels(token: string, orderId: string, payload: GenerateMaterialLabelsPayload): Promise<{ jobId: string; zpl: string }>;
  reportMaterialTransformationPrintOutcome(token: string, jobId: string, payload: MaterialPrintOutcomePayload): Promise<void>;
  listRefrigeratedLoads(token: string): Promise<RefrigeratedLoad[]>;
  getExtractionPlan(token: string, loadId: string): Promise<ExtractionPlan>;
  listDocks(token: string): Promise<Dock[]>;
  reportLoadIncident(token: string, assignmentId: string, payload: ReportLoadIncidentPayload): Promise<void>;
  sendLoadFolioToDock(token: string, assignmentId: string, payload: SendLoadFolioToDockPayload): Promise<RefrigeratedLoad>;
  listOperationalNotifications(token: string): Promise<OperationalNotificationFeed>;
  readOperationalNotification(token: string, notificationId: string): Promise<OperationalNotification>;
  confirmOperationalNotification(token: string, notificationId: string): Promise<OperationalNotification>;
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

  async lookupFolio(token: string, folioNumber: string) {
    const path = `/api/movimientos/consultar-folio?numero_folio=${encodeURIComponent(folioNumber)}`;
    return (await this.request<ApiItem<FolioLookup>>(path, token)).data;
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

  async listMaterialDispatches(
    token: string,
    states: MaterialDispatch['estado'][] = ['pendiente', 'parcial'],
  ) {
    const stateFilter = encodeURIComponent(states.join(','));
    const response = await this.request<ApiList<MaterialDispatch>>(
      `/api/materiales/despachos?estados=${stateFilter}`,
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

  async listMaterialTransformations(token: string) {
    return (await this.request<ApiList<MaterialTransformationOrder>>(
      '/api/materiales/transformaciones/ordenes?per_page=100',
      token,
    )).data;
  }

  async startMaterialTransformation(
    token: string,
    orderId: string,
    payload: StartMaterialTransformationPayload,
  ) {
    return (await this.request<ApiItem<MaterialTransformationOrder>>(
      `/api/materiales/transformaciones/ordenes/${encodeURIComponent(orderId)}/iniciar`,
      token,
      { method: 'POST', body: JSON.stringify(payload) },
    )).data;
  }

  async openMaterialTransformationLot(
    token: string,
    orderId: string,
    payload: OpenMaterialTransformationLotPayload,
  ) {
    return (await this.request<ApiItem<MaterialTransformationOrder>>(
      `/api/materiales/transformaciones/ordenes/${encodeURIComponent(orderId)}/lotes`,
      token,
      { method: 'POST', body: JSON.stringify(payload) },
    )).data;
  }

  async closeMaterialTransformationLot(
    token: string,
    lotId: string,
    payload: CloseMaterialTransformationLotPayload,
  ) {
    return (await this.request<ApiItem<MaterialTransformationOrder>>(
      `/api/materiales/transformaciones/lotes/${encodeURIComponent(lotId)}/cerrar`,
      token,
      { method: 'POST', body: JSON.stringify(payload) },
    )).data;
  }

  async reverseMaterialTransformationLot(
    token: string,
    lotId: string,
    payload: ReverseMaterialTransformationLotPayload,
  ) {
    return (await this.request<ApiItem<MaterialTransformationOrder>>(
      `/api/materiales/transformaciones/lotes/${encodeURIComponent(lotId)}/revertir`,
      token,
      { method: 'POST', body: JSON.stringify(payload) },
    )).data;
  }

  async closeMaterialTransformationOrder(
    token: string,
    orderId: string,
    payload: CloseMaterialTransformationOrderPayload,
  ) {
    return (await this.request<ApiItem<MaterialTransformationOrder>>(
      `/api/materiales/transformaciones/ordenes/${encodeURIComponent(orderId)}/cerrar`,
      token,
      { method: 'POST', body: JSON.stringify(payload) },
    )).data;
  }

  async materialLabelProfiles(token: string) {
    return (await this.request<ApiList<LabelPrintProfile>>(
      '/api/materiales/recepciones/perfiles-impresion',
      token,
    )).data;
  }

  async materialTransformationPrintJobs(token: string, orderId: string) {
    return (await this.request<ApiList<MaterialPrintJob>>(
      `/api/materiales/transformaciones/ordenes/${encodeURIComponent(orderId)}/impresiones`,
      token,
    )).data;
  }

  async generateMaterialTransformationLabels(
    token: string,
    orderId: string,
    payload: GenerateMaterialLabelsPayload,
  ) {
    let response: Response;
    try {
      response = await fetch(
        `${this.baseUrl}/api/materiales/transformaciones/ordenes/${encodeURIComponent(orderId)}/etiquetas`,
        {
          method: 'POST',
          headers: {
            Accept: 'application/zpl',
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(payload),
        },
      );
    } catch {
      throw new ApiError('No fue posible preparar la etiqueta de transformación.', 0);
    }
    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      throw new ApiError(
        validationMessage(data, 'No fue posible preparar la etiqueta de transformación.'),
        response.status,
        data,
      );
    }
    const jobId = response.headers.get('X-Estiba-Print-Job');
    if (!jobId) {
      throw new ApiError('La API no devolvió el identificador auditable de impresión.', 500);
    }

    return { jobId, zpl: await response.text() };
  }

  async reportMaterialTransformationPrintOutcome(
    token: string,
    jobId: string,
    payload: MaterialPrintOutcomePayload,
  ) {
    await this.request(
      `/api/materiales/transformaciones/trabajos-impresion/${encodeURIComponent(jobId)}/resultado`,
      token,
      { method: 'POST', body: JSON.stringify(payload) },
    );
  }

  async listRefrigeratedLoads(token: string) {
    return (await this.request<ApiList<RefrigeratedLoad>>('/api/cargas/pendientes', token)).data;
  }

  async getExtractionPlan(token: string, loadId: string) {
    return (await this.request<ApiItem<ExtractionPlan>>(
      `/api/cargas/${loadId}/plan-extraccion`,
      token,
    )).data;
  }

  async listDocks(token: string) {
    return (await this.request<ApiList<Dock>>('/api/andenes', token)).data;
  }

  async reportLoadIncident(
    token: string,
    assignmentId: string,
    payload: ReportLoadIncidentPayload,
  ) {
    await this.request(`/api/cargas/asignaciones/${assignmentId}/incidencias`, token, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async sendLoadFolioToDock(
    token: string,
    assignmentId: string,
    payload: SendLoadFolioToDockPayload,
  ) {
    return (await this.request<ApiItem<RefrigeratedLoad>>(
      `/api/cargas/asignaciones/${assignmentId}/enviar-anden`,
      token,
      { method: 'POST', body: JSON.stringify(payload) },
    )).data;
  }

  async listOperationalNotifications(token: string): Promise<OperationalNotificationFeed> {
    const response = await this.request<{
      data: OperationalNotification[];
      resumen: { no_leidas: number; sincronizado_at: string };
    }>('/api/notificaciones-operacionales?per_page=50', token);
    return {
      items: response.data,
      unread: response.resumen.no_leidas,
      syncedAt: response.resumen.sincronizado_at,
    };
  }

  async readOperationalNotification(token: string, notificationId: string) {
    return (await this.request<ApiItem<OperationalNotification>>(
      `/api/notificaciones-operacionales/${notificationId}/leer`,
      token,
      { method: 'POST' },
    )).data;
  }

  async confirmOperationalNotification(token: string, notificationId: string) {
    return (await this.request<ApiItem<OperationalNotification>>(
      `/api/notificaciones-operacionales/${notificationId}/confirmar`,
      token,
      { method: 'POST' },
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
    lookupFolio: unavailable,
    locate: unavailable,
    move: unavailable,
    getMaterialCatalog: unavailable,
    listMaterialDispatches: unavailable,
    createMaterialDispatch: unavailable,
    withdrawMaterial: unavailable,
    listMaterialTransformations: unavailable,
    startMaterialTransformation: unavailable,
    openMaterialTransformationLot: unavailable,
    closeMaterialTransformationLot: unavailable,
    reverseMaterialTransformationLot: unavailable,
    closeMaterialTransformationOrder: unavailable,
    materialLabelProfiles: unavailable,
    materialTransformationPrintJobs: unavailable,
    generateMaterialTransformationLabels: unavailable,
    reportMaterialTransformationPrintOutcome: unavailable,
    listRefrigeratedLoads: unavailable,
    getExtractionPlan: unavailable,
    listDocks: unavailable,
    reportLoadIncident: unavailable,
    sendLoadFolioToDock: unavailable,
    listOperationalNotifications: unavailable,
    readOperationalNotification: unavailable,
    confirmOperationalNotification: unavailable,
  };
}
