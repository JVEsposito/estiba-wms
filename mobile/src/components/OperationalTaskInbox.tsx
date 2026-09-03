import * as Crypto from 'expo-crypto';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
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
  folioScanMatches,
  OperationalTask,
  operationalTaskDestinationLabel,
  operationalTaskLabel,
  operationalTaskPositionLabel,
  positionScanMatches,
} from '../domain/operationalTasks';
import { calculateRollingFrontier } from '../domain/rollingPlanner';
import { useOperationalPolling } from '../hooks/useOperationalPolling';
import { ApiError } from '../services/apiError';
import { EstibaApi } from '../services/estibaApi';
import { OperationalTasksApi } from '../services/operationalTasksApi';
import { colors } from '../theme/colors';

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
  const taskApi = useMemo(
    () => api.mode === 'connected' && api.baseUrl ? new OperationalTasksApi(api.baseUrl) : null,
    [api.baseUrl, api.mode],
  );
  const [available, setAvailable] = useState<OperationalTask[]>([]);
  const [mine, setMine] = useState<OperationalTask[]>([]);
  const [tab, setTab] = useState<TaskTab>('mias');
  const [activeTask, setActiveTask] = useState<OperationalTask | null>(null);
  const [folioScan, setFolioScan] = useState('');
  const [positionScan, setPositionScan] = useState('');
  const [folioVerified, setFolioVerified] = useState(false);
  const [positionVerified, setPositionVerified] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [clock, setClock] = useState(Date.now());
  const initialLoad = useRef(true);
  const loadInFlight = useRef(false);
  const executionSessions = useRef<OpenSession[]>([]);

  const inPhysicalMovement = activeTask?.estado === 'en_proceso';
  const secondsRemaining = useMemo(() => {
    if (!activeTask?.reserva || activeTask.estado === 'en_proceso') return null;
    const expiresAt = activeTask.reserva.vence_at;
    if (!expiresAt) return null;
    return Math.max(0, Math.floor((new Date(expiresAt).getTime() - clock) / 1000));
  }, [activeTask?.estado, activeTask?.reserva, clock]);
  const leaseExpired = Boolean(activeTask && !inPhysicalMovement && secondsRemaining === 0);
  const hasPhysicalDestination = Boolean(
    activeTask?.destino?.posicion && activeTask?.reserva?.tipo_compromiso === 'fisica',
  );

  useEffect(() => {
    void loadTasks();
  }, [taskApi, auth.token]);

  useEffect(() => {
    if (!activeTask?.reserva?.vence_at || activeTask.estado === 'en_proceso') return undefined;
    const interval = setInterval(() => setClock(Date.now()), 1_000);
    return () => clearInterval(interval);
  }, [activeTask?.id, activeTask?.estado, activeTask?.reserva?.vence_at]);

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
    setFolioVerified(false);
    setPositionVerified(false);
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
      resetScans();
      setNotice(
        taken.tipo_movimiento === 'retiro'
          ? `Tarea crítica tomada. Destino directo: ${operationalTaskDestinationLabel(taken)}.`
          : taken.reserva?.tipo_compromiso === 'fisica'
          ? `Tarea ${operationalTaskLabel(taken.plan.tipo)} tomada con destino físico reservado.`
          : `Tarea ${operationalTaskLabel(taken.plan.tipo)} tomada. El destino se calculará con el snapshot vigente.`,
      );
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
    resetScans();
    if (task.estado === 'en_proceso') {
      setFolioVerified(true);
      setNotice('Movimiento ya iniciado: el destino está fijo hasta completar o registrar una incidencia.');
      return;
    }
    if ((task.reserva?.segundos_restantes ?? 600) < 180) void renewActiveTaskById(task.id);
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
        resetScans();
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

  async function verifyFolio() {
    if (!activeTask || activeTask.estado === 'en_proceso') return;
    if (!folioScanMatches(activeTask, folioScan)) {
      setFolioVerified(false);
      setPositionVerified(false);
      setError(
        `Folio incorrecto. Esperado: ${activeTask.folio.numero_folio}. Escaneado: ${folioScan.trim() || 'sin lectura'}.`,
      );
      return;
    }

    setFolioVerified(true);
    setError('');
    setNotice(`Folio ${activeTask.folio.numero_folio} verificado. Calculando frontera con el estado vigente…`);

    if (activeTask.tipo_movimiento === 'retiro') {
      setNotice(`Folio verificado. Destino prioritario: ${operationalTaskDestinationLabel(activeTask)}.`);
      return;
    }
    if (activeTask.reserva?.tipo_compromiso === 'fisica' && activeTask.destino?.posicion) {
      setNotice(`Destino físico vigente: ${operationalTaskPositionLabel(activeTask.destino)}.`);
      return;
    }

    await calculateAndMaterializeFrontier(activeTask);
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
    if (!taskApi || !activeTask || !folioVerified || activeTask.estado === 'en_proceso') return;
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

  function verifyPosition() {
    if (!activeTask?.destino?.posicion || activeTask.estado !== 'en_proceso') return;
    if (!positionScanMatches(activeTask, positionScan)) {
      setPositionVerified(false);
      setError(
        `Posición incorrecta. Destino fijo: ${operationalTaskPositionLabel(activeTask.destino)}. `
        + `Escaneado: ${positionScan.trim() || 'sin lectura'}.`,
      );
      return;
    }

    setPositionVerified(true);
    setError('');
    setNotice(`Destino ${operationalTaskPositionLabel(activeTask.destino)} verificado.`);
  }

  async function completeTask() {
    if (!activeTask || activeTask.estado !== 'en_proceso' || !positionVerified) return;
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
      setActiveTask(null);
      resetScans();
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

  async function completeDirectWithdrawal() {
    if (!activeTask
      || activeTask.estado !== 'en_proceso'
      || activeTask.tipo_movimiento !== 'retiro'
      || !activeTask.destino_logico?.carga_folio_id) return;

    const task = activeTask;
    const directDestination = activeTask.destino_logico;
    const sessions = executionSessions.current.length
      ? executionSessions.current
      : [];
    setBusy(true);
    setError('');
    setNotice('');

    try {
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
      setNotice(
        `Retiro completado: ${task.folio.numero_folio} entregado en ${directDestination.nombre}.`,
      );
      setActiveTask(null);
      resetScans();
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

  async function acquireExecutionSessions(task: OperationalTask, sessions: OpenSession[]) {
    if (task.tipo_movimiento === 'retiro') {
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

  function resetScans() {
    setFolioScan('');
    setPositionScan('');
    setFolioVerified(false);
    setPositionVerified(false);
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

      <View style={styles.workspace}>
        <ScrollView contentContainerStyle={styles.list} style={styles.listScroll}>
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

        <View style={styles.executionPanel}>
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
                <RouteLine label="Folio" value={activeTask.folio.numero_folio} strong />
                <RouteLine label="Origen" value={operationalTaskPositionLabel(activeTask.origen)} />
                <RouteLine label="Destino" value={operationalTaskDestinationLabel(activeTask)} strong />
                <RouteLine label="Motivo" value={taskReason(activeTask)} />
              </View>

              {!inPhysicalMovement ? (
                <Step number="1" title="Escanear folio" complete={folioVerified}>
                  <TextInput
                    autoCapitalize="characters"
                    editable={!busy && !leaseExpired}
                    onChangeText={(value) => {
                      setFolioScan(value);
                      setFolioVerified(false);
                      setPositionVerified(false);
                    }}
                    onSubmitEditing={() => void verifyFolio()}
                    placeholder={`Esperado: ${activeTask.folio.numero_folio}`}
                    placeholderTextColor={colors.muted}
                    returnKeyType="done"
                    style={styles.scanInput}
                    value={folioScan}
                  />
                  <Pressable
                    disabled={!folioScan.trim() || busy || leaseExpired}
                    onPress={() => void verifyFolio()}
                    style={[styles.secondaryButton, (!folioScan.trim() || busy || leaseExpired) && styles.buttonDisabled]}
                  >
                    <Text style={styles.secondaryButtonText}>
                      {activeTask.tipo_movimiento === 'retiro' ? 'Verificar folio' : 'Verificar y calcular'}
                    </Text>
                  </Pressable>
                </Step>
              ) : null}

              {!inPhysicalMovement && activeTask.tipo_movimiento !== 'retiro' ? (
                <Step number="2" title="Materializar frontera" complete={hasPhysicalDestination} disabled={!folioVerified}>
                  <Text style={styles.destinationHint}>
                    {hasPhysicalDestination
                      ? `Servidor reservó: ${operationalTaskPositionLabel(activeTask.destino)}`
                      : 'La tablet calcula candidatos; el servidor valida y reserva solo la frontera inmediata.'}
                  </Text>
                  {!hasPhysicalDestination ? (
                    <Pressable
                      disabled={!folioVerified || busy || leaseExpired}
                      onPress={() => void calculateAndMaterializeFrontier(activeTask)}
                      style={[styles.secondaryButton, (!folioVerified || busy || leaseExpired) && styles.buttonDisabled]}
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
                  disabled={!folioVerified || (activeTask.tipo_movimiento !== 'retiro' && !hasPhysicalDestination)}
                >
                  <Text style={styles.pointOfNoReturnCopy}>
                    Esta acción marca el pallet como físicamente en movimiento. Desde aquí el destino queda fijo.
                  </Text>
                  <Pressable
                    disabled={!folioVerified || (activeTask.tipo_movimiento !== 'retiro' && !hasPhysicalDestination) || busy || leaseExpired}
                    onPress={() => void startPhysicalTask()}
                    style={[
                      styles.primaryButton,
                      (!folioVerified || (activeTask.tipo_movimiento !== 'retiro' && !hasPhysicalDestination) || busy || leaseExpired) && styles.buttonDisabled,
                    ]}
                  >
                    <Text style={styles.primaryButtonText}>RETIRAR PALLET · INICIAR MOVIMIENTO</Text>
                  </Pressable>
                  <Pressable disabled={busy} onPress={() => requestRelease(activeTask)} style={styles.releaseButton}>
                    <Text style={styles.releaseButtonText}>Liberar antes de iniciar</Text>
                  </Pressable>
                </Step>
              ) : null}

              {inPhysicalMovement && activeTask.tipo_movimiento !== 'retiro' ? (
                <>
                  <Step number="4" title="Escanear destino fijo" complete={positionVerified}>
                    <Text style={styles.destinationHint}>
                      Destino comprometido: {operationalTaskPositionLabel(activeTask.destino)}
                    </Text>
                    <TextInput
                      autoCapitalize="characters"
                      editable={!busy}
                      onChangeText={(value) => {
                        setPositionScan(value);
                        setPositionVerified(false);
                      }}
                      onSubmitEditing={verifyPosition}
                      placeholder="Escanea la posición de destino"
                      placeholderTextColor={colors.muted}
                      returnKeyType="done"
                      style={styles.scanInput}
                      value={positionScan}
                    />
                    <Pressable
                      disabled={!positionScan.trim() || busy}
                      onPress={verifyPosition}
                      style={[styles.secondaryButton, (!positionScan.trim() || busy) && styles.buttonDisabled]}
                    >
                      <Text style={styles.secondaryButtonText}>Verificar posición</Text>
                    </Pressable>
                  </Step>

                  <Step number="5" title="Confirmar estado real" complete={false} disabled={!positionVerified}>
                    <Pressable
                      disabled={!positionVerified || busy}
                      onPress={() => void completeTask()}
                      style={[styles.primaryButton, (!positionVerified || busy) && styles.buttonDisabled]}
                    >
                      <Text style={styles.primaryButtonText}>CONFIRMAR MOVIMIENTO</Text>
                    </Pressable>
                    <Text style={styles.pointOfNoReturnCopy}>
                      Si el destino no puede completarse físicamente, no liberes la tarea: debe resolverse como incidencia.
                    </Text>
                  </Step>
                </>
              ) : null}

              {inPhysicalMovement && activeTask.tipo_movimiento === 'retiro' ? (
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
                    Confirma solo cuando el pallet haya salido físicamente de la cámara y esté en el andén indicado.
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
          <ActivityIndicator color={colors.cyan} size="large" />
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
        <PriorityBadge priority={task.prioridad} />
      </View>
      <Text style={styles.taskFolio}>{task.folio.numero_folio}</Text>
      <Text style={styles.taskCommitment}>{commitment}</Text>
      <Text style={styles.taskRoute}>Origen · {operationalTaskPositionLabel(task.origen)}</Text>
      <Text style={styles.taskRoute}>Destino · {operationalTaskDestinationLabel(task)}</Text>
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

function PriorityBadge({ priority }: { priority: OperationalTask['prioridad'] }) {
  const label = priority === 'critica'
    ? 'CRÍTICA'
    : priority === 'urgente'
      ? 'URGENTE'
      : priority === 'alta'
        ? 'ALTA'
        : 'NORMAL';
  const style = priority === 'critica' || priority === 'urgente'
    ? styles.priorityCritical
    : priority === 'alta'
      ? styles.priorityHigh
      : styles.priorityNormal;

  return <Text style={[styles.priorityBadge, style]}>{label}</Text>;
}

function CommitmentBadge({ task, seconds }: { task: OperationalTask; seconds: number | null }) {
  if (task.estado === 'en_proceso') {
    return <Text style={[styles.reservation, styles.reservationHard]}>EN MOVIMIENTO · DESTINO FIJO</Text>;
  }
  const expired = seconds !== null && seconds <= 0;
  const warning = seconds !== null && seconds > 0 && seconds < 180;
  const prefix = task.reserva?.tipo_compromiso === 'fisica' ? 'FÍSICA' : 'CLAIM';
  return (
    <Text style={[
      styles.reservation,
      expired ? styles.reservationExpired : warning ? styles.reservationWarning : styles.reservationActive,
    ]}>
      {expired ? `${prefix} VENCIDO` : `${prefix} ${seconds === null ? 'ACTIVO' : formatDuration(seconds)}`}
    </Text>
  );
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

function RouteLine({ label, strong = false, value }: { label: string; strong?: boolean; value: string }) {
  return (
    <View style={styles.routeLine}>
      <Text style={styles.routeLabel}>{label}</Text>
      <Text numberOfLines={2} style={[styles.routeValue, strong && styles.routeValueStrong]}>{value}</Text>
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
  screen: { flex: 1, backgroundColor: colors.background, padding: 12 },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginBottom: 10 },
  headerCopy: { flexShrink: 1 },
  eyebrow: { color: colors.cyan, fontSize: 8, fontWeight: '900', letterSpacing: 1.2 },
  title: { color: colors.text, fontSize: 22, fontWeight: '900', marginTop: 3 },
  subtitle: { color: colors.muted, fontSize: 10, marginTop: 3 },
  refreshButton: { borderWidth: 1, borderColor: colors.cyanDark, borderRadius: 9, paddingHorizontal: 13, paddingVertical: 8 },
  refreshButtonText: { color: colors.cyan, fontWeight: '900', fontSize: 10 },
  errorBanner: { flexDirection: 'row', justifyContent: 'space-between', gap: 10, padding: 10, borderRadius: 9, borderWidth: 1, borderColor: colors.red, backgroundColor: colors.blocked, marginBottom: 8 },
  errorText: { color: colors.text, flex: 1, fontSize: 10, fontWeight: '700' },
  noticeBanner: { flexDirection: 'row', justifyContent: 'space-between', gap: 10, padding: 10, borderRadius: 9, borderWidth: 1, borderColor: colors.greenDark, backgroundColor: colors.panel, marginBottom: 8 },
  noticeText: { color: colors.green, flex: 1, fontSize: 10, fontWeight: '700' },
  bannerClose: { color: colors.muted, fontWeight: '900' },
  tabs: { flexDirection: 'row', gap: 7, marginBottom: 9 },
  tab: { paddingHorizontal: 14, paddingVertical: 8, borderWidth: 1, borderColor: colors.border, borderRadius: 9, backgroundColor: colors.panel },
  tabActive: { borderColor: colors.cyanDark, backgroundColor: colors.selected },
  tabText: { color: colors.muted, fontSize: 10, fontWeight: '900' },
  tabTextActive: { color: colors.cyan },
  workspace: { flex: 1, minHeight: 0, flexDirection: 'row', gap: 10 },
  listScroll: { flex: 0.42 },
  list: { gap: 8, paddingBottom: 20 },
  taskCard: { padding: 12, borderWidth: 1, borderColor: colors.border, borderRadius: 12, backgroundColor: colors.panel },
  taskCardActive: { borderColor: colors.cyan, backgroundColor: colors.selected },
  taskTopline: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  taskType: { color: colors.text, fontSize: 11, fontWeight: '900', flex: 1 },
  taskFolio: { color: colors.cyan, fontSize: 19, fontWeight: '900', marginTop: 7 },
  taskCommitment: { color: colors.amber, fontSize: 8, fontWeight: '900', marginTop: 3, letterSpacing: 0.7 },
  taskRoute: { color: colors.muted, fontSize: 9, marginTop: 4 },
  taskInstruction: { color: colors.text, fontSize: 9, lineHeight: 14, marginTop: 7 },
  taskFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8, marginTop: 10 },
  taskMeta: { color: colors.muted, fontSize: 8 },
  taskActions: { flexDirection: 'row', gap: 6 },
  executeButton: { paddingHorizontal: 11, paddingVertical: 7, borderRadius: 8, backgroundColor: colors.cyan },
  executeButtonText: { color: colors.accentText, fontSize: 9, fontWeight: '900' },
  releaseSmall: { paddingHorizontal: 9, paddingVertical: 7, borderRadius: 8, borderWidth: 1, borderColor: colors.red },
  releaseSmallText: { color: colors.red, fontSize: 9, fontWeight: '900' },
  priorityBadge: { paddingHorizontal: 7, paddingVertical: 3, borderRadius: 6, overflow: 'hidden', fontSize: 7, fontWeight: '900' },
  priorityCritical: { color: colors.red, backgroundColor: colors.blocked },
  priorityHigh: { color: colors.amber, backgroundColor: colors.amberDark },
  priorityNormal: { color: colors.cyan, backgroundColor: colors.selected },
  executionPanel: { flex: 0.58, minWidth: 0, padding: 13, borderRadius: 13, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep },
  executionHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  executionTitleWrap: { flex: 1 },
  executionTitle: { color: colors.text, fontSize: 17, fontWeight: '900', marginTop: 3 },
  reservation: { paddingHorizontal: 9, paddingVertical: 5, borderRadius: 7, overflow: 'hidden', fontSize: 8, fontWeight: '900' },
  reservationActive: { color: colors.green, backgroundColor: colors.greenDark },
  reservationWarning: { color: colors.amber, backgroundColor: colors.amberDark },
  reservationExpired: { color: colors.red, backgroundColor: colors.blocked },
  reservationHard: { color: colors.text, backgroundColor: colors.red },
  routeCard: { marginTop: 10, padding: 10, borderRadius: 10, backgroundColor: colors.panel, gap: 5 },
  routeLine: { flexDirection: 'row', gap: 9 },
  routeLabel: { color: colors.muted, width: 54, fontSize: 9, fontWeight: '800' },
  routeValue: { color: colors.text, flex: 1, fontSize: 9 },
  routeValueStrong: { color: colors.cyan, fontWeight: '900' },
  step: { flexDirection: 'row', gap: 10, marginTop: 10, padding: 10, borderRadius: 10, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  stepDisabled: { opacity: 0.45 },
  stepNumber: { width: 25, height: 25, borderRadius: 13, borderWidth: 1, borderColor: colors.cyanDark, alignItems: 'center', justifyContent: 'center' },
  stepNumberComplete: { backgroundColor: colors.greenDark, borderColor: colors.green },
  stepNumberText: { color: colors.cyan, fontSize: 10, fontWeight: '900' },
  stepNumberTextComplete: { color: colors.green },
  stepBody: { flex: 1, gap: 7 },
  stepTitle: { color: colors.text, fontSize: 11, fontWeight: '900' },
  scanInput: { minHeight: 38, paddingHorizontal: 10, borderRadius: 8, borderWidth: 1, borderColor: colors.border, color: colors.text, backgroundColor: colors.background },
  secondaryButton: { alignSelf: 'flex-start', paddingHorizontal: 11, paddingVertical: 7, borderRadius: 8, borderWidth: 1, borderColor: colors.cyanDark },
  secondaryButtonText: { color: colors.cyan, fontSize: 9, fontWeight: '900' },
  primaryButton: { paddingHorizontal: 12, paddingVertical: 10, borderRadius: 8, backgroundColor: colors.cyan, alignItems: 'center' },
  primaryButtonText: { color: colors.accentText, fontSize: 9, fontWeight: '900' },
  releaseButton: { alignSelf: 'flex-start', paddingHorizontal: 10, paddingVertical: 7, borderRadius: 8, borderWidth: 1, borderColor: colors.red },
  releaseButtonText: { color: colors.red, fontSize: 9, fontWeight: '900' },
  buttonDisabled: { opacity: 0.4 },
  destinationHint: { color: colors.muted, fontSize: 9, lineHeight: 14 },
  pointOfNoReturnCopy: { color: colors.amber, fontSize: 9, lineHeight: 14 },
  pendingFlow: { marginTop: 10, padding: 11, borderWidth: 1, borderColor: colors.amberDark, borderRadius: 10, backgroundColor: colors.panel },
  pendingFlowTitle: { color: colors.amber, fontWeight: '900', fontSize: 10 },
  pendingFlowCopy: { color: colors.muted, fontSize: 9, lineHeight: 14, marginTop: 5 },
  executionEmpty: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 20 },
  emptyList: { padding: 24, alignItems: 'center' },
  emptyStandalone: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 30, backgroundColor: colors.background },
  emptyIcon: { color: colors.cyan, fontSize: 30, fontWeight: '900' },
  emptyTitle: { color: colors.text, fontSize: 14, fontWeight: '900', marginTop: 8, textAlign: 'center' },
  emptyCopy: { color: colors.muted, fontSize: 10, lineHeight: 16, marginTop: 5, textAlign: 'center', maxWidth: 430 },
  busyOverlay: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(8,12,16,0.74)', alignItems: 'center', justifyContent: 'center', gap: 10 },
  busyText: { color: colors.text, fontSize: 10, fontWeight: '900' },
});
