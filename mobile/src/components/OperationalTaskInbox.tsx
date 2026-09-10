import * as Crypto from 'expo-crypto';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from 'react-native';

import { OPERATIONAL_POLL_INTERVAL_MS } from '../config/polling';
import {
  AuthSession,
  CameraPlan,
  LocatePayload,
  MovePayload,
  SendLoadFolioToDockPayload,
} from '../domain/estiba';
import {
  ManeuverDiscrepancyType,
  OperationalTask,
  TemporaryExtractionPayload,
  operationalTaskDestinationLabel,
  operationalTaskLabel,
  operationalTaskPositionLabel,
} from '../domain/operationalTasks';
import { calculateRollingFrontier } from '../domain/rollingPlanner';
import { useOperationalPolling } from '../hooks/useOperationalPolling';
import { ApiError } from '../services/apiError';
import { EstibaApi } from '../services/estibaApi';
import { OperationalTasksApi } from '../services/operationalTasksApi';
import {
  OperatorEntityCode,
  OperatorPriorityBadge,
  OperatorRouteLine,
  OperatorStatusBadge,
} from './operator/OperatorPrimitives';
import { operatorTheme as o } from '../theme/operatorTheme';

type Props = {
  api: EstibaApi;
  auth: AuthSession;
};

type TaskTab = 'mias' | 'disponibles';

type OpenSession = {
  cameraId: string;
  sessionId: string;
  openedByTask: boolean;
  plan: CameraPlan;
};

type MovementWarning = {
  codigo: string;
  titulo: string;
  mensaje: string;
};

