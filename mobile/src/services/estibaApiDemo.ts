import {
  AuthSession,
  CreateMaterialDispatchPayload,
  Dock,
  ExtractionPlan,
  LocatePayload,
  LoginPayload,
  MaterialCatalog,
  MaterialDispatch,
  MaterialDispatchSummary,
  MovePayload,
  OperationalNotification,
  OperationalNotificationFeed,
  RefrigeratedLoad,
  WithdrawMaterialPayload,
} from '../domain/estiba';
import {
  CloseMaterialTransformationLotPayload,
  CloseMaterialTransformationOrderPayload,
  MaterialTransformationOrder,
  MaterialTransformationOrderSummary,
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
import {
  closeDemoSession,
  getDemoPlan,
  listDemoCameras,
  listDemoRecentMovements,
  locateDemoFolio,
  lookupDemoFolio,
  moveDemoFolio,
  openDemoSession,
} from '../demo/demoOperationalEngine';
import { demoSagConditions } from '../demo/demoOperationalSeed';
import { ApiError } from './apiError';
import type { EstibaApi } from './estibaApi';

const demoIdentity: AuthSession = {
  token: `local-session-${Date.now()}-${Math.random().toString(36).slice(2)}`,
  token_type: 'Bearer',
  usuario: {
    id: 'user-demo',
    nombre: 'Administrador Demo',
    email: 'administrador@estiba.demo',
    rol: 'administrador',
    ambito_camaras: 'ambos',
    modulos_tablet: ['demo_administracion', 'operacion'],
    capacidades: {
      modulos_tablet: ['demo_administracion', 'operacion'],
      ambito_camaras: 'ambos',
      puede_supervisar: true,
      puede_operar_productos: true,
      puede_operar_materiales: true,
      puede_consultar_cargas: true,
      puede_consultar_catalogo_cargas: false,
      puede_gestionar_cargas: false,
      puede_gestionar_andenes: false,
      puede_consultar_despachos_materiales: false,
      puede_gestionar_despachos_materiales: false,
      puede_retirar_materiales: false,
      puede_cancelar_despachos_materiales: false,
      puede_consultar_kardex_materiales: false,
      puede_validar_pallets: false,
      puede_rechazar_pallets: false,
      puede_consultar_validaciones_pallet: false,
    },
  },
  dispositivo: {
    id: 'device-demo',
    codigo: 'DEMO-01',
    nombre: 'Tablet autónoma Demo',
  },
};

function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}

export class DemoEstibaApi implements EstibaApi {
  readonly mode = 'demo' as const;
  readonly baseUrl = null;
  readonly configurationError = null;

  async login(payload: LoginPayload) {
    if (!payload.email || !payload.password || !payload.codigo_dispositivo) {
      throw new ApiError('Completa las credenciales y el código de tablet.', 422);
    }
    return clone(demoIdentity);
  }

  async logout() {}

  async listCameras() {
    return listDemoCameras();
  }

  async listConditions() {
    return clone(demoSagConditions);
  }

  async getMaterialCatalog(): Promise<MaterialCatalog> {
    return { temporada: null, clientes: [], items: [], destinos: [] };
  }

  async listMaterialDispatches(
    _token: string,
    _states?: MaterialDispatch['estado'][],
  ): Promise<MaterialDispatch[]> {
    return [];
  }

  async listMaterialDispatchSummaries(
    _token: string,
    _states?: MaterialDispatch['estado'][],
  ): Promise<MaterialDispatchSummary[]> {
    return [];
  }

  async getMaterialDispatch(_token: string, _dispatchId: string): Promise<MaterialDispatch> {
    throw new ApiError('No existen despachos de materiales en modo demo.', 404);
  }

  async listRefrigeratedLoads(_token: string): Promise<RefrigeratedLoad[]> {
    return [];
  }

  async getExtractionPlan(_token: string, loadId: string): Promise<ExtractionPlan> {
    return {
      carga_id: loadId,
      carga_codigo: 'CAR-DEMO',
      generado_at: new Date().toISOString(),
      resumen: { pendientes: 0, planificables: 0, bloqueados: 0, sin_ubicacion: 0, con_incidencia: 0 },
      siguiente: null,
      items: [],
    };
  }

  async listDocks(_token: string): Promise<Dock[]> {
    return [{ id: 'dock-demo', codigo: 'AND-01', nombre: 'Andén 01', activo: true }];
  }

  async reportLoadIncident(): Promise<void> {}

