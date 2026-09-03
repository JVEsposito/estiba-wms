import * as Crypto from 'expo-crypto';
import { useEffect, useMemo, useRef, useState } from 'react';
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
import { AuthSession, CameraPlan, LocatePayload, MovePayload } from '../domain/estiba';
import {
  folioScanMatches,
  OperationalTask,
  operationalTaskLabel,
  operationalTaskPositionLabel,
  positionScanMatches,
} from '../domain/operationalTasks';
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

  const secondsRemaining = useMemo(() => {
    const expiresAt = activeTask?.reserva?.vence_at;
    if (!expiresAt) return null;
    return Math.max(0, Math.floor((new Date(expiresAt).getTime() - clock) / 1000));
  }, [activeTask?.reserva?.vence_at, clock]);

  useEffect(() => {
    void loadTasks();
  }, [taskApi, auth.token]);

  useEffect(() => {
    if (!activeTask?.reserva?.vence_at) return undefined;
    const interval = setInterval(() => setClock(Date.now()), 1_000);
    return () => clearInterval(interval);
  }, [activeTask?.id, activeTask?.reserva?.vence_at]);

  useEffect(() => {
    if (!taskApi || !activeTask?.reserva || secondsRemaining === 0) return undefined;
    const interval = setInterval(() => void renewActiveTask(), 4 * 60_000);
    return () => clearInterval(interval);
  }, [taskApi, activeTask?.id, activeTask?.reserva?.id, secondsRemaining === 0]);

  useEffect(() => {
    if (secondsRemaining !== 0 || !activeTask) return;
    setError('La reserva venció. Actualiza la bandeja y vuelve a tomar la tarea si continúa disponible.');
    setFolioVerified(false);
    setPositionVerified(false);
  }, [secondsRemaining, activeTask?.id]);

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
      setNotice(`Tarea ${operationalTaskLabel(taken.plan.tipo)} reservada para esta tablet.`);
      await loadTasks({ quiet: true });
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    } finally {
      setBusy(false);
    }
  }

  async function renewActiveTask() {
    if (!taskApi || !activeTask) return;
    try {
      const renewed = await taskApi.renew(auth.token, activeTask.id);
      setActiveTask(renewed);
      setMine((current) => current.map((task) => task.id === renewed.id ? renewed : task));
      setClock(Date.now());
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    }
  }

  function requestRelease(task: OperationalTask) {
    Alert.alert(
      'Liberar tarea',
      'La tarea volverá a la bandeja y el destino reservado quedará disponible.',
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Liberar', style: 'destructive', onPress: () => void releaseTask(task) },
      ],
    );
  }

  async function releaseTask(task: OperationalTask) {
    if (!taskApi) return;
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

  function beginTask(task: OperationalTask) {
    setActiveTask(task);
    setError('');
    setNotice('');
    resetScans();
    if ((task.reserva?.segundos_restantes ?? 0) < 180) void renewActiveTaskById(task.id);
  }

  async function renewActiveTaskById(taskId: string) {
    if (!taskApi) return;
    try {
      const renewed = await taskApi.renew(auth.token, taskId);
      setActiveTask(renewed);
      setMine((current) => current.map((task) => task.id === renewed.id ? renewed : task));
      setClock(Date.now());
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    }
  }

  function verifyFolio() {
    if (!activeTask) return;
    if (!folioScanMatches(activeTask, folioScan)) {
      setFolioVerified(false);
      setPositionVerified(false);
      setError(`Folio incorrecto. Esperado: ${activeTask.folio.numero_folio}. Escaneado: ${folioScan.trim() || 'sin lectura'}.`);
      return;
    }

    setFolioVerified(true);
    setError('');
    setNotice(`Folio ${activeTask.folio.numero_folio} verificado.`);
  }

  function verifyPosition() {
    if (!activeTask?.destino?.posicion) return;
    if (!positionScanMatches(activeTask, positionScan)) {
      setPositionVerified(false);
      setError(
        `Posición incorrecta. Destino esperado: ${operationalTaskPositionLabel(activeTask.destino)}. Escaneado: ${positionScan.trim() || 'sin lectura'}.`,
      );
      return;
    }

    setPositionVerified(true);
    setError('');
    setNotice(`Destino ${operationalTaskPositionLabel(activeTask.destino)} verificado.`);
  }

  async function completeTask() {
    if (!activeTask || !folioVerified || !positionVerified || secondsRemaining === 0) return;
    const task = activeTask;
    const destination = task.destino;
    if (!destination?.posicion) {
      setError('La tarea no posee una posición física de destino y todavía no puede ejecutarse desde la bandeja guiada.');
      return;
    }

    setBusy(true);
    setError('');
    setNotice('');
    const sessions: OpenSession[] = [];

    try {
      const destinationSession = await acquireSession(destination.camara.id, sessions);
      const sourceSession = task.origen?.camara
        ? task.origen.camara.id === destination.camara.id
          ? destinationSession
          : await acquireSession(task.origen.camara.id, sessions)
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
      } else {
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
      }

      setNotice(`Tarea completada: ${task.folio.numero_folio} → ${operationalTaskPositionLabel(task.destino)}.`);
      setActiveTask(null);
      resetScans();
      await loadTasks({ quiet: true });
    } catch (reason) {
      setError(messageFrom(reason));
      await loadTasks({ quiet: true });
    } finally {
      await closeTemporarySessions(sessions);
      setBusy(false);
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
    const session: OpenSession = {
      cameraId,
      sessionId: opened.id,
      openedByTask: true,
      plan,
    };
    sessions.push(session);
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
          <Text style={styles.eyebrow}>OPERACIÓN GUIADA</Text>
          <Text style={styles.title}>Bandeja de labores</Text>
          <Text style={styles.subtitle}>
            {auth.usuario.nombre} · {auth.dispositivo.nombre}
          </Text>
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
        <Pressable
          onPress={() => setTab('mias')}
          style={[styles.tab, tab === 'mias' && styles.tabActive]}
        >
          <Text style={[styles.tabText, tab === 'mias' && styles.tabTextActive]}>
            Mis tareas · {mine.length}
          </Text>
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
              onRelease={tab === 'mias' ? () => requestRelease(task) : undefined}
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
                  ? 'Toma una labor disponible para comenzar.'
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
                  <Text style={styles.eyebrow}>TAREA EN EJECUCIÓN</Text>
                  <Text style={styles.executionTitle}>{operationalTaskLabel(activeTask.plan.tipo)}</Text>
                </View>
                <ReservationBadge seconds={secondsRemaining} />
              </View>

              <View style={styles.routeCard}>
                <RouteLine label="Folio" value={activeTask.folio.numero_folio} strong />
                <RouteLine label="Origen" value={operationalTaskPositionLabel(activeTask.origen)} />
                <RouteLine label="Destino" value={operationalTaskPositionLabel(activeTask.destino)} strong />
                <RouteLine label="Motivo" value={taskReason(activeTask)} />
              </View>

              <Step number="1" title="Escanear folio" complete={folioVerified}>
                <TextInput
                  autoCapitalize="characters"
                  editable={!busy && secondsRemaining !== 0}
                  onChangeText={(value) => {
                    setFolioScan(value);
                    setFolioVerified(false);
                    setPositionVerified(false);
                  }}
                  onSubmitEditing={verifyFolio}
                  placeholder={`Esperado: ${activeTask.folio.numero_folio}`}
                  placeholderTextColor={colors.muted}
                  returnKeyType="done"
                  style={styles.scanInput}
                  value={folioScan}
                />
                <Pressable
                  disabled={!folioScan.trim() || busy || secondsRemaining === 0}
                  onPress={verifyFolio}
                  style={[styles.secondaryButton, (!folioScan.trim() || busy || secondsRemaining === 0) && styles.buttonDisabled]}
                >
                  <Text style={styles.secondaryButtonText}>Verificar folio</Text>
                </Pressable>
              </Step>

              <Step number="2" title="Escanear posición" complete={positionVerified} disabled={!folioVerified}>
                <Text style={styles.destinationHint}>
                  Destino reservado: {operationalTaskPositionLabel(activeTask.destino)}
                </Text>
                <TextInput
                  autoCapitalize="characters"
                  editable={folioVerified && !busy && secondsRemaining !== 0}
                  onChangeText={(value) => {
                    setPositionScan(value);
                    setPositionVerified(false);
                  }}
                  onSubmitEditing={verifyPosition}
                  placeholder="Escanea la posición de destino"
                  placeholderTextColor={colors.muted}
                  returnKeyType="done"
                  style={[styles.scanInput, !folioVerified && styles.inputDisabled]}
                  value={positionScan}
                />
                <Pressable
                  disabled={!folioVerified || !positionScan.trim() || busy || secondsRemaining === 0}
                  onPress={verifyPosition}
                  style={[
                    styles.secondaryButton,
                    (!folioVerified || !positionScan.trim() || busy || secondsRemaining === 0) && styles.buttonDisabled,
                  ]}
                >
                  <Text style={styles.secondaryButtonText}>Verificar posición</Text>
                </Pressable>
              </Step>

              <Step number="3" title="Confirmar movimiento" complete={false} disabled={!positionVerified}>
                <Pressable
                  disabled={!folioVerified || !positionVerified || busy || secondsRemaining === 0}
                  onPress={() => void completeTask()}
                  style={[
                    styles.primaryButton,
                    (!folioVerified || !positionVerified || busy || secondsRemaining === 0) && styles.buttonDisabled,
                  ]}
                >
                  <Text style={styles.primaryButtonText}>CONFIRMAR MOVIMIENTO</Text>
                </Pressable>
                <Pressable disabled={busy} onPress={() => requestRelease(activeTask)} style={styles.releaseButton}>
                  <Text style={styles.releaseButtonText}>Liberar tarea</Text>
                </Pressable>
              </Step>
            </>
          ) : (
            <View style={styles.executionEmpty}>
              <Text style={styles.emptyIcon}>⇢</Text>
              <Text style={styles.emptyTitle}>Selecciona una tarea propia</Text>
              <Text style={styles.emptyCopy}>
                La ejecución guiada verificará folio y destino antes de enviar el movimiento al servidor.
              </Text>
            </View>
          )}
        </View>
      </View>

      {busy ? (
        <View pointerEvents="none" style={styles.busyOverlay}>
          <ActivityIndicator color={colors.cyan} size="large" />
          <Text style={styles.busyText}>Sincronizando…</Text>
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
  return (
    <View style={[styles.taskCard, active && styles.taskCardActive]}>
      <View style={styles.taskTopline}>
        <Text style={styles.taskType}>{operationalTaskLabel(task.plan.tipo)}</Text>
        <PriorityBadge priority={task.prioridad} />
      </View>
      <Text style={styles.taskFolio}>{task.folio.numero_folio}</Text>
      <Text style={styles.taskRoute}>Origen · {operationalTaskPositionLabel(task.origen)}</Text>
      <Text style={styles.taskRoute}>Destino · {operationalTaskPositionLabel(task.destino)}</Text>
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
              <Text style={styles.executeButtonText}>Ejecutar</Text>
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

function ReservationBadge({ seconds }: { seconds: number | null }) {
  if (seconds === null) return <Text style={[styles.reservation, styles.reservationExpired]}>SIN RESERVA</Text>;
  const expired = seconds <= 0;
  const warning = seconds > 0 && seconds < 180;
  return (
    <Text style={[
      styles.reservation,
      expired ? styles.reservationExpired : warning ? styles.reservationWarning : styles.reservationActive,
    ]}>
      {expired ? 'RESERVA VENCIDA' : `RESERVA ${formatDuration(seconds)}`}
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
  children: React.ReactNode;
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

function messageFrom(reason: unknown) {
  if (reason instanceof ApiError) {
    if (reason.status === 409) return `${reason.message} La bandeja se actualizará para evitar operar sobre una reserva antigua.`;
    return reason.message;
  }
  return reason instanceof Error ? reason.message : 'La operación no pudo completarse.';
}

function formatDuration(seconds: number) {
  const minutes = Math.floor(seconds / 60);
  const remainder = seconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background, padding: 14 },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginBottom: 12 },
  headerCopy: { flex: 1 },
  eyebrow: { color: colors.cyan, fontSize: 9, fontWeight: '900', letterSpacing: 1.3 },
  title: { color: colors.text, fontSize: 25, fontWeight: '900', marginTop: 4 },
  subtitle: { color: colors.muted, fontSize: 11, marginTop: 3 },
  refreshButton: { paddingHorizontal: 14, paddingVertical: 9, borderRadius: 9, borderWidth: 1, borderColor: colors.cyanDark, backgroundColor: colors.panel },
  refreshButtonText: { color: colors.cyan, fontWeight: '900', fontSize: 10 },
  errorBanner: { flexDirection: 'row', justifyContent: 'space-between', gap: 12, padding: 11, marginBottom: 9, borderRadius: 9, borderWidth: 1, borderColor: colors.red, backgroundColor: colors.blocked },
  noticeBanner: { flexDirection: 'row', justifyContent: 'space-between', gap: 12, padding: 11, marginBottom: 9, borderRadius: 9, borderWidth: 1, borderColor: colors.greenDark, backgroundColor: '#10281D' },
  errorText: { flex: 1, color: colors.text, fontSize: 11, fontWeight: '800' },
  noticeText: { flex: 1, color: colors.green, fontSize: 11, fontWeight: '800' },
  bannerClose: { color: colors.muted, fontSize: 17, fontWeight: '900' },
  tabs: { flexDirection: 'row', gap: 7, marginBottom: 11 },
  tab: { paddingHorizontal: 15, paddingVertical: 9, borderRadius: 9, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  tabActive: { borderColor: colors.cyanDark, backgroundColor: colors.selected },
  tabText: { color: colors.muted, fontSize: 10, fontWeight: '900' },
  tabTextActive: { color: colors.cyan },
  workspace: { flex: 1, flexDirection: 'row', gap: 12, minHeight: 0 },
  listScroll: { flex: 0.9 },
  list: { gap: 9, paddingBottom: 20 },
  taskCard: { padding: 13, borderRadius: 12, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  taskCardActive: { borderColor: colors.cyan, backgroundColor: colors.selected },
  taskTopline: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  taskType: { flex: 1, color: colors.text, fontSize: 13, fontWeight: '900' },
  taskFolio: { color: colors.cyan, fontSize: 20, fontWeight: '900', marginTop: 8 },
  taskRoute: { color: colors.text, fontSize: 10, fontWeight: '700', marginTop: 5 },
  taskInstruction: { color: colors.muted, fontSize: 10, lineHeight: 15, marginTop: 7 },
  taskFooter: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8, marginTop: 11 },
  taskMeta: { color: colors.muted, fontSize: 9, fontWeight: '800' },
  taskActions: { flexDirection: 'row', gap: 7 },
  priorityBadge: { paddingHorizontal: 7, paddingVertical: 4, borderRadius: 999, overflow: 'hidden', fontSize: 8, fontWeight: '900' },
  priorityCritical: { color: colors.text, backgroundColor: colors.blocked },
  priorityHigh: { color: colors.amber, backgroundColor: colors.amberDark },
  priorityNormal: { color: colors.cyan, backgroundColor: colors.selected },
  executeButton: { paddingHorizontal: 12, paddingVertical: 8, borderRadius: 8, backgroundColor: colors.cyan },
  executeButtonText: { color: colors.accentText, fontSize: 9, fontWeight: '900' },
  releaseSmall: { paddingHorizontal: 10, paddingVertical: 8, borderRadius: 8, borderWidth: 1, borderColor: colors.red },
  releaseSmallText: { color: colors.red, fontSize: 9, fontWeight: '900' },
  executionPanel: { flex: 1.25, padding: 14, borderRadius: 13, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep },
  executionHeader: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 10 },
  executionTitleWrap: { flex: 1 },
  executionTitle: { color: colors.text, fontSize: 20, fontWeight: '900', marginTop: 3 },
  reservation: { paddingHorizontal: 9, paddingVertical: 6, borderRadius: 8, overflow: 'hidden', fontSize: 9, fontWeight: '900' },
  reservationActive: { color: colors.green, backgroundColor: colors.greenDark },
  reservationWarning: { color: colors.amber, backgroundColor: colors.amberDark },
  reservationExpired: { color: colors.red, backgroundColor: colors.blocked },
  routeCard: { gap: 7, padding: 11, marginTop: 12, marginBottom: 12, borderRadius: 10, borderWidth: 1, borderColor: colors.borderSoft, backgroundColor: colors.panel },
  routeLine: { flexDirection: 'row', gap: 10 },
  routeLabel: { width: 55, color: colors.muted, fontSize: 9, fontWeight: '900', textTransform: 'uppercase' },
  routeValue: { flex: 1, color: colors.text, fontSize: 10, fontWeight: '700' },
  routeValueStrong: { color: colors.cyan, fontWeight: '900' },
  step: { flexDirection: 'row', gap: 10, paddingVertical: 9, borderTopWidth: 1, borderTopColor: colors.borderSoft },
  stepDisabled: { opacity: 0.55 },
  stepNumber: { width: 27, height: 27, borderRadius: 999, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: colors.cyanDark, backgroundColor: colors.panel },
  stepNumberComplete: { borderColor: colors.greenDark, backgroundColor: colors.greenDark },
  stepNumberText: { color: colors.cyan, fontSize: 11, fontWeight: '900' },
  stepNumberTextComplete: { color: colors.green },
  stepBody: { flex: 1, gap: 7 },
  stepTitle: { color: colors.text, fontSize: 12, fontWeight: '900' },
  scanInput: { minHeight: 42, paddingHorizontal: 12, borderRadius: 8, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panelStrong, color: colors.text, fontSize: 15, fontWeight: '900' },
  inputDisabled: { opacity: 0.5 },
  destinationHint: { color: colors.cyan, fontSize: 10, fontWeight: '800' },
  secondaryButton: { alignSelf: 'flex-start', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 8, borderWidth: 1, borderColor: colors.cyanDark },
  secondaryButtonText: { color: colors.cyan, fontSize: 9, fontWeight: '900' },
  primaryButton: { minHeight: 44, alignItems: 'center', justifyContent: 'center', borderRadius: 9, backgroundColor: colors.cyan },
  primaryButtonText: { color: colors.accentText, fontSize: 11, fontWeight: '900' },
  buttonDisabled: { opacity: 0.4 },
  releaseButton: { alignSelf: 'flex-start', paddingVertical: 5 },
  releaseButtonText: { color: colors.red, fontSize: 9, fontWeight: '900' },
  emptyList: { minHeight: 210, alignItems: 'center', justifyContent: 'center', padding: 20, borderRadius: 12, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  executionEmpty: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  emptyStandalone: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28, backgroundColor: colors.background },
  emptyIcon: { color: colors.cyan, fontSize: 35, fontWeight: '900' },
  emptyTitle: { color: colors.text, fontSize: 16, fontWeight: '900', marginTop: 9, textAlign: 'center' },
  emptyCopy: { maxWidth: 440, color: colors.muted, fontSize: 11, lineHeight: 17, marginTop: 6, textAlign: 'center' },
  busyOverlay: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center', gap: 8, backgroundColor: 'rgba(8,12,16,0.78)' },
  busyText: { color: colors.text, fontSize: 10, fontWeight: '900' },
});
