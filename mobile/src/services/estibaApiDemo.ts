import {
  AuthSession,
  CameraPlan,
  CameraSummary,
  CreateMaterialDispatchPayload,
  Dock,
  EditSession,
  ExtractionPlan,
  Folio,
  LocatePayload,
  LoginPayload,
  Movement,
  MaterialCatalog,
  MaterialDispatch,
  MovePayload,
  OpenedSession,
  OperationalNotification,
  OperationalNotificationFeed,
  Position,
  RefrigeratedLoad,
  SagCondition,
  WithdrawMaterialPayload,
} from '../domain/estiba';
import { ApiError } from './apiError';
import type { EstibaApi } from './estibaApi';

const conditions: SagCondition[] = [
  { id: 'sag-apta', codigo: 'APTA', nombre: 'Apta para exportación' },
  { id: 'sag-pendiente', codigo: 'PENDIENTE', nombre: 'Pendiente de inspección' },
  { id: 'sag-observada', codigo: 'OBSERVADA', nombre: 'Con observación SAG' },
];

const demoIdentity: AuthSession = {
  token: `local-session-${Date.now()}-${Math.random().toString(36).slice(2)}`,
  token_type: 'Bearer',
  usuario: {
    id: 'user-demo',
    nombre: 'Operador de prueba',
    email: 'operador@demo.invalid',
    rol: 'camarero_frio',
    ambito_camaras: 'productos',
    capacidades: {
      ambito_camaras: 'productos',
      puede_supervisar: false,
      puede_operar_productos: true,
      puede_operar_materiales: false,
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
    codigo: 'DEMO-LOCAL',
    nombre: 'Tablet cámara 01',
  },
};

function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}

function createFolio(index: number, type: 'pallet' | 'saldo' = 'pallet'): Folio {
  return {
    id: `folio-${index}`,
    numero_folio: `FOL-${String(1188 + index * 137).padStart(4, '0')}`,
    tipo_bulto: type,
    estado_operacional: 'en_camara',
    condicion_sag: conditions[index % conditions.length],
    fecha_ingreso: new Date().toISOString(),
    variedad: ['Santina', 'Lapins', 'Regina'][index % 3],
    calibre: ['2J', '3J', 'J'][index % 3],
    marca: 'Demo Frío',
    exportadora: 'Exportadora Demo',
    material: null,
    ubicado_at: new Date().toISOString(),
  };
}

function createPositions(cameraId: string, occupiedIndexes: number[]): Position[] {
  let index = 0;
  const positions: Position[] = [];

  for (const level of [1, 2]) {
    for (const band of [1, 2, 3]) {
      for (const position of [1, 2, 3, 4]) {
        const occupied = occupiedIndexes.includes(index);
        positions.push({
          id: `${cameraId}-B${band}-P${position}-N${level}`,
          banda: band,
          posicion: position,
          nivel: level,
          etiqueta: `B${String(band).padStart(2, '0')}-P${String(position).padStart(2, '0')}-N${level}`,
          estado: index === 22 ? 'bloqueada' : 'activa',
          ocupada: occupied,
          folio: occupied ? createFolio(index, index % 5 === 0 ? 'saldo' : 'pallet') : null,
        });
        index += 1;
      }
    }
  }

  return positions;
}

function ownEditSession(cameraId: string): EditSession {
  const now = new Date().toISOString();
  return {
    id: `session-${cameraId}`,
    es_propia: true,
    usuario: { id: demoIdentity.usuario.id, nombre: demoIdentity.usuario.nombre },
    dispositivo: { id: demoIdentity.dispositivo.id, nombre: demoIdentity.dispositivo.nombre },
    iniciada_at: now,
    ultima_actividad_at: now,
  };
}

function otherEditSession(cameraId: string): EditSession {
  const now = new Date().toISOString();
  return {
    id: `session-other-${cameraId}`,
    es_propia: false,
    usuario: { id: 'user-maria', nombre: 'María P.' },
    dispositivo: { id: 'tablet-02', nombre: 'Tablet cámara 02' },
    iniciada_at: now,
    ultima_actividad_at: now,
  };
}

function createPlan(
  id: string,
  code: string,
  name: string,
  occupied: number[],
  locked = false,
): CameraPlan {
  const plan: CameraPlan = {
    id,
    codigo: code,
    nombre: name,
    tipo: code.startsWith('DES') ? 'despacho' : 'transito',
    contenido: 'productos',
    estado: 'activa',
    version_plano: 3,
    ocupacion: { ocupadas: 0, total: 0, porcentaje: 0 },
    acceso: locked
      ? { modo: 'solo_lectura', bloqueada: true, sesion: otherEditSession(id) }
      : { modo: 'disponible', bloqueada: false, sesion: null },
    posiciones: createPositions(id, occupied),
  };

  return syncOccupancy(plan);
}

