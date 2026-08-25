import * as Crypto from 'expo-crypto';

import {
  CameraPlan,
  CameraSummary,
  EditSession,
  Folio,
  FolioLookup,
  LocatePayload,
  Movement,
  MovePayload,
  OpenedSession,
  Position,
} from '../domain/estiba';
import { cameraDisplayName } from '../domain/cameras';
import { ApiError } from '../services/apiError';
import {
  DemoDatabaseExecutor,
  openDemoDatabase,
  writeDemoAudit,
} from './demoDatabase';
import {
  createInitialOperationalState,
  demoDevice,
  demoMovementEnd,
  demoOperator,
  DEMO_OPERATIONAL_STATE_KEY,
  DemoOperationalState,
  demoSagConditions,
  syncDemoOccupancy,
} from './demoOperationalSeed';

type OperationalStateRow = { state_json: string };

type CatalogFolioRow = {
  id: string;
  number: string;
  species: string;
  variety: string;
  status: string;
  created_at: string;
  client_name: string;
};

function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}

function parseState(serialized: string): DemoOperationalState {
  try {
    const state = JSON.parse(serialized) as Partial<DemoOperationalState>;
    if (!Array.isArray(state.plans) || !Array.isArray(state.movements)) throw new Error();
    return {
      schemaVersion: 1,
      plans: state.plans,
      movements: state.movements,
      operationFingerprints: state.operationFingerprints ?? {},
    };
  } catch {
    throw new ApiError(
      'La base operativa Demo no pudo leerse. Restaura el escenario desde Administración Demo.',
      500,
    );
  }
}

async function stateFrom(executor: DemoDatabaseExecutor): Promise<DemoOperationalState> {
  let row = await executor.getFirstAsync<OperationalStateRow>(
    'SELECT state_json FROM demo_operational_state WHERE key = ?',
    DEMO_OPERATIONAL_STATE_KEY,
  );

  if (!row) {
    const initial = createInitialOperationalState();
    await executor.runAsync(
      `INSERT OR IGNORE INTO demo_operational_state (key, state_json, updated_at)
       VALUES (?, ?, ?)`,
      DEMO_OPERATIONAL_STATE_KEY,
      JSON.stringify(initial),
      new Date().toISOString(),
    );
    row = await executor.getFirstAsync<OperationalStateRow>(
      'SELECT state_json FROM demo_operational_state WHERE key = ?',
      DEMO_OPERATIONAL_STATE_KEY,
    );
  }

  if (!row) throw new ApiError('No fue posible preparar la operación local.', 500);
  return parseState(row.state_json);
}

async function readState(): Promise<DemoOperationalState> {
  const db = await openDemoDatabase();
  return stateFrom(db);
}

async function mutateState<T>(
  mutation: (state: DemoOperationalState, transaction: DemoDatabaseExecutor) => Promise<T>,
): Promise<T> {
  const db = await openDemoDatabase();
  let result: T | undefined;

  await db.withExclusiveTransactionAsync(async (transaction) => {
    const state = await stateFrom(transaction);
    result = await mutation(state, transaction);
    await transaction.runAsync(
      `UPDATE demo_operational_state
       SET state_json = ?, updated_at = ?
       WHERE key = ?`,
      JSON.stringify(state),
      new Date().toISOString(),
      DEMO_OPERATIONAL_STATE_KEY,
    );
  });

  return result as T;
}

function summary(plan: CameraPlan): CameraSummary {
  const { posiciones: _positions, folios_sin_posicion: _unlocated, ...camera } = plan;
  return clone(camera);
}

function findPlan(state: DemoOperationalState, cameraId: string): CameraPlan {
  const plan = state.plans.find((candidate) => candidate.id === cameraId);
  if (!plan) throw new ApiError('La cámara no existe.', 404);
  return plan;
}

function findPosition(state: DemoOperationalState, positionId: string): { plan: CameraPlan; position: Position } {
  for (const plan of state.plans) {
    const position = plan.posiciones.find((candidate) => candidate.id === positionId);
    if (position) return { plan, position };
  }
  throw new ApiError('La posición no existe.', 404);
}

function findLocatedFolio(state: DemoOperationalState, number: string) {
  return state.plans
    .flatMap((plan) => plan.posiciones.map((position) => ({ plan, position })))
    .find(({ position }) => position.folio?.numero_folio === number);
}

function assertOwnSession(plan: CameraPlan, sessionId: string): void {
  if (plan.acceso.modo !== 'edicion'
    || !plan.acceso.sesion?.es_propia
    || plan.acceso.sesion.id !== sessionId) {
    throw new ApiError(`No tienes una sesión activa en ${cameraDisplayName(plan)}.`, 409);
  }
}