export function OperationalTaskInbox({ api, auth }: Props) {
  const { width } = useWindowDimensions();
  const compact = width < o.breakpoint.compact;
  const taskApi = useMemo(
    () => api.mode === 'connected' && api.baseUrl ? new OperationalTasksApi(api.baseUrl) : null,
    [api.baseUrl, api.mode],
  );
  const [available, setAvailable] = useState<OperationalTask[]>([]);
  const [mine, setMine] = useState<OperationalTask[]>([]);
  const [tab, setTab] = useState<TaskTab>('mias');
  const [activeTask, setActiveTask] = useState<OperationalTask | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [clock, setClock] = useState(Date.now());
  const initialLoad = useRef(true);
  const loadInFlight = useRef(false);
  const executionSessions = useRef<OpenSession[]>([]);

  const inPhysicalMovement = activeTask?.estado === 'en_proceso';
  const secondsRemaining = useMemo(() => {
    if (!activeTask?.reserva
      || activeTask.estado === 'en_proceso'
      || activeTask.maniobra?.custodia_temporal_activa) return null;
    const expiresAt = activeTask.reserva.vence_at;
    if (!expiresAt) return null;
    return Math.max(0, Math.floor((new Date(expiresAt).getTime() - clock) / 1000));
  }, [activeTask?.estado, activeTask?.maniobra?.custodia_temporal_activa, activeTask?.reserva, clock]);
  const leaseExpired = Boolean(activeTask && !inPhysicalMovement && secondsRemaining === 0);
  const hasPhysicalDestination = Boolean(
    activeTask?.destino?.posicion && activeTask?.reserva?.tipo_compromiso === 'fisica',
  );

  useEffect(() => {
    void loadTasks();
  }, [taskApi, auth.token]);

  useEffect(() => {
    if (!activeTask?.reserva?.vence_at
      || activeTask.estado === 'en_proceso'
      || activeTask.maniobra?.custodia_temporal_activa) return undefined;
    const interval = setInterval(() => setClock(Date.now()), 1_000);
    return () => clearInterval(interval);
  }, [
    activeTask?.id,
    activeTask?.estado,
    activeTask?.maniobra?.custodia_temporal_activa,
    activeTask?.reserva?.vence_at,
  ]);

  useEffect(() => {
    if (!taskApi || !activeTask?.reserva || activeTask.estado === 'en_proceso' || leaseExpired) {
      return undefined;
    }
    const interval = setInterval(() => void renewActiveTask(), 4 * 60_000);
    return () => clearInterval(interval);
  }, [taskApi, activeTask?.id, activeTask?.estado, activeTask?.reserva?.id, leaseExpired]);

  useEffect(() => {
    if (!leaseExpired || !activeTask) return;
    setError('El claim venció antes de iniciar el movimiento. Actualiza la bandeja y vuelve a tomar la tarea.');
  }, [leaseExpired, activeTask?.id]);

  useOperationalPolling(
    () => loadTasks({ quiet: true }),
    {
      enabled: Boolean(taskApi) && activeTask === null,
      intervalMs: OPERATIONAL_POLL_INTERVAL_MS,
      onError: (reason) => setError(messageFrom(reason)),
      onResume: () => loadTasks({ quiet: true }),
    },
  );

  async function loadTasks({ quiet = false }: { quiet?: boolean } = {}) {
    if (!taskApi || loadInFlight.current) return;
    loadInFlight.current = true;
    if (!quiet) setBusy(true);

    try {
      const [nextMine, nextAvailable] = await Promise.all([
        taskApi.list(auth.token, 'mias'),
        taskApi.list(auth.token, 'disponibles'),
      ]);
      setMine(nextMine);
      setAvailable(nextAvailable);
      setError('');

      setActiveTask((current) => {
        if (!current) return current;
        return nextMine.find((task) => task.id === current.id) ?? null;
      });

      if (initialLoad.current) {
        initialLoad.current = false;
        setTab(nextMine.length > 0 ? 'mias' : 'disponibles');
      }

      return { mine: nextMine, available: nextAvailable };
    } catch (reason) {
      setError(messageFrom(reason));
    } finally {
      loadInFlight.current = false;
      if (!quiet) setBusy(false);
    }
  }

  async function takeTask(task: OperationalTask) {
    if (!taskApi) return;
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const taken = await taskApi.take(auth.token, task.id);
      setActiveTask(taken);
      setTab('mias');
      setNotice(
        taken.tipo_movimiento === 'retiro'
          ? `Tarea crítica tomada. Destino directo: ${operationalTaskDestinationLabel(taken)}.`
          : taken.reserva?.tipo_compromiso === 'fisica'
          ? `Tarea ${operationalTaskLabel(taken.plan.tipo)} tomada con destino físico reservado.`
          : `Tarea ${operationalTaskLabel(taken.plan.tipo)} tomada. El destino se calculará con el snapshot vigente.`,
      );
      if (taken.tipo_movimiento !== 'retiro'
        && (!taken.destino?.posicion || taken.reserva?.tipo_compromiso !== 'fisica')) {
        await calculateAndMaterializeFrontier(taken);
      }
      await loadTasks({ quiet: true });
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    } finally {
      setBusy(false);
    }
  }

  function beginTask(task: OperationalTask) {
    setActiveTask(task);
    setError('');
    setNotice('');
    if (task.estado === 'en_proceso') {
      setNotice('Movimiento ya iniciado: el destino está fijo hasta completar o registrar una incidencia.');
      return;
    }
    if ((task.reserva?.segundos_restantes ?? 600) < 180) void renewActiveTaskById(task.id);
    if (task.tipo_movimiento !== 'retiro'
      && (!task.destino?.posicion || task.reserva?.tipo_compromiso !== 'fisica')) {
      void calculateAndMaterializeFrontier(task);
    }
  }

  async function renewActiveTask() {
    if (!activeTask) return;
    await renewActiveTaskById(activeTask.id);
  }

  async function renewActiveTaskById(taskId: string) {
    if (!taskApi) return;
    try {
      const renewed = await taskApi.renew(auth.token, taskId);
      setActiveTask((current) => current?.id === renewed.id ? renewed : current);
      replaceMine([renewed]);
      setClock(Date.now());
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    }
  }

  function requestRelease(task: OperationalTask) {
    if (task.estado === 'en_proceso') {
      Alert.alert(
        'Movimiento ya iniciado',
        'El pallet está en punto de no retorno. Completa el destino reservado o registra una incidencia.',
      );
      return;
    }

    Alert.alert(
      'Liberar tarea',
      task.reserva?.tipo_compromiso === 'fisica'
        ? 'La tarea volverá a la bandeja y su destino físico quedará disponible.'
        : 'La tarea volverá a la bandeja. Aún no existe una posición física comprometida.',
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Liberar', style: 'destructive', onPress: () => void releaseTask(task) },
      ],
    );
  }

  async function releaseTask(task: OperationalTask) {
    if (!taskApi || task.estado === 'en_proceso') return;
    setBusy(true);
    setError('');
    try {
      await taskApi.release(auth.token, task.id);
      if (activeTask?.id === task.id) {
        setActiveTask(null);
      }
      setNotice('Tarea liberada y devuelta a la bandeja.');
      await loadTasks({ quiet: true });
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    } finally {
      setBusy(false);
    }
  }

  async function calculateAndMaterializeFrontier(anchorTask: OperationalTask) {
    if (!taskApi) return;
    setBusy(true);
    setError('');

    try {
      const snapshot = await taskApi.snapshot(auth.token, anchorTask.plan.id);
      if (snapshot.planner.horizon !== 'rolling' || snapshot.planner.compute !== 'tablet') {
        throw new Error(
          `El planificador está configurado como ${snapshot.planner.compute}/${snapshot.planner.horizon}; no corresponde cálculo rolling en tablet.`,
        );
      }

      const tasksForPlan = dedupeTasks([
        anchorTask,
        ...mine.filter((task) => task.plan.id === anchorTask.plan.id),
      ]).filter((task) => task.estado === 'asumida');
      const cameras = (await api.listCameras(auth.token))
        .filter((camera) => camera.contenido === 'productos' && camera.estado === 'activa');
      const requiredIds = candidateCameraIds(tasksForPlan, cameras.map((camera) => camera.id));
      const plans = await Promise.all([...requiredIds].map((cameraId) => api.getPlan(auth.token, cameraId)));
      const frontier = calculateRollingFrontier(tasksForPlan, snapshot, plans);

      if (!frontier.proposals.length) {
        throw new Error('El snapshot no contiene un destino libre y compatible para la frontera actual.');
      }

      const result = await taskApi.materializeFrontier(
        auth.token,
        anchorTask.plan.id,
        snapshot.snapshot_version,
        frontier.proposals,
      );
      const acceptedTasks = result.aceptadas.map((item) => item.tarea);
      replaceMine(acceptedTasks);
      const acceptedAnchor = acceptedTasks.find((task) => task.id === anchorTask.id);
      if (acceptedAnchor) {
        setActiveTask(acceptedAnchor);
        setNotice(
          `Frontera validada: ${acceptedTasks.length} tarea(s) con reserva física. `
          + `Destino actual: ${operationalTaskPositionLabel(acceptedAnchor.destino)}.`,
        );
      } else {
        const rejected = result.rechazadas.find((item) => item.tarea_id === anchorTask.id);
        setError(
          rejected?.motivo
            ?? 'La tarea quedó fuera de la frontera inmediata. Actualiza el estado antes de ejecutarla.',
        );
      }

      if (result.recalcular && acceptedAnchor) {
        setNotice(
          `Frontera parcial: ${acceptedTasks.length} aceptada(s), ${result.rechazadas.length} reemplazada(s). `
          + `La tablet recalculará con el nuevo snapshot en el siguiente ciclo.`,
        );
      }
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    } finally {
      setBusy(false);
    }
  }

  async function startPhysicalTask() {
    if (!taskApi || !activeTask || activeTask.estado === 'en_proceso') return;
    if (activeTask.tipo_movimiento !== 'retiro'
      && (!activeTask.destino?.posicion || activeTask.reserva?.tipo_compromiso !== 'fisica')) {
      setError('Primero debe existir un destino físico validado por el servidor.');
      return;
    }

    setBusy(true);
    setError('');
    const sessions: OpenSession[] = [];
    try {
      await acquireExecutionSessions(activeTask, sessions);
      const started = await taskApi.start(auth.token, activeTask.id);
      executionSessions.current = sessions;
      setActiveTask(started);
      replaceMine([started]);
      setNotice(
        `PALLET EN MOVIMIENTO · ${started.folio.numero_folio}. `
        + `Desde este punto el destino ${operationalTaskDestinationLabel(started)} no puede recalcularse.`,
      );
    } catch (reason) {
      await closeTemporarySessions(sessions);
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    } finally {
      setBusy(false);
    }
  }

  async function completeTask() {
    if (!activeTask || activeTask.estado !== 'en_proceso') return;
    const task = activeTask;
    const destination = task.destino;
    if (!destination?.posicion) {
      setError('La tarea en movimiento no posee una posición física de destino. Registra una incidencia.');
      return;
    }

    setBusy(true);
    setError('');
    setNotice('');
    const sessions = executionSessions.current.length
      ? executionSessions.current
      : [];

    try {
      if (!sessions.length) await acquireExecutionSessions(task, sessions);
      const destinationSession = sessionForCamera(sessions, destination.camara.id);
      const sourceSession = task.origen?.camara
        ? sessionForCamera(sessions, task.origen.camara.id)
        : null;

      if (task.origen?.posicion && sourceSession) {
        const payload: MovePayload = {
          operacion_id: Crypto.randomUUID(),
          tarea_movimiento_id: task.id,
          folio_id: task.folio.id,
          posicion_destino_id: destination.posicion.id,
          sesion_origen_id: sourceSession.sessionId,
          sesion_destino_id: destinationSession.sessionId,
          version_origen_conocida: sourceSession.plan.version_plano,
          version_destino_conocida: destinationSession.plan.version_plano,
          generado_dispositivo_at: new Date().toISOString(),
        };
        await executeWithWarnings(payload, (confirmedPayload) => api.move(auth.token, confirmedPayload));
      } else if (task.tipo_movimiento === 'ubicacion_inicial') {
        const payload: LocatePayload = {
          operacion_id: Crypto.randomUUID(),
          tarea_movimiento_id: task.id,
          numero_folio: task.folio.numero_folio,
          tipo_bulto: task.folio.tipo_bulto,
          camara_destino_id: destination.camara.id,
          posicion_destino_id: destination.posicion.id,
          sesion_destino_id: destinationSession.sessionId,
          version_destino_conocida: destinationSession.plan.version_plano,
          generado_dispositivo_at: new Date().toISOString(),
        };
        await executeWithWarnings(payload, (confirmedPayload) => api.locate(auth.token, confirmedPayload));
      } else {
        throw new Error('La tarea no posee un origen físico compatible con este movimiento.');
      }

      setNotice(`Movimiento completado: ${task.folio.numero_folio} → ${operationalTaskPositionLabel(task.destino)}.`);
      await continueManeuver(task);
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    } finally {
      await closeTemporarySessions(sessions);
      executionSessions.current = [];
      setBusy(false);
    }
  }

  async function completeDirectWithdrawal() {
    if (!taskApi
      || !activeTask
      || activeTask.estado !== 'en_proceso'
      || activeTask.tipo_movimiento !== 'retiro'
      || !activeTask.destino_logico?.carga_folio_id) return;

    const task = activeTask;
    const directDestination = activeTask.destino_logico;
    const fromPrefrio = task.contexto?.origen_logico === 'tunel_prefrio';
    const sessions = executionSessions.current.length
      ? executionSessions.current
      : [];
    setBusy(true);
    setError('');
    setNotice('');

    try {
      if (fromPrefrio) {
        await taskApi.completeDirectPrefrio(auth.token, task.id);
      } else {
        if (!sessions.length) await acquireExecutionSessions(task, sessions);
        if (!task.origen?.camara) throw new Error('La tarea no conserva una cámara de origen.');
        const sourceSession = sessionForCamera(sessions, task.origen.camara.id);
        const payload: SendLoadFolioToDockPayload = {
          operacion_id: Crypto.randomUUID(),
          tarea_movimiento_id: task.id,
          anden_id: directDestination.id,
          sesion_estiba_id: sourceSession.sessionId,
          version_camara_conocida: sourceSession.plan.version_plano,
          generado_dispositivo_at: new Date().toISOString(),
        };
        await executeWithWarnings(
          payload,
          (confirmedPayload) => api.sendLoadFolioToDock(
            auth.token,
            directDestination.carga_folio_id,
            confirmedPayload,
          ).then(() => undefined),
        );
      }

      setNotice(
        `Retiro completado: ${task.folio.numero_folio} entregado en ${directDestination.nombre}.`,
      );
      setActiveTask(null);
      await loadTasks({ quiet: true });
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    } finally {
      await closeTemporarySessions(sessions);
      executionSessions.current = [];
      setBusy(false);
    }
  }

  async function completeTemporaryExtraction() {
    if (!taskApi
      || !activeTask
      || activeTask.estado !== 'en_proceso'
      || activeTask.tipo_paso_maniobra !== 'extraccion_temporal') return;

    const task = activeTask;
    const sessions = executionSessions.current.length ? executionSessions.current : [];
    setBusy(true);
    setError('');
    try {
      if (!sessions.length) await acquireExecutionSessions(task, sessions);
      if (!task.origen?.camara) throw new Error('La extracción no conserva su cámara de origen.');
      const sourceSession = sessionForCamera(sessions, task.origen.camara.id);
      const payload: TemporaryExtractionPayload = {
        operacion_id: Crypto.randomUUID(),
        sesion_origen_id: sourceSession.sessionId,
        version_origen_conocida: sourceSession.plan.version_plano,
        generado_dispositivo_at: new Date().toISOString(),
      };
      await executeWithWarnings(
        payload,
        (confirmedPayload) => taskApi.completeTemporaryExtraction(
          auth.token,
          task.id,
          confirmedPayload,
        ),
      );
      setNotice(
        `${task.folio.numero_folio} quedó bajo custodia temporal de la maniobra. Continúa con el siguiente paso mostrado.`,
      );
      await continueManeuver(task);
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    } finally {
      await closeTemporarySessions(sessions);
      executionSessions.current = [];
      setBusy(false);
    }
  }

  function requestDiscrepancy() {
    if (!activeTask?.maniobra) {
      setError('La gestión NO COINCIDE está disponible para maniobras físicas del planificador.');
      return;
    }
    Alert.alert(
      'NO COINCIDE',
      'Detendremos la maniobra sin cambiar pasos ya ejecutados. ¿Qué encontró físicamente?',
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Pallet distinto', onPress: () => void sendDiscrepancy('pallet_no_coincide') },
        { text: 'Obstáculo / no movible', onPress: () => void sendDiscrepancy('obstaculo') },
      ],
    );
  }

  async function sendDiscrepancy(type: ManeuverDiscrepancyType) {
    if (!taskApi || !activeTask) return;
    setBusy(true);
    setError('');
    try {
      await taskApi.reportDiscrepancy(auth.token, activeTask.id, type);
      setNotice('Maniobra pausada. Supervisión recibió la discrepancia y el WMS recalculará desde el estado físico confirmado.');
      setActiveTask(null);
      await loadTasks({ quiet: true });
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    } finally {
      setBusy(false);
    }
  }

  async function continueManeuver(completedTask: OperationalTask) {
    setActiveTask(null);
    const refreshed = await loadTasks({ quiet: true });
    if (!completedTask.maniobra || !refreshed) return;

    const next = refreshed.mine.find((task) => (
      task.maniobra?.id === completedTask.maniobra?.id
      && (task.secuencia_maniobra ?? 0) > (completedTask.secuencia_maniobra ?? 0)
    ));
    if (!next) return;

    setActiveTask(next);
    setTab('mias');
    setNotice(
      `Continúa la misma maniobra: paso ${next.secuencia_maniobra ?? next.maniobra?.secuencia_actual} `
      + `de ${next.maniobra?.pasos_totales} · ${next.folio.numero_folio}.`,
    );
    if (next.tipo_movimiento !== 'retiro'
      && (!next.destino?.posicion || next.reserva?.tipo_compromiso !== 'fisica')) {
      await calculateAndMaterializeFrontier(next);
    }
  }

  async function acquireExecutionSessions(task: OperationalTask, sessions: OpenSession[]) {
    if (task.tipo_movimiento === 'retiro') {
      if (task.contexto?.origen_logico === 'tunel_prefrio') return;
      if (!task.origen?.camara) throw new Error('La tarea no posee cámara de origen.');
      await acquireSession(task.origen.camara.id, sessions);
      return;
    }
    if (!task.destino?.camara) throw new Error('La tarea no posee cámara de destino materializada.');
    await acquireSession(task.destino.camara.id, sessions);
    if (task.origen?.camara && task.origen.camara.id !== task.destino.camara.id) {
      await acquireSession(task.origen.camara.id, sessions);
    }
  }

  async function acquireSession(cameraId: string, sessions: OpenSession[]) {
    const existing = sessions.find((session) => session.cameraId === cameraId);
    if (existing) return existing;

    const plan = await api.getPlan(auth.token, cameraId);
    if (plan.acceso.modo === 'solo_lectura') {
      throw new Error(`${plan.nombre} está siendo operada desde otra sesión.`);
    }

    if (plan.acceso.modo === 'edicion' && plan.acceso.sesion?.es_propia) {
      const own: OpenSession = {
        cameraId,
        sessionId: plan.acceso.sesion.id,
        openedByTask: false,
        plan,
      };
      sessions.push(own);
      return own;
    }

    if (plan.acceso.modo !== 'disponible') {
      throw new Error(`${plan.nombre} no está disponible para ejecutar la tarea.`);
    }

    const opened = await api.openSession(auth.token, cameraId);
    const openedPlan = await api.getPlan(auth.token, cameraId);
    const session: OpenSession = {
      cameraId,
      sessionId: opened.id,
      openedByTask: true,
      plan: openedPlan,
    };
    sessions.push(session);
    return session;
  }

  function sessionForCamera(sessions: OpenSession[], cameraId: string) {
    const session = sessions.find((candidate) => candidate.cameraId === cameraId);
    if (!session) throw new Error('La sesión física requerida por la tarea ya no está disponible.');
    return session;
  }

  async function closeTemporarySessions(sessions: OpenSession[]) {
    await Promise.all(sessions
      .filter((session) => session.openedByTask)
      .map((session) => api.closeSession(auth.token, session.sessionId).catch(() => undefined)));
  }

  async function executeWithWarnings<T extends { advertencias_confirmadas?: string[] }>(
    payload: T,
    operation: (confirmedPayload: T) => Promise<void>,
  ) {
    try {
      await operation(payload);
    } catch (reason) {
      const warnings = warningResponse(reason);
      if (!warnings.length) throw reason;

      const accepted = await confirmWarnings(warnings);
      if (!accepted) throw new Error('Operación cancelada: no se confirmaron las advertencias físicas.');

      await operation({
        ...payload,
        advertencias_confirmadas: warnings.map((warning) => warning.codigo),
      });
    }
  }

  function confirmWarnings(warnings: MovementWarning[]): Promise<boolean> {
    return new Promise((resolve) => {
      Alert.alert(
        'Confirmar excepción física',
        warnings.map((warning) => `${warning.titulo}\n${warning.mensaje}`).join('\n\n'),
        [
          { text: 'Cancelar', style: 'cancel', onPress: () => resolve(false) },
          { text: 'Continuar', style: 'destructive', onPress: () => resolve(true) },
        ],
        { cancelable: false },
      );
    });
  }

  function replaceMine(tasks: OperationalTask[]) {
    if (!tasks.length) return;
    const byId = new Map(tasks.map((task) => [task.id, task]));
    setMine((current) => current.map((task) => byId.get(task.id) ?? task));
  }

  if (!taskApi) {
    return (
      <View style={styles.emptyStandalone}>
        <Text style={styles.emptyIcon}>◎</Text>
        <Text style={styles.emptyTitle}>Labores guiadas disponibles en operación conectada</Text>
        <Text style={styles.emptyCopy}>
          El modo demo conserva el plano y las operaciones actuales sin consultar tareas productivas.
        </Text>
      </View>
    );
  }

  const visibleTasks = tab === 'mias' ? mine : available;

  return (
    <View style={styles.screen}>
      <View style={styles.header}>
        <View style={styles.headerCopy}>
          <Text style={styles.eyebrow}>OPERACIÓN GUIADA · ROLLING HORIZON</Text>
          <Text style={styles.title}>Bandeja de labores</Text>
          <Text style={styles.subtitle}>{auth.usuario.nombre} · {auth.dispositivo.nombre}</Text>
        </View>
        <Pressable disabled={busy} onPress={() => void loadTasks()} style={styles.refreshButton}>
          <Text style={styles.refreshButtonText}>↻ Actualizar</Text>
        </Pressable>
      </View>

      {error ? (
        <Pressable onPress={() => setError('')} style={styles.errorBanner}>
          <Text style={styles.errorText}>{error}</Text>
          <Text style={styles.bannerClose}>×</Text>
        </Pressable>
      ) : null}
      {notice ? (
        <Pressable onPress={() => setNotice('')} style={styles.noticeBanner}>
          <Text style={styles.noticeText}>{notice}</Text>
          <Text style={styles.bannerClose}>×</Text>
        </Pressable>
      ) : null}

      <View style={styles.tabs}>
        <Pressable onPress={() => setTab('mias')} style={[styles.tab, tab === 'mias' && styles.tabActive]}>
          <Text style={[styles.tabText, tab === 'mias' && styles.tabTextActive]}>Mis tareas · {mine.length}</Text>
        </Pressable>
        <Pressable
          onPress={() => setTab('disponibles')}
          style={[styles.tab, tab === 'disponibles' && styles.tabActive]}
        >
          <Text style={[styles.tabText, tab === 'disponibles' && styles.tabTextActive]}>
            Disponibles · {available.length}
          </Text>
        </Pressable>
      </View>

      <View style={[styles.workspace, compact && styles.workspaceCompact]}>
        <ScrollView
          contentContainerStyle={styles.list}
          style={[styles.listScroll, compact && styles.listScrollCompact]}
        >
          {visibleTasks.length ? visibleTasks.map((task) => (
            <TaskCard
              active={activeTask?.id === task.id}
              key={task.id}
              onExecute={tab === 'mias' ? () => beginTask(task) : undefined}
              onRelease={tab === 'mias' && task.estado !== 'en_proceso' ? () => requestRelease(task) : undefined}
              onTake={tab === 'disponibles' ? () => void takeTask(task) : undefined}
              task={task}
            />
          )) : (
            <View style={styles.emptyList}>
              <Text style={styles.emptyIcon}>✓</Text>
              <Text style={styles.emptyTitle}>
                {tab === 'mias' ? 'No tienes tareas tomadas' : 'No hay labores pendientes disponibles'}
              </Text>
              <Text style={styles.emptyCopy}>
                {tab === 'mias'
                  ? 'Puedes tomar varias labores como cola; solo un pallet puede entrar físicamente en movimiento por tablet.'
                  : 'La bandeja se actualizará automáticamente cuando exista nuevo trabajo.'}
              </Text>
            </View>
          )}
        </ScrollView>

        <View style={[styles.executionPanel, compact && styles.executionPanelCompact]}>
          {activeTask ? (
            <>
              <View style={styles.executionHeader}>
                <View style={styles.executionTitleWrap}>
                  <Text style={styles.eyebrow}>{inPhysicalMovement ? 'PUNTO DE NO RETORNO' : 'TAREA TOMADA'}</Text>
                  <Text style={styles.executionTitle}>{operationalTaskLabel(activeTask.plan.tipo)}</Text>
                </View>
                <CommitmentBadge task={activeTask} seconds={secondsRemaining} />
              </View>

              <View style={styles.routeCard}>
                <OperatorRouteLine label="Folio" value={activeTask.folio.numero_folio} strong />
                <OperatorRouteLine label="Origen" value={operationalTaskPositionLabel(activeTask.origen)} />
                <OperatorRouteLine label="Destino" value={operationalTaskDestinationLabel(activeTask)} strong />
                <OperatorRouteLine label="Motivo" value={taskReason(activeTask)} />
                {activeTask.maniobra ? (
                  <OperatorRouteLine
                    label="Maniobra"
                    value={`Paso ${activeTask.secuencia_maniobra ?? activeTask.maniobra.secuencia_actual} de ${activeTask.maniobra.pasos_totales}`}
                    strong
                  />
                ) : null}
              </View>

              {!inPhysicalMovement ? (
                <Step number="1" title="Seguir el patrón mostrado" complete>
                  <Text style={styles.destinationHint}>
                    Retira exactamente {activeTask.folio.numero_folio} desde {operationalTaskPositionLabel(activeTask.origen)}.
                    El WMS ya conoce el folio y la posición; no requiere escaneo mientras la realidad coincida.
                  </Text>
                </Step>
              ) : null}

              {!inPhysicalMovement && activeTask.tipo_movimiento !== 'retiro' ? (
                <Step number="2" title="Confirmar destino calculado" complete={hasPhysicalDestination}>
                  <Text style={styles.destinationHint}>
                    {hasPhysicalDestination
                      ? `Servidor reservó: ${operationalTaskPositionLabel(activeTask.destino)}`
                      : 'La tablet está simulando el destino y el servidor reservará únicamente este paso próximo.'}
                  </Text>
                  {!hasPhysicalDestination ? (
                    <Pressable
                      disabled={busy || leaseExpired}
                      onPress={() => void calculateAndMaterializeFrontier(activeTask)}
                      style={[styles.secondaryButton, (busy || leaseExpired) && styles.buttonDisabled]}
                    >
                      <Text style={styles.secondaryButtonText}>Recalcular frontera</Text>
                    </Pressable>
                  ) : null}
                </Step>
              ) : null}

              {!inPhysicalMovement ? (
                <Step
                  number={activeTask.tipo_movimiento === 'retiro' ? '2' : '3'}
                  title="Retirar pallet"
                  complete={false}
                  disabled={activeTask.tipo_movimiento !== 'retiro' && !hasPhysicalDestination}
                >
                  <Text style={styles.pointOfNoReturnCopy}>
                    Esta acción marca el pallet como físicamente en movimiento. Desde aquí el destino queda fijo.
                  </Text>
                  <Pressable
                    disabled={(activeTask.tipo_movimiento !== 'retiro' && !hasPhysicalDestination) || busy || leaseExpired}
                    onPress={() => void startPhysicalTask()}
                    style={[
                      styles.primaryButton,
                      ((activeTask.tipo_movimiento !== 'retiro' && !hasPhysicalDestination) || busy || leaseExpired) && styles.buttonDisabled,
                    ]}
                  >
                    <Text style={styles.primaryButtonText}>RETIRAR PALLET · INICIAR MOVIMIENTO</Text>
                  </Pressable>
                  <Pressable disabled={busy} onPress={() => requestRelease(activeTask)} style={styles.releaseButton}>
                    <Text style={styles.releaseButtonText}>Liberar antes de iniciar</Text>
                  </Pressable>
                  {activeTask.maniobra ? (
                    <Pressable disabled={busy} onPress={requestDiscrepancy} style={styles.mismatchButton}>
                      <Text style={styles.mismatchButtonText}>NO COINCIDE</Text>
                    </Pressable>
                  ) : null}
                </Step>
              ) : null}

              {inPhysicalMovement && activeTask.tipo_movimiento !== 'retiro' ? (
                <Step number="4" title="Completar destino indicado" complete={false}>
                  <Text style={styles.destinationHint}>
                    Lleva {activeTask.folio.numero_folio} a {operationalTaskPositionLabel(activeTask.destino)}.
                  </Text>
                  <Pressable
                    disabled={busy}
                    onPress={() => void completeTask()}
                    style={[styles.primaryButton, busy && styles.buttonDisabled]}
                  >
                    <Text style={styles.primaryButtonText}>CONFIRMAR MOVIMIENTO</Text>
                  </Pressable>
                  {activeTask.maniobra ? (
                    <Pressable disabled={busy} onPress={requestDiscrepancy} style={styles.mismatchButton}>
                      <Text style={styles.mismatchButtonText}>NO COINCIDE</Text>
                    </Pressable>
                  ) : null}
                  <Text style={styles.pointOfNoReturnCopy}>
                    Confirma solo cuando el pallet esté físicamente resuelto en el destino mostrado.
                  </Text>
                </Step>
              ) : null}

              {inPhysicalMovement
                && activeTask.tipo_movimiento === 'retiro'
                && activeTask.tipo_paso_maniobra === 'extraccion_temporal' ? (
                <Step number="3" title="Confirmar extracción temporal" complete={false}>
                  <Text style={styles.destinationHint}>
                    Mantén {activeTask.folio.numero_folio} bajo control de esta maniobra. El WMS mostrará su retorno o destino definitivo antes de cerrar.
                  </Text>
                  <Pressable
                    disabled={busy}
                    onPress={() => void completeTemporaryExtraction()}
                    style={[styles.primaryButton, busy && styles.buttonDisabled]}
                  >
                    <Text style={styles.primaryButtonText}>CONFIRMAR PALLET RETIRADO</Text>
                  </Pressable>
                  <Pressable disabled={busy} onPress={requestDiscrepancy} style={styles.mismatchButton}>
                    <Text style={styles.mismatchButtonText}>NO COINCIDE</Text>
                  </Pressable>
                </Step>
              ) : null}

              {inPhysicalMovement
                && activeTask.tipo_movimiento === 'retiro'
                && activeTask.tipo_paso_maniobra !== 'extraccion_temporal' ? (
                <Step number="3" title="Confirmar entrega en andén" complete={false}>
                  <Text style={styles.destinationHint}>
                    Destino comprometido: {operationalTaskDestinationLabel(activeTask)}
                  </Text>
                  <Pressable
                    disabled={busy}
                    onPress={() => void completeDirectWithdrawal()}
                    style={[styles.primaryButton, busy && styles.buttonDisabled]}
                  >
                    <Text style={styles.primaryButtonText}>CONFIRMAR ENTREGA EN ANDÉN</Text>
                  </Pressable>
                  <Text style={styles.pointOfNoReturnCopy}>
                    Confirma solo cuando el pallet haya salido físicamente de su origen y esté en el andén indicado.
                  </Text>
                </Step>
              ) : null}
            </>
          ) : (
            <View style={styles.executionEmpty}>
              <Text style={styles.emptyIcon}>⇢</Text>
              <Text style={styles.emptyTitle}>Selecciona una tarea propia</Text>
              <Text style={styles.emptyCopy}>
                Tomar una tarea reclama el trabajo. La posición se reserva recién cuando la tablet propone una frontera todavía válida.
              </Text>
            </View>
          )}
        </View>
      </View>

      {busy ? (
        <View pointerEvents="none" style={styles.busyOverlay}>
          <ActivityIndicator color={o.color.primary} size="large" />
          <Text style={styles.busyText}>Sincronizando estado operacional…</Text>
        </View>
      ) : null}
    </View>
  );
}