  async sendLoadFolioToDock(): Promise<RefrigeratedLoad> {
    throw new ApiError('No existen cargas publicadas en el modo de demostración.', 422);
  }

  async listOperationalNotifications(): Promise<OperationalNotificationFeed> {
    return { items: [], unread: 0, syncedAt: new Date().toISOString() };
  }

  async getOperationalNotificationSummary() {
    return { unread: 0, syncedAt: new Date().toISOString() };
  }

  async readOperationalNotification(): Promise<OperationalNotification> {
    throw new ApiError('No existen notificaciones en el modo de demostración.', 404);
  }

  async confirmOperationalNotification(): Promise<OperationalNotification> {
    throw new ApiError('No existen notificaciones en el modo de demostración.', 404);
  }

  async createMaterialDispatch(_token: string, _payload: CreateMaterialDispatchPayload): Promise<MaterialDispatch> {
    throw new ApiError('El despacho de materiales no está disponible en modo demo.', 422);
  }

  async withdrawMaterial(_token: string, _dispatchId: string, _payload: WithdrawMaterialPayload): Promise<MaterialDispatch> {
    throw new ApiError('El despacho de materiales no está disponible en modo demo.', 422);
  }

  async listMaterialTransformations(): Promise<MaterialTransformationOrderSummary[]> {
    return [];
  }

  async getMaterialTransformation(
    _token: string,
    _orderId: string,
  ): Promise<MaterialTransformationOrder> {
    throw new ApiError('La transformación de materiales no está disponible en modo demo.', 422);
  }

  async startMaterialTransformation(
    _token: string,
    _orderId: string,
    _payload: StartMaterialTransformationPayload,
  ): Promise<MaterialTransformationOrder> {
    throw new ApiError('La transformación de materiales no está disponible en modo demo.', 422);
  }

  async openMaterialTransformationLot(
    _token: string,
    _orderId: string,
    _payload: OpenMaterialTransformationLotPayload,
  ): Promise<MaterialTransformationOrder> {
    throw new ApiError('La transformación de materiales no está disponible en modo demo.', 422);
  }

  async closeMaterialTransformationLot(
    _token: string,
    _lotId: string,
    _payload: CloseMaterialTransformationLotPayload,
  ): Promise<MaterialTransformationOrder> {
    throw new ApiError('La transformación de materiales no está disponible en modo demo.', 422);
  }

  async reverseMaterialTransformationLot(
    _token: string,
    _lotId: string,
    _payload: ReverseMaterialTransformationLotPayload,
  ): Promise<MaterialTransformationOrder> {
    throw new ApiError('La transformación de materiales no está disponible en modo demo.', 422);
  }

  async closeMaterialTransformationOrder(
    _token: string,
    _orderId: string,
    _payload: CloseMaterialTransformationOrderPayload,
  ): Promise<MaterialTransformationOrder> {
    throw new ApiError('La transformación de materiales no está disponible en modo demo.', 422);
  }

  async materialLabelProfiles(): Promise<LabelPrintProfile[]> {
    return [];
  }

  async materialTransformationPrintJobs(): Promise<MaterialPrintJob[]> {
    return [];
  }

  async generateMaterialTransformationLabels(
    _token: string,
    _orderId: string,
    _payload: GenerateMaterialLabelsPayload,
  ): Promise<{ jobId: string; zpl: string }> {
    throw new ApiError('La impresión directa requiere conexión con la API.', 422);
  }

  async reportMaterialTransformationPrintOutcome(
    _token: string,
    _jobId: string,
    _payload: MaterialPrintOutcomePayload,
  ): Promise<void> {
    throw new ApiError('La impresión directa requiere conexión con la API.', 422);
  }

  async getPlan(_token: string, cameraId: string) {
    return getDemoPlan(cameraId);
  }

  async refreshPlan(token: string, cameraId: string) {
    return this.getPlan(token, cameraId);
  }

  async listRecent(_token: string, cameraId: string) {
    return listDemoRecentMovements(cameraId);
  }

  async openSession(_token: string, cameraId: string) {
    return openDemoSession(cameraId);
  }

  async closeSession(_token: string, sessionId: string) {
    return closeDemoSession(sessionId);
  }

  async lookupFolio(_token: string, folioNumber: string) {
    return lookupDemoFolio(folioNumber);
  }

  async locate(_token: string, payload: LocatePayload) {
    return locateDemoFolio(payload);
  }

  async move(_token: string, payload: MovePayload) {
    return moveDemoFolio(payload);
  }
}