function syncOccupancy(plan: CameraPlan) {
  const total = plan.posiciones.length;
  const occupied = plan.posiciones.filter((position) => position.ocupada).length;
  plan.ocupacion = {
    ocupadas: occupied,
    total,
    porcentaje: total === 0 ? 0 : Math.round((occupied / total) * 1000) / 10,
  };
  return plan;
}

function summary(plan: CameraPlan): CameraSummary {
  const { posiciones: _positions, ...camera } = plan;
  return clone(camera);
}

function movementEnd(plan: CameraPlan, position: Position) {
  return {
    camara: { id: plan.id, codigo: plan.codigo, nombre: plan.nombre },
    posicion: {
      id: position.id,
      banda: position.banda,
      posicion: position.posicion,
      nivel: position.nivel,
      etiqueta: position.etiqueta,
    },
    version_anterior: Math.max(0, plan.version_plano - 1),
    version_resultante: plan.version_plano,
  };
}

export class DemoEstibaApi implements EstibaApi {
  readonly mode = 'demo' as const;
  readonly baseUrl = null;
  readonly configurationError = null;
  private plans = [
    createPlan('camera-01', 'CAM-01', 'Cámara de tránsito 01', [0, 2, 5, 7, 9, 13, 16]),
    createPlan('camera-02', 'CAM-02', 'Cámara de tránsito 02', [1, 3, 4, 8, 10, 12, 15, 18], true),
    createPlan('dispatch-01', 'DES-01', 'Zona de despacho', [6, 11]),
  ];
  private movements: Movement[] = [];

  constructor() {
    const firstPlan = this.plans[0];
    const occupied = firstPlan.posiciones.filter((position) => position.folio).slice(0, 3);
    this.movements = occupied.map((position, index) => ({
      id: `movement-${index}`,
      operacion_id: `operation-${index}`,
      tipo_movimiento: 'ubicacion_inicial',
      folio: {
        id: position.folio!.id,
        numero_folio: position.folio!.numero_folio,
        tipo_bulto: position.folio!.tipo_bulto,
      },
      origen: null,
      destino: movementEnd(firstPlan, position),
      usuario: { id: demoIdentity.usuario.id, nombre: demoIdentity.usuario.nombre },
      generado_dispositivo_at: new Date(Date.now() - index * 300000).toISOString(),
      recibido_servidor_at: new Date(Date.now() - index * 300000).toISOString(),
      created_at: new Date(Date.now() - index * 300000).toISOString(),
    }));
  }

  async login(payload: LoginPayload) {
    if (!payload.email || !payload.password || !payload.codigo_dispositivo) {
      throw new ApiError('Completa las credenciales y el código de tablet.', 422);
    }
    return clone(demoIdentity);
  }

  async logout() {}

  async listCameras() {
    return this.plans.map(summary);
  }

  async listConditions() {
    return clone(conditions);
  }

  async getMaterialCatalog(): Promise<MaterialCatalog> {
    return { items: [], destinos: [] };
  }