function TaskCard({
  active,
  onExecute,
  onRelease,
  onTake,
  task,
}: {
  active: boolean;
  onExecute?: () => void;
  onRelease?: () => void;
  onTake?: () => void;
  task: OperationalTask;
}) {
  const commitment = task.estado === 'en_proceso'
    ? 'EN MOVIMIENTO'
    : task.reserva?.tipo_compromiso === 'fisica'
      ? 'DESTINO RESERVADO'
      : task.reserva
        ? 'CLAIM'
        : 'OFRECIDA';

  return (
    <View style={[styles.taskCard, active && styles.taskCardActive]}>
      <View style={styles.taskTopline}>
        <Text style={styles.taskType}>{operationalTaskLabel(task.plan.tipo)}</Text>
        <OperatorPriorityBadge priority={task.prioridad} />
      </View>
      <View style={styles.taskFolio}><OperatorEntityCode prominent value={task.folio.numero_folio} /></View>
      <View style={styles.taskCommitment}>
        <OperatorStatusBadge
          label={commitment}
          tone={task.estado === 'en_proceso' ? 'critical' : task.reserva?.tipo_compromiso === 'fisica' ? 'success' : task.reserva ? 'info' : 'neutral'}
        />
      </View>
      <OperatorRouteLine label="Origen" value={operationalTaskPositionLabel(task.origen)} />
      <OperatorRouteLine label="Destino" value={operationalTaskDestinationLabel(task)} strong />
      <Text numberOfLines={2} style={styles.taskInstruction}>{taskReason(task)}</Text>
      <View style={styles.taskFooter}>
        <Text style={styles.taskMeta}>Secuencia {task.secuencia}</Text>
        <View style={styles.taskActions}>
          {onRelease ? (
            <Pressable onPress={onRelease} style={styles.releaseSmall}>
              <Text style={styles.releaseSmallText}>Liberar</Text>
            </Pressable>
          ) : null}
          {onExecute ? (
            <Pressable onPress={onExecute} style={styles.executeButton}>
              <Text style={styles.executeButtonText}>{task.estado === 'en_proceso' ? 'Continuar' : 'Abrir'}</Text>
            </Pressable>
          ) : null}
          {onTake ? (
            <Pressable onPress={onTake} style={styles.executeButton}>
              <Text style={styles.executeButtonText}>Tomar tarea</Text>
            </Pressable>
          ) : null}
        </View>
      </View>
    </View>
  );
}