function assertVersion(plan: CameraPlan, knownVersion: number): void {
  if (plan.version_plano !== knownVersion) {
    throw new ApiError(
      `${cameraDisplayName(plan)} cambió desde la última lectura. Actualiza el plano antes de continuar.`,
      409,
    );
  }
}

function operationResult(
  state: DemoOperationalState,
  operationId: string,
  fingerprint: string,
): 'new' | 'repeat' {
  const previous = state.operationFingerprints[operationId];
  if (!previous) return 'new';
  if (previous === fingerprint) return 'repeat';
  throw new ApiError(
    'El identificador de operación ya fue utilizado con datos diferentes.',
    409,
  );
}

function openedSession(plan: CameraPlan, session: EditSession): OpenedSession {
  return {
    id: session.id,
    camara_id: plan.id,
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

function movement(
  operationId: string,
  type: Movement['tipo_movimiento'],
  folio: Folio,
  origin: Movement['origen'],
  destination: Movement['destino'],
  generatedAt: string,
): Movement {
  const receivedAt = new Date().toISOString();
  return {
    id: Crypto.randomUUID(),
    operacion_id: operationId,
    tipo_movimiento: type,
    folio: { id: folio.id, numero_folio: folio.numero_folio, tipo_bulto: folio.tipo_bulto },
    origen: origin,
    destino: destination,
    usuario: demoOperator,
    generado_dispositivo_at: generatedAt,
    recibido_servidor_at: receivedAt,
    created_at: receivedAt,
  };
}

function catalogFolio(row: CatalogFolioRow): Folio {
  return {
    id: row.id,
    numero_folio: row.number,
    tipo_bulto: 'pallet',
    estado_operacional: row.status,
    condicion_termica: null,
    habilitacion_almacenamiento: null,
    condicion_sag: demoSagConditions[0],
    fecha_ingreso: row.created_at,
    variedad: row.variety,
    calibre: null,
    marca: row.species,
    exportadora: row.client_name,
    material: null,
    ubicado_at: null,
  };
}

async function findCatalogFolio(
  executor: DemoDatabaseExecutor,
  number: string,
): Promise<CatalogFolioRow | null> {
  return executor.getFirstAsync<CatalogFolioRow>(
    `SELECT
       f.id,
       f.number,
       f.species,
       f.variety,
       f.status,
       f.created_at,
       c.name AS client_name
     FROM demo_folios f
     INNER JOIN demo_clients c ON c.id = f.client_id
     WHERE f.number = ?`,
    number,
  );
}

export async function listDemoCameras(): Promise<CameraSummary[]> {
  const state = await readState();
  return state.plans.map((plan) => summary(syncDemoOccupancy(plan)));
}

export async function getDemoPlan(cameraId: string): Promise<CameraPlan> {
  const state = await readState();
  return clone(syncDemoOccupancy(findPlan(state, cameraId)));
}

export async function listDemoRecentMovements(cameraId: string): Promise<Movement[]> {
  const state = await readState();
  return clone(state.movements.filter((item) => (
    item.origen?.camara.id === cameraId || item.destino?.camara.id === cameraId
  )).slice(0, 30));
}

export async function openDemoSession(cameraId: string): Promise<OpenedSession> {
  return mutateState(async (state, transaction) => {
    const plan = findPlan(state, cameraId);
    if (plan.acceso.sesion?.es_propia) return openedSession(plan, plan.acceso.sesion);
    if (plan.acceso.bloqueada || plan.acceso.modo === 'solo_lectura') {
      throw new ApiError('La cámara está siendo editada por otro operador.', 409);
    }

    const now = new Date().toISOString();
    const session: EditSession = {
      id: Crypto.randomUUID(),
      es_propia: true,
      usuario: demoOperator,
      dispositivo: demoDevice,
      iniciada_at: now,
      ultima_actividad_at: now,
    };
    plan.acceso = { modo: 'edicion', bloqueada: true, sesion: session };
    await writeDemoAudit(transaction, 'abrir_sesion', 'camara', plan.id, plan.codigo);
    return openedSession(plan, session);
  });
}

export async function closeDemoSession(sessionId: string): Promise<void> {
  await mutateState(async (state, transaction) => {
    const plan = state.plans.find((candidate) => candidate.acceso.sesion?.id === sessionId);
    if (!plan?.acceso.sesion?.es_propia) {
      throw new ApiError('La sesión ya no está activa o pertenece a otro operador.', 409);
    }
    plan.acceso = { modo: 'disponible', bloqueada: false, sesion: null };
    await writeDemoAudit(transaction, 'cerrar_sesion', 'camara', plan.id, plan.codigo);
  });
}

export async function lookupDemoFolio(folioNumber: string): Promise<FolioLookup> {
  const normalized = folioNumber.trim().toUpperCase();
  const db = await openDemoDatabase();
  const [state, catalog] = await Promise.all([
    stateFrom(db),
    findCatalogFolio(db, normalized),
  ]);
  const located = findLocatedFolio(state, normalized);

  if (located?.position.folio) {
    const folio = located.position.folio;
    return {
      existe: true,
      id: folio.id,
      numero_folio: folio.numero_folio,
      tipo_bulto: folio.tipo_bulto,
      estado_operacional: folio.estado_operacional,
      condicion_termica: folio.condicion_termica ?? null,
      habilitacion_almacenamiento: folio.habilitacion_almacenamiento ?? null,
      disponible_ubicacion: false,
      mensaje_disponibilidad: `El folio ya está ubicado en ${cameraDisplayName(located.plan)} · ${located.position.etiqueta ?? ''}.`,
      origen_sistema: 'demo',
      condicion_sag: folio.condicion_sag,
      variedad: folio.variedad,
      calibre: folio.calibre,
      marca: folio.marca,
      exportadora: folio.exportadora,
      ubicacion_actual: {
        camara: { id: located.plan.id, codigo: located.plan.codigo, nombre: located.plan.nombre },
        posicion: { id: located.position.id, etiqueta: located.position.etiqueta },
      },
      material: null,
    };
  }

  if (!catalog) return { existe: false, numero_folio: normalized };
  const folio = catalogFolio(catalog);
  return {
    existe: true,
    id: folio.id,
    numero_folio: folio.numero_folio,
    tipo_bulto: folio.tipo_bulto,
    estado_operacional: folio.estado_operacional,
    condicion_termica: null,
    habilitacion_almacenamiento: null,
    disponible_ubicacion: true,
    mensaje_disponibilidad: 'Folio disponible para ubicación en la operación Demo.',
    origen_sistema: 'demo_administracion',
    condicion_sag: folio.condicion_sag,
    variedad: folio.variedad,
    calibre: folio.calibre,
    marca: folio.marca,
    exportadora: folio.exportadora,
    ubicacion_actual: null,
    material: null,
  };
}

export async function locateDemoFolio(payload: LocatePayload): Promise<void> {
  if (!payload.posicion_destino_id) {
    throw new ApiError('Selecciona una posición para la ubicación Demo.', 422);
  }
  if (payload.tipo_bulto === 'material') {
    throw new ApiError('Los materiales se incorporarán al motor local en el próximo paso.', 422);
  }

  const normalized = payload.numero_folio.trim().toUpperCase();
  const fingerprint = JSON.stringify({
    type: 'locate',
    number: normalized,
    packageType: payload.tipo_bulto,
    cameraId: payload.camara_destino_id,
    positionId: payload.posicion_destino_id,
    sessionId: payload.sesion_destino_id,
    data: payload.datos_folio ?? null,
  });

  await mutateState(async (state, transaction) => {
    if (operationResult(state, payload.operacion_id, fingerprint) === 'repeat') return;
    const destination = findPosition(state, payload.posicion_destino_id!);
    if (destination.plan.id !== payload.camara_destino_id) {
      throw new ApiError('La posición no pertenece a la cámara de destino.', 409);
    }
    assertOwnSession(destination.plan, payload.sesion_destino_id);
    assertVersion(destination.plan, payload.version_destino_conocida);
    if (destination.position.ocupada || destination.position.estado !== 'activa') {
      throw new ApiError('La posición de destino ya no está disponible.', 409);
    }
    if (findLocatedFolio(state, normalized)) {
      throw new ApiError('El folio ya se encuentra ubicado en una cámara Demo.', 409);
    }

    const catalog = await findCatalogFolio(transaction, normalized);
    const now = new Date().toISOString();
    const existing = catalog ? catalogFolio(catalog) : null;
    const condition = demoSagConditions.find(
      (item) => item.id === payload.datos_folio?.condicion_sag_id,
    ) ?? existing?.condicion_sag ?? null;
    const folio: Folio = {
      id: existing?.id ?? Crypto.randomUUID(),
      numero_folio: normalized,
      tipo_bulto: payload.tipo_bulto,
      estado_operacional: 'en_camara',
      condicion_termica: existing?.condicion_termica ?? null,
      habilitacion_almacenamiento: existing?.habilitacion_almacenamiento ?? null,
      condicion_sag: condition,
      fecha_ingreso: existing?.fecha_ingreso ?? now,
      variedad: existing?.variedad ?? payload.datos_folio?.variedad ?? null,
      calibre: existing?.calibre ?? payload.datos_folio?.calibre ?? null,
      marca: existing?.marca ?? payload.datos_folio?.marca ?? null,
      exportadora: existing?.exportadora ?? payload.datos_folio?.exportadora ?? null,
      material: null,
      ubicado_at: now,
    };

    const previousVersion = destination.plan.version_plano;
    destination.position.ocupada = true;
    destination.position.folio = folio;
    destination.plan.version_plano += 1;
    syncDemoOccupancy(destination.plan);
    state.movements.unshift(movement(
      payload.operacion_id,
      'ubicacion_inicial',
      folio,
      null,
      demoMovementEnd(
        destination.plan,
        destination.position,
        previousVersion,
        destination.plan.version_plano,
      ),
      payload.generado_dispositivo_at,
    ));
    state.operationFingerprints[payload.operacion_id] = fingerprint;
    if (catalog) {
      await transaction.runAsync(
        'UPDATE demo_folios SET status = ? WHERE id = ?',
        'en_camara',
        catalog.id,
      );
    }
    await writeDemoAudit(
      transaction,
      'ubicar_folio',
      'folio',
      folio.id,
      `${folio.numero_folio} → ${destination.plan.codigo} · ${destination.position.etiqueta}`,
    );
  });
}

export async function moveDemoFolio(payload: MovePayload): Promise<void> {
  if (!payload.posicion_destino_id) {
    throw new ApiError('Selecciona una posición de destino.', 422);
  }
  const fingerprint = JSON.stringify({
    type: 'move',
    folioId: payload.folio_id,
    destinationPositionId: payload.posicion_destino_id,
    originSessionId: payload.sesion_origen_id,
    destinationSessionId: payload.sesion_destino_id,
  });

  await mutateState(async (state, transaction) => {
    if (operationResult(state, payload.operacion_id, fingerprint) === 'repeat') return;
    const origin = state.plans
      .flatMap((plan) => plan.posiciones.map((position) => ({ plan, position })))
      .find((item) => item.position.folio?.id === payload.folio_id);
    const destination = findPosition(state, payload.posicion_destino_id!);
    if (!origin?.position.folio) {
      throw new ApiError('El folio ya no está en la posición de origen.', 409);
    }
    if (origin.position.id === destination.position.id) {
      throw new ApiError('Selecciona una posición diferente a la actual.', 422);
    }
    assertOwnSession(origin.plan, payload.sesion_origen_id);
    assertOwnSession(destination.plan, payload.sesion_destino_id);
    assertVersion(origin.plan, payload.version_origen_conocida);
    assertVersion(destination.plan, payload.version_destino_conocida);
    if (destination.position.ocupada || destination.position.estado !== 'activa') {
      throw new ApiError('La posición de destino ya no está disponible.', 409);
    }

    const folio = origin.position.folio;
    const sameCamera = origin.plan.id === destination.plan.id;
    const originPreviousVersion = origin.plan.version_plano;
    const destinationPreviousVersion = destination.plan.version_plano;
    origin.position.ocupada = false;
    origin.position.folio = null;
    destination.position.ocupada = true;
    destination.position.folio = folio;
    origin.plan.version_plano += 1;
    if (!sameCamera) destination.plan.version_plano += 1;
    syncDemoOccupancy(origin.plan);
    syncDemoOccupancy(destination.plan);

    const resultingOriginVersion = origin.plan.version_plano;
    const resultingDestinationVersion = destination.plan.version_plano;
    state.movements.unshift(movement(
      payload.operacion_id,
      sameCamera ? 'reubicacion' : 'traslado_entre_camaras',
      folio,
      demoMovementEnd(
        origin.plan,
        origin.position,
        originPreviousVersion,
        resultingOriginVersion,
      ),
      demoMovementEnd(
        destination.plan,
        destination.position,
        destinationPreviousVersion,
        resultingDestinationVersion,
      ),
      payload.generado_dispositivo_at,
    ));
    state.operationFingerprints[payload.operacion_id] = fingerprint;
    await writeDemoAudit(
      transaction,
      'mover_folio',
      'folio',
      folio.id,
      `${folio.numero_folio} → ${destination.plan.codigo} · ${destination.position.etiqueta}`,
    );
  });
}