  async listMaterialDispatches(
    _token: string,
    _states?: MaterialDispatch['estado'][],
  ): Promise<MaterialDispatch[]> {
    return [];
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

  async getPlan(_token: string, cameraId: string) {
    const plan = this.findPlan(cameraId);
    return clone(syncOccupancy(plan));
  }

  async listRecent(_token: string, cameraId: string) {
    return clone(this.movements.filter((movement) => (
      movement.origen?.camara.id === cameraId || movement.destino?.camara.id === cameraId
    )));
  }

  async openSession(_token: string, cameraId: string): Promise<OpenedSession> {
    const plan = this.findPlan(cameraId);
    if (plan.acceso.modo === 'solo_lectura') {
      throw new ApiError('La cámara está siendo editada por otro operador.', 409);
    }
    const session = ownEditSession(cameraId);
    plan.acceso = { modo: 'edicion', bloqueada: true, sesion: session };

    return {
      id: session.id,
      camara_id: cameraId,
      estado: 'activa',
      version_inicial: plan.version_plano,
      version_final: null,
      iniciada_at: session.iniciada_at,
      ultima_actividad_at: session.ultima_actividad_at,
      cerrada_at: null,
      motivo_cierre: null,
      usuario: session.usuario,
      dispositivo: session.dispositivo,
    };
  }

  async closeSession(_token: string, sessionId: string) {
    const plan = this.plans.find((candidate) => candidate.acceso.sesion?.id === sessionId);
    if (!plan) throw new ApiError('La sesión ya no está activa.', 409);
    plan.acceso = { modo: 'disponible', bloqueada: false, sesion: null };
  }

  async locate(_token: string, payload: LocatePayload) {
    const destination = this.findPosition(payload.posicion_destino_id);
    this.assertOwnSession(destination.plan, payload.sesion_destino_id);
    if (destination.position.ocupada || destination.position.estado !== 'activa') {
      throw new ApiError('La posición de destino ya no está disponible.', 409);
    }

    const condition = conditions.find((item) => item.id === payload.datos_folio?.condicion_sag_id) ?? null;
    const folio: Folio = {
      id: `folio-${Date.now()}`,
      numero_folio: payload.numero_folio,
      tipo_bulto: payload.tipo_bulto,
      estado_operacional: 'en_camara',
      condicion_sag: condition,
      fecha_ingreso: new Date().toISOString(),
      variedad: payload.datos_folio?.variedad ?? null,
      calibre: payload.datos_folio?.calibre ?? null,
      marca: payload.datos_folio?.marca ?? null,
      exportadora: payload.datos_folio?.exportadora ?? null,
      material: null,
      ubicado_at: new Date().toISOString(),
    };
    destination.position.ocupada = true;
    destination.position.folio = folio;
    destination.plan.version_plano += 1;
    syncOccupancy(destination.plan);
    this.movements.unshift(this.buildMovement(
      payload.operacion_id,
      'ubicacion_inicial',
      folio,
      null,
      movementEnd(destination.plan, destination.position),
    ));
  }

  async move(_token: string, payload: MovePayload) {
    const origin = this.plans
      .flatMap((plan) => plan.posiciones.map((position) => ({ plan, position })))
      .find((item) => item.position.folio?.id === payload.folio_id);
    const destination = this.findPosition(payload.posicion_destino_id);

    if (!origin?.position.folio) throw new ApiError('El folio ya no está en el origen indicado.', 409);
    this.assertOwnSession(origin.plan, payload.sesion_origen_id);
    this.assertOwnSession(destination.plan, payload.sesion_destino_id);
    if (destination.position.ocupada || destination.position.estado !== 'activa') {
      throw new ApiError('La posición de destino ya no está disponible.', 409);
    }

    const folio = origin.position.folio;
    const originEnd = movementEnd(origin.plan, origin.position);
    origin.position.ocupada = false;
    origin.position.folio = null;
    destination.position.ocupada = true;
    destination.position.folio = folio;
    origin.plan.version_plano += 1;
    if (origin.plan.id !== destination.plan.id) destination.plan.version_plano += 1;
    syncOccupancy(origin.plan);
    syncOccupancy(destination.plan);
    this.movements.unshift(this.buildMovement(
      payload.operacion_id,
      origin.plan.id === destination.plan.id ? 'reubicacion' : 'traslado_entre_camaras',
      folio,
      originEnd,
      movementEnd(destination.plan, destination.position),
    ));
  }

  private findPlan(cameraId: string) {
    const plan = this.plans.find((candidate) => candidate.id === cameraId);
    if (!plan) throw new ApiError('La cámara no existe.', 404);
    return plan;
  }

  private findPosition(positionId: string) {
    for (const plan of this.plans) {
      const position = plan.posiciones.find((candidate) => candidate.id === positionId);
      if (position) return { plan, position };
    }
    throw new ApiError('La posición no existe.', 404);
  }

  private assertOwnSession(plan: CameraPlan, sessionId: string) {
    if (plan.acceso.modo !== 'edicion'
      || !plan.acceso.sesion?.es_propia
      || plan.acceso.sesion.id !== sessionId) {
      throw new ApiError(`No tienes una sesión activa en ${plan.codigo}.`, 409);
    }
  }

  private buildMovement(
    operationId: string,
    type: Movement['tipo_movimiento'],
    folio: Folio,
    origin: Movement['origen'],
    destination: Movement['destino'],
  ): Movement {
    const now = new Date().toISOString();
    return {
      id: `movement-${Date.now()}`,
      operacion_id: operationId,
      tipo_movimiento: type,
      folio: { id: folio.id, numero_folio: folio.numero_folio, tipo_bulto: folio.tipo_bulto },
      origen: origin,
      destino: destination,
      usuario: { id: demoIdentity.usuario.id, nombre: demoIdentity.usuario.nombre },
      generado_dispositivo_at: now,
      recibido_servidor_at: now,
      created_at: now,
    };
  }
}