function CommitmentBadge({ task, seconds }: { task: OperationalTask; seconds: number | null }) {
  if (task.estado === 'en_proceso') {
    return <OperatorStatusBadge label="EN MOVIMIENTO · DESTINO FIJO" tone="critical" />;
  }
  const expired = seconds !== null && seconds <= 0;
  const warning = seconds !== null && seconds > 0 && seconds < 180;
  const prefix = task.reserva?.tipo_compromiso === 'fisica' ? 'FÍSICA' : 'CLAIM';
  return <OperatorStatusBadge
    label={expired ? `${prefix} VENCIDO` : `${prefix} ${seconds === null ? 'ACTIVO' : formatDuration(seconds)}`}
    tone={expired ? 'critical' : warning ? 'warning' : 'success'}
  />;
}

function Step({
  children,
  complete,
  disabled = false,
  number,
  title,
}: {
  children: ReactNode;
  complete: boolean;
  disabled?: boolean;
  number: string;
  title: string;
}) {
  return (
    <View style={[styles.step, disabled && styles.stepDisabled]}>
      <View style={[styles.stepNumber, complete && styles.stepNumberComplete]}>
        <Text style={[styles.stepNumberText, complete && styles.stepNumberTextComplete]}>{complete ? '✓' : number}</Text>
      </View>
      <View style={styles.stepBody}>
        <Text style={styles.stepTitle}>{title}</Text>
        {children}
      </View>
    </View>
  );
}

function candidateCameraIds(tasks: OperationalTask[], allProductCameraIds: string[]) {
  const ids = new Set<string>();
  let needsGeneralSearch = false;

  for (const task of tasks) {
    if (task.destino?.camara.id) {
      ids.add(task.destino.camara.id);
      continue;
    }
    if (task.tipo_movimiento === 'reubicacion' && task.origen?.camara.id) {
      ids.add(task.origen.camara.id);
      continue;
    }
    if (task.tipo_movimiento === 'ubicacion_inicial' || task.tipo_movimiento === 'traslado_entre_camaras') {
      needsGeneralSearch = true;
    }
  }

  if (needsGeneralSearch) allProductCameraIds.forEach((id) => ids.add(id));
  return ids;
}

function dedupeTasks(tasks: OperationalTask[]) {
  return [...new Map(tasks.map((task) => [task.id, task])).values()];
}

function taskReason(task: OperationalTask) {
  if (task.instruccion) return task.instruccion;
  const candidates = ['motivo', 'razon', 'detalle', 'origen'];
  for (const key of candidates) {
    const value = task.contexto?.[key];
    if (typeof value === 'string' && value.trim()) return value;
  }
  return task.plan.titulo;
}

function warningResponse(reason: unknown): MovementWarning[] {
  if (!(reason instanceof ApiError) || !reason.data || typeof reason.data !== 'object') return [];
  const data = reason.data as { codigo?: string; advertencias?: MovementWarning[] };
  return data.codigo === 'confirmacion_requerida' && Array.isArray(data.advertencias)
    ? data.advertencias
    : [];
}

function formatDuration(seconds: number) {
  const minutes = Math.floor(seconds / 60);
  const remainder = seconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
}

function messageFrom(reason: unknown) {
  return reason instanceof Error ? reason.message : 'La operación no pudo completarse.';
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: o.color.canvas, padding: o.space[4] },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: o.space[3], marginBottom: o.space[3] },
  headerCopy: { flexShrink: 1 },
  eyebrow: { color: o.color.primaryPressed, fontSize: o.type.caption, fontWeight: '900', letterSpacing: 1.1 },
  title: { color: o.color.text, fontSize: o.type.title, fontWeight: '900', marginTop: 3 },
  subtitle: { color: o.color.muted, fontSize: o.type.small, marginTop: 3 },
  refreshButton: { minHeight: o.touch.minimum, justifyContent: 'center', borderWidth: 1, borderColor: o.color.primary, borderRadius: o.radius.control, paddingHorizontal: o.space[4], paddingVertical: o.space[2], backgroundColor: o.color.surface },
  refreshButtonText: { color: o.color.primaryPressed, fontWeight: '900', fontSize: o.type.small },
  errorBanner: { flexDirection: 'row', justifyContent: 'space-between', gap: o.space[3], padding: o.space[3], borderRadius: o.radius.control, borderWidth: 1, borderLeftWidth: 5, borderColor: o.color.critical, backgroundColor: o.color.criticalSurface, marginBottom: o.space[2] },
  errorText: { color: o.color.critical, flex: 1, fontSize: o.type.small, fontWeight: '800' },
  noticeBanner: { flexDirection: 'row', justifyContent: 'space-between', gap: o.space[3], padding: o.space[3], borderRadius: o.radius.control, borderWidth: 1, borderLeftWidth: 5, borderColor: o.color.success, backgroundColor: o.color.successSurface, marginBottom: o.space[2] },
  noticeText: { color: o.color.success, flex: 1, fontSize: o.type.small, fontWeight: '800' },
  bannerClose: { color: o.color.muted, fontSize: 20, fontWeight: '900' },
  tabs: { flexDirection: 'row', gap: o.space[2], marginBottom: o.space[3] },
  tab: { minHeight: o.touch.minimum, justifyContent: 'center', paddingHorizontal: o.space[4], paddingVertical: o.space[2], borderWidth: 1, borderColor: o.color.borderStrong, borderRadius: o.radius.control, backgroundColor: o.color.surface },
  tabActive: { borderColor: o.color.primary, backgroundColor: o.color.selected, borderBottomWidth: 4 },
  tabText: { color: o.color.muted, fontSize: o.type.small, fontWeight: '900' },
  tabTextActive: { color: o.color.primaryPressed },
  workspace: { flex: 1, minHeight: 0, flexDirection: 'row', gap: o.space[3] },
  workspaceCompact: { flexDirection: 'column' },
  listScroll: { flex: 0.42 },
  listScrollCompact: { flex: 0, maxHeight: 300 },
  list: { gap: o.space[2], paddingBottom: o.space[6] },
  taskCard: { padding: o.space[4], borderWidth: 1, borderColor: o.color.border, borderRadius: o.radius.panel, backgroundColor: o.color.surface, shadowColor: o.color.shadow, shadowOpacity: 0.08, shadowRadius: 4, shadowOffset: { width: 0, height: 2 }, elevation: 2 },
  taskCardActive: { borderColor: o.color.primary, borderLeftWidth: 6, backgroundColor: o.color.selected },
  taskTopline: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: o.space[2] },
  taskType: { color: o.color.text, fontSize: o.type.body, fontWeight: '900', flex: 1 },
  taskFolio: { marginTop: o.space[3] },
  taskCommitment: { alignSelf: 'flex-start', marginVertical: o.space[2] },
  taskInstruction: { color: o.color.text, fontSize: o.type.small, lineHeight: 20, marginTop: o.space[2] },
  taskFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: o.space[2], marginTop: o.space[3] },
  taskMeta: { color: o.color.muted, fontSize: o.type.caption },
  taskActions: { flexDirection: 'row', gap: o.space[2], flexWrap: 'wrap', justifyContent: 'flex-end' },
  executeButton: { minHeight: o.touch.minimum, justifyContent: 'center', paddingHorizontal: o.space[4], paddingVertical: o.space[2], borderRadius: o.radius.control, backgroundColor: o.color.primary },
  executeButtonText: { color: o.color.onPrimary, fontSize: o.type.small, fontWeight: '900' },
  releaseSmall: { minHeight: o.touch.minimum, justifyContent: 'center', paddingHorizontal: o.space[3], paddingVertical: o.space[2], borderRadius: o.radius.control, borderWidth: 1, borderColor: o.color.critical, backgroundColor: o.color.surface },
  releaseSmallText: { color: o.color.critical, fontSize: o.type.small, fontWeight: '900' },
  executionPanel: { flex: 0.58, minWidth: 0, padding: o.space[4], borderRadius: o.radius.panel, borderWidth: 1, borderColor: o.color.border, backgroundColor: o.color.surface, shadowColor: o.color.shadow, shadowOpacity: 0.08, shadowRadius: 4, shadowOffset: { width: 0, height: 2 }, elevation: 2 },
  executionPanelCompact: { flex: 1 },
  executionHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: o.space[2] },
  executionTitleWrap: { flex: 1 },
  executionTitle: { color: o.color.text, fontSize: o.type.heading, fontWeight: '900', marginTop: 3 },
  routeCard: { marginTop: o.space[3], padding: o.space[3], borderRadius: o.radius.control, borderWidth: 1, borderColor: o.color.border, backgroundColor: o.color.surfaceMuted, gap: o.space[1] },
  step: { flexDirection: 'row', gap: o.space[3], marginTop: o.space[3], padding: o.space[3], borderRadius: o.radius.control, borderWidth: 1, borderColor: o.color.border, backgroundColor: o.color.surfaceMuted },
  stepDisabled: { opacity: 0.45 },
  stepNumber: { width: 34, height: 34, borderRadius: 17, borderWidth: 2, borderColor: o.color.primary, alignItems: 'center', justifyContent: 'center', backgroundColor: o.color.surface },
  stepNumberComplete: { backgroundColor: o.color.successSurface, borderColor: o.color.success },
  stepNumberText: { color: o.color.primaryPressed, fontSize: o.type.small, fontWeight: '900' },
  stepNumberTextComplete: { color: o.color.success },
  stepBody: { flex: 1, gap: o.space[2] },
  stepTitle: { color: o.color.text, fontSize: o.type.body, fontWeight: '900' },
  secondaryButton: { minHeight: o.touch.minimum, alignSelf: 'flex-start', justifyContent: 'center', paddingHorizontal: o.space[4], paddingVertical: o.space[2], borderRadius: o.radius.control, borderWidth: 1, borderColor: o.color.primary, backgroundColor: o.color.surface },
  secondaryButtonText: { color: o.color.primaryPressed, fontSize: o.type.small, fontWeight: '900' },
  primaryButton: { minHeight: o.touch.prominent, paddingHorizontal: o.space[4], paddingVertical: o.space[3], borderRadius: o.radius.control, backgroundColor: o.color.success, alignItems: 'center', justifyContent: 'center' },
  primaryButtonText: { color: o.color.onPrimary, fontSize: o.type.body, fontWeight: '900' },
  releaseButton: { minHeight: o.touch.minimum, alignSelf: 'flex-start', justifyContent: 'center', paddingHorizontal: o.space[3], paddingVertical: o.space[2], borderRadius: o.radius.control, borderWidth: 1, borderColor: o.color.critical, backgroundColor: o.color.surface },
  releaseButtonText: { color: o.color.critical, fontSize: o.type.small, fontWeight: '900' },
  mismatchButton: { minHeight: o.touch.minimum, alignSelf: 'flex-start', justifyContent: 'center', paddingHorizontal: o.space[4], paddingVertical: o.space[2], borderRadius: o.radius.control, borderWidth: 1, borderColor: o.color.critical, backgroundColor: o.color.criticalSurface },
  mismatchButtonText: { color: o.color.critical, fontSize: o.type.small, fontWeight: '900' },
  buttonDisabled: { opacity: 0.4 },
  destinationHint: { color: o.color.muted, fontSize: o.type.small, lineHeight: 20 },
  pointOfNoReturnCopy: { color: o.color.warning, fontSize: o.type.small, lineHeight: 20, fontWeight: '700' },
  pendingFlow: { marginTop: o.space[3], padding: o.space[3], borderWidth: 1, borderColor: o.color.warning, borderRadius: o.radius.control, backgroundColor: o.color.warningSurface },
  pendingFlowTitle: { color: o.color.warning, fontWeight: '900', fontSize: o.type.small },
  pendingFlowCopy: { color: o.color.text, fontSize: o.type.small, lineHeight: 20, marginTop: o.space[1] },
  executionEmpty: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: o.space[6] },
  emptyList: { padding: o.space[6], alignItems: 'center' },
  emptyStandalone: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: o.space[8], backgroundColor: o.color.canvas },
  emptyIcon: { color: o.color.primary, fontSize: 34, fontWeight: '900' },
  emptyTitle: { color: o.color.text, fontSize: o.type.body, fontWeight: '900', marginTop: o.space[2], textAlign: 'center' },
  emptyCopy: { color: o.color.muted, fontSize: o.type.small, lineHeight: 20, marginTop: o.space[1], textAlign: 'center', maxWidth: 520 },
  busyOverlay: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(18,51,66,0.82)', alignItems: 'center', justifyContent: 'center', gap: o.space[3] },
  busyText: { color: o.color.onNavy, fontSize: o.type.small, fontWeight: '900' },
});
