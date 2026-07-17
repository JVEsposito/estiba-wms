import * as Crypto from 'expo-crypto';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  AppState,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { AuthSession } from '../domain/estiba';
import {
  PrefrioActionPayload,
  PrefrioFolioCandidate,
  PrefrioMobileCache,
  PrefrioOperationalEventType,
  PrefrioProcess,
  PrefrioQueuedCommand,
  PrefrioTunnel,
} from '../domain/prefrio';
import { ApiError } from '../services/apiError';
import {
  createPrefrioProcess,
  executePrefrioCommand,
  findEligiblePrefrioFolios,
  getPrefrioProcess,
  listEligiblePrefrioFolios,
  listPrefrioProcesses,
  listPrefrioTunnels,
} from '../services/prefrioApi';
import {
  enqueuePrefrioCommand,
  loadPrefrioCache,
  loadPrefrioOutbox,
  markPrefrioCommand,
  removePrefrioCommand,
  savePrefrioCache,
} from '../services/prefrioOfflineStore';
import { colors } from '../theme/colors';

const EMPTY_CACHE: PrefrioMobileCache = {
  tunnels: [],
  processes: [],
  eligible_folios: [],
  synced_at: '',
};

const ACTIVE_STATES = new Set([
  'borrador',
  'cargando',
  'listo_para_iniciar',
  'en_proceso',
  'pendiente_verificacion',
]);

type PrefrioScreenProps = {
  auth: AuthSession;
  baseUrl: string | null;
  onLogout: () => void;
};

type EventDraft = {
  type: PrefrioOperationalEventType;
  title: string;
  requiresTemperature: boolean;
};

export function PrefrioScreen({ auth, baseUrl, onLogout }: PrefrioScreenProps) {
  const scannerRef = useRef<TextInput>(null);
  const flushing = useRef(false);
  const cacheRef = useRef<PrefrioMobileCache>(EMPTY_CACHE);
  const outboxRef = useRef<PrefrioQueuedCommand[]>([]);
  const [cache, setCache] = useState<PrefrioMobileCache>(EMPTY_CACHE);
  const [outbox, setOutbox] = useState<PrefrioQueuedCommand[]>([]);
  const [selectedProcessId, setSelectedProcessId] = useState<string | null>(null);
  const [selectedTunnelId, setSelectedTunnelId] = useState<string | null>(null);
  const [selectedPositionId, setSelectedPositionId] = useState<string | null>(null);
  const [folioNumber, setFolioNumber] = useState('');
  const [initialTemperature, setInitialTemperature] = useState('');
  const [busy, setBusy] = useState(true);
  const [online, setOnline] = useState(Boolean(baseUrl));
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const [eventDraft, setEventDraft] = useState<EventDraft | null>(null);
  const [eventTemperature, setEventTemperature] = useState('');
  const [eventNote, setEventNote] = useState('');
  const [creating, setCreating] = useState(false);
  const [createSetpoint, setCreateSetpoint] = useState('-1.5');
  const [createDuration, setCreateDuration] = useState('720');
  const [createFormat, setCreateFormat] = useState('Granel 5 kg');

  const userId = auth.usuario.id;
  const deviceId = auth.dispositivo.id;
  const canOperate = auth.usuario.capacidades.puede_operar_prefrio === true;
  const selectedProcess = useMemo(
    () => cache.processes.find((item) => item.id === selectedProcessId) ?? null,
    [cache.processes, selectedProcessId],
  );
  const selectedTunnel = useMemo(() => {
    const processTunnelId = selectedProcess?.tunel.id;
    return cache.tunnels.find((item) => item.id === (processTunnelId ?? selectedTunnelId)) ?? null;
  }, [cache.tunnels, selectedProcess, selectedTunnelId]);
  const assignmentsByPosition = useMemo(() => new Map(
    selectedProcess?.folios
      .filter((item) => !['retirado', 'cancelado'].includes(item.estado))
      .map((item) => [item.posicion?.id ?? '', item]) ?? [],
  ), [selectedProcess]);
  const freePositions = useMemo(
    () => selectedTunnel?.posiciones.filter((item) => item.activa && !assignmentsByPosition.has(item.id)) ?? [],
    [selectedTunnel, assignmentsByPosition],
  );
  const selectedPosition = selectedTunnel?.posiciones.find((item) => item.id === selectedPositionId) ?? null;
  const processQueue = outbox.filter((item) => item.process_id === selectedProcessId);
  const unresolved = outbox.filter((item) => item.status !== 'pendiente').length;

  useEffect(() => {
    void initialize();
  }, []);

  useEffect(() => {
    const timer = setInterval(() => void synchronize(), 30000);
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') void synchronize();
    });

    return () => {
      clearInterval(timer);
      subscription.remove();
    };
  }, [baseUrl, auth.token]);

  useEffect(() => {
    if (!selectedProcess && cache.processes.length) {
      const active = cache.processes.find((item) => ACTIVE_STATES.has(item.estado));
      setSelectedProcessId(active?.id ?? cache.processes[0].id);
    }
  }, [cache.processes, selectedProcess]);

  useEffect(() => {
    if (selectedProcess && !selectedPositionId) {
      setSelectedPositionId(freePositions[0]?.id ?? null);
    }
  }, [selectedProcessId, freePositions.length]);

  async function initialize() {
    setBusy(true);
    setError('');
    try {
      const [savedCache, savedOutbox] = await Promise.all([
        loadPrefrioCache(userId, deviceId),
        loadPrefrioOutbox(userId, deviceId),
      ]);
      if (savedCache) replaceCache(savedCache, false);
      replaceOutbox(savedOutbox);
      await synchronize();
    } catch (reason) {
      setError(messageFrom(reason));
    } finally {
      setBusy(false);
    }
  }

  async function synchronize() {
    if (!baseUrl) {
      setOnline(false);
      return;
    }

    try {
      await flushOutbox();
      const [tunnels, processes, eligibleFolios] = await Promise.all([
        listPrefrioTunnels(baseUrl, auth.token),
        listPrefrioProcesses(baseUrl, auth.token),
        listEligiblePrefrioFolios(baseUrl, auth.token),
      ]);
      const next: PrefrioMobileCache = {
        tunnels,
        processes,
        eligible_folios: eligibleFolios,
        synced_at: new Date().toISOString(),
      };
      await replaceCache(next);
      setOnline(true);
      setError('');
    } catch (reason) {
      if (reason instanceof ApiError && reason.status === 401) {
        Alert.alert('Sesión vencida', 'Vuelve a iniciar el turno.');
        onLogout();
        return;
      }
      if (reason instanceof ApiError && reason.status === 0) setOnline(false);
      if (!cacheRef.current.synced_at) setError(messageFrom(reason));
    }
  }

  async function flushOutbox() {
    if (!baseUrl || flushing.current) return;
    flushing.current = true;

    try {
      let items = await loadPrefrioOutbox(userId, deviceId);
      const blockedProcesses = new Set(
        items.filter((item) => item.status === 'conflicto').map((item) => item.process_id),
      );

      for (const item of items.filter((candidate) => candidate.status === 'pendiente')) {
        if (blockedProcesses.has(item.process_id)) continue;

        try {
          const process = await executePrefrioCommand(
            baseUrl,
            auth.token,
            item.route,
            item.payload as Record<string, unknown>,
          );
          items = await removePrefrioCommand(userId, deviceId, item.id);
          await upsertServerProcess(process);
          setOnline(true);
        } catch (reason) {
          if (reason instanceof ApiError && reason.status === 0) {
            setOnline(false);
            break;
          }

          const status = reason instanceof ApiError && reason.status === 409
            ? 'conflicto'
            : 'error';
          items = await markPrefrioCommand(
            userId,
            deviceId,
            item.id,
            status,
            messageFrom(reason),
          );
          if (status === 'conflicto') blockedProcesses.add(item.process_id);
          await refreshProcessAfterFailure(item.process_id);
        }
      }
      replaceOutbox(items);
    } finally {
      flushing.current = false;
    }
  }

  async function refreshProcessAfterFailure(processId: string) {
    if (!baseUrl) return;
    try {
      await upsertServerProcess(await getPrefrioProcess(baseUrl, auth.token, processId));
    } catch {
      // Conserva la última versión conocida hasta la próxima sincronización completa.
    }
  }

  async function replaceCache(next: PrefrioMobileCache, persist = true) {
    cacheRef.current = next;
    setCache(next);
    if (persist) await savePrefrioCache(userId, deviceId, next);
  }

  function replaceOutbox(items: PrefrioQueuedCommand[]) {
    outboxRef.current = items;
    setOutbox(items);
  }

  async function upsertServerProcess(process: PrefrioProcess) {
    const current = cacheRef.current;
    const nextProcesses = [
      process,
      ...current.processes.filter((item) => item.id !== process.id),
    ];
    await replaceCache({ ...current, processes: nextProcesses });
    setSelectedProcessId(process.id);
  }

  async function findCandidate(number: string): Promise<PrefrioFolioCandidate | null> {
    const normalized = number.trim().toUpperCase();
    const cached = cacheRef.current.eligible_folios.find(
      (item) => item.numero_folio.toUpperCase() === normalized,
    );
    if (cached) return cached;
    if (!baseUrl) return null;

    const found = await findEligiblePrefrioFolios(baseUrl, auth.token, normalized);
    const exact = found.find((item) => item.numero_folio.toUpperCase() === normalized) ?? null;
    if (exact) {
      await replaceCache({
        ...cacheRef.current,
        eligible_folios: [
          exact,
          ...cacheRef.current.eligible_folios.filter((item) => item.id !== exact.id),
        ],
      });
    }
    return exact;
  }

  async function addFolio() {
    if (!selectedProcess || !selectedTunnel || !canOperate) return;
    if (!['borrador', 'cargando'].includes(selectedProcess.estado)) {
      setError('El proceso ya no admite nuevos folios.');
      return;
    }

    const position = selectedPosition ?? freePositions[0];
    if (!position) {
      setError('No quedan posiciones disponibles en el túnel.');
      return;
    }

    setBusy(true);
    setError('');
    setNotice('');
    try {
      const folio = await findCandidate(folioNumber);
      if (!folio) {
        setError(online
          ? 'El folio no está habilitado para ingresar a Prefrío.'
          : 'El folio no existe en el catálogo guardado. Sincroniza la PDA para incorporarlo.');
        return;
      }

      const temperature = initialTemperature.trim() === ''
        ? undefined
        : Number(initialTemperature.replace(',', '.'));
      if (temperature !== undefined && !Number.isFinite(temperature)) {
        setError('La temperatura inicial no es válida.');
        return;
      }

      const operationId = Crypto.randomUUID();
      const payload = {
        operacion_id: operationId,
        version_conocida: selectedProcess.version,
        folio_id: folio.id,
        posicion_tunel_prefrio_id: position.id,
        ...(temperature !== undefined ? { temperatura_inicial: temperature } : {}),
        ocurrido_at: new Date().toISOString(),
      };
      const command = commandFor(
        operationId,
        selectedProcess,
        'agregar_folio',
        `Cargar ${folio.numero_folio} en ${position.etiqueta}`,
        `/api/prefrio/procesos/${selectedProcess.id}/folios`,
        payload,
      );
      const optimistic: PrefrioProcess = {
        ...selectedProcess,
        estado: 'cargando',
        version: selectedProcess.version + 1,
        folios: [
          ...selectedProcess.folios,
          {
            id: `local:${operationId}`,
            estado: 'cargado',
            temperatura_inicial: temperature ?? null,
            temperatura_final: null,
            cargado_at: payload.ocurrido_at,
            retirado_at: null,
            motivo_resultado: null,
            observacion: null,
            posicion: position,
            folio: {
              id: folio.id,
              numero_folio: folio.numero_folio,
              tipo_bulto: folio.tipo_bulto,
              estado_operacional: folio.estado_operacional,
              condicion_termica: folio.condicion_termica,
              habilitacion_almacenamiento: folio.habilitacion_almacenamiento,
              variedad: folio.variedad,
              calibre: folio.calibre,
              marca: folio.marca,
              exportadora: folio.exportadora,
            },
            cargado_por: { id: auth.usuario.id, nombre: auth.usuario.nombre },
          },
        ],
      };
      await enqueueAndApply(command, optimistic, folio.id);
      setFolioNumber('');
      setInitialTemperature('');
      setSelectedPositionId(nextFreePositionId(optimistic, selectedTunnel));
      setNotice(`${folio.numero_folio} quedó registrado en ${position.etiqueta}.`);
      setTimeout(() => scannerRef.current?.focus(), 180);
    } catch (reason) {
      setError(messageFrom(reason));
    } finally {
      setBusy(false);
    }
  }

  async function enqueueAndApply(
    command: PrefrioQueuedCommand,
    optimistic: PrefrioProcess,
    removeEligibleFolioId?: string,
  ) {
    const items = await enqueuePrefrioCommand(userId, deviceId, command);
    replaceOutbox(items);
    const current = cacheRef.current;
    await replaceCache({
      ...current,
      processes: [optimistic, ...current.processes.filter((item) => item.id !== optimistic.id)],
      eligible_folios: removeEligibleFolioId
        ? current.eligible_folios.filter((item) => item.id !== removeEligibleFolioId)
        : current.eligible_folios,
    });
    if (baseUrl) await flushOutbox();
  }

  async function queueStateAction(
    kind: PrefrioQueuedCommand['kind'],
    label: string,
    route: string,
    nextState: PrefrioProcess['estado'],
    note?: string,
  ) {
    if (!selectedProcess) return;
    const operationId = Crypto.randomUUID();
    const payload: PrefrioActionPayload = {
      operacion_id: operationId,
      version_conocida: selectedProcess.version,
      ...(note?.trim() ? { observacion: note.trim() } : {}),
      ocurrido_at: new Date().toISOString(),
    };
    const command = commandFor(operationId, selectedProcess, kind, label, route, payload);
    const optimistic: PrefrioProcess = {
      ...selectedProcess,
      estado: nextState,
      version: selectedProcess.version + 1,
      folios: nextState === 'en_proceso'
        ? selectedProcess.folios.map((item) => item.estado === 'cargado'
          ? { ...item, estado: 'en_proceso' as const }
          : item)
        : selectedProcess.folios,
    };
    await enqueueAndApply(command, optimistic);
    setNotice(`${label} quedó registrado${baseUrl ? '.' : ' en la bandeja local.'}`);
  }

  async function registerEvent() {
    if (!selectedProcess || !eventDraft) return;
    const temperature = eventTemperature.trim() === ''
      ? undefined
      : Number(eventTemperature.replace(',', '.'));
    if (eventDraft.requiresTemperature && (temperature === undefined || !Number.isFinite(temperature))) {
      setError('Ingresa una temperatura válida para la lectura.');
      return;
    }

    const operationId = Crypto.randomUUID();
    const payload: PrefrioActionPayload = {
      operacion_id: operationId,
      version_conocida: selectedProcess.version,
      ...(eventNote.trim() ? { observacion: eventNote.trim() } : {}),
      ...(temperature !== undefined ? { datos: { temperatura: temperature, unidad: '°C' } } : {}),
      ocurrido_at: new Date().toISOString(),
    };
    const command = commandFor(
      operationId,
      selectedProcess,
      'evento',
      eventDraft.title,
      `/api/prefrio/procesos/${selectedProcess.id}/eventos/${eventDraft.type}`,
      payload,
    );
    const optimistic: PrefrioProcess = {
      ...selectedProcess,
      version: selectedProcess.version + 1,
      eventos: [
        {
          id: `local:${operationId}`,
          operacion_id: operationId,
          tipo: eventDraft.type,
          ocurrido_at: payload.ocurrido_at,
          datos: payload.datos ?? null,
          observacion: payload.observacion ?? null,
          usuario: { id: auth.usuario.id, nombre: auth.usuario.nombre },
          dispositivo: {
            id: auth.dispositivo.id,
            codigo: auth.dispositivo.codigo,
            nombre: auth.dispositivo.nombre,
          },
        },
        ...selectedProcess.eventos,
      ],
    };
    await enqueueAndApply(command, optimistic);
    setEventDraft(null);
    setEventTemperature('');
    setEventNote('');
    setNotice(`${command.label} quedó registrado.`);
  }

  async function removeAssignment(assignmentId: string, folioLabel: string) {
    if (!selectedProcess) return;
    if (assignmentId.startsWith('local:')) {
      setError('Esta carga aún no se sincroniza. Espera la confirmación antes de retirarla.');
      return;
    }

    Alert.alert('Retirar folio', `¿Retirar ${folioLabel} de la carga del túnel?`, [
      { text: 'Volver', style: 'cancel' },
      {
        text: 'Retirar',
        style: 'destructive',
        onPress: () => void queueRemoval(assignmentId, folioLabel),
      },
    ]);
  }

  async function queueRemoval(assignmentId: string, folioLabel: string) {
    if (!selectedProcess) return;
    const operationId = Crypto.randomUUID();
    const payload: PrefrioActionPayload = {
      operacion_id: operationId,
      version_conocida: selectedProcess.version,
      ocurrido_at: new Date().toISOString(),
    };
    const command = commandFor(
      operationId,
      selectedProcess,
      'retirar_folio',
      `Retirar ${folioLabel}`,
      `/api/prefrio/procesos/${selectedProcess.id}/folios/${assignmentId}/retirar`,
      payload,
    );
    const folios = selectedProcess.folios.map((item) => item.id === assignmentId
      ? { ...item, estado: 'retirado' as const, retirado_at: payload.ocurrido_at }
      : item);
    const quedan = folios.some((item) => item.estado === 'cargado');
    await enqueueAndApply(command, {
      ...selectedProcess,
      estado: quedan ? 'cargando' : 'borrador',
      version: selectedProcess.version + 1,
      folios,
    });
  }

  async function createProcess() {
    if (!baseUrl || !selectedTunnel || !canOperate) {
      setError('La creación de procesos requiere conexión con el servidor.');
      return;
    }
    const setpoint = Number(createSetpoint.replace(',', '.'));
    const duration = Number(createDuration);
    if (!Number.isFinite(setpoint) || !Number.isInteger(duration) || duration < 1) {
      setError('Revisa el setpoint y la duración objetivo.');
      return;
    }

    setBusy(true);
    setError('');
    try {
      const process = await createPrefrioProcess(baseUrl, auth.token, {
        operacion_id: Crypto.randomUUID(),
        tunel_prefrio_id: selectedTunnel.id,
        setpoint,
        duracion_objetivo_minutos: duration,
        ...(createFormat.trim() ? { formato_referencia: createFormat.trim() } : {}),
        ocurrido_at: new Date().toISOString(),
      });
      await upsertServerProcess(process);
      setCreating(false);
      await synchronize();
      setNotice(`${process.codigo} creado en ${selectedTunnel.codigo}.`);
    } catch (reason) {
      setError(messageFrom(reason));
    } finally {
      setBusy(false);
    }
  }

  async function discardCommand(item: PrefrioQueuedCommand) {
    if (!baseUrl) {
      setError('Conecta la PDA antes de descartar una operación para reconstruir el estado confirmado.');
      return;
    }
    const items = await removePrefrioCommand(userId, deviceId, item.id);
    replaceOutbox(items);
    await synchronize();
  }

  function logout() {
    if (outbox.some((item) => item.status === 'pendiente')) {
      Alert.alert(
        'Operaciones pendientes',
        'La bandeja permanece guardada en esta PDA. ¿Deseas cerrar la sesión igualmente?',
        [
          { text: 'Volver', style: 'cancel' },
          { text: 'Cerrar sesión', style: 'destructive', onPress: onLogout },
        ],
      );
      return;
    }
    onLogout();
  }

  if (!cache.synced_at && busy) {
    return (
      <View style={styles.boot}>
        <ActivityIndicator color={colors.cyan} size="large" />
        <Text style={styles.muted}>Preparando Prefrío…</Text>
      </View>
    );
  }

  return (
    <View style={styles.screen}>
      <ScrollView contentContainerStyle={styles.page} keyboardShouldPersistTaps="handled">
        <View style={styles.topbar}>
          <View>
            <Text style={styles.eyebrow}>ESTIBA WMS · PRE-FRÍO</Text>
            <Text style={styles.title}>Operación de túneles</Text>
            <Text style={styles.muted}>{auth.usuario.nombre} · {auth.dispositivo.codigo}</Text>
          </View>
          <View style={styles.topbarActions}>
            <View style={[styles.connection, online ? styles.connectionOnline : styles.connectionOffline]}>
              <Text style={styles.connectionText}>{online ? 'EN LÍNEA' : 'SIN CONEXIÓN'}</Text>
            </View>
            <Pressable onPress={() => void synchronize()} style={styles.secondaryButton}>
              <Text style={styles.secondaryButtonText}>↻ Sincronizar</Text>
            </Pressable>
            <Pressable onPress={logout} style={styles.secondaryButton}>
              <Text style={styles.secondaryButtonText}>Cerrar turno</Text>
            </Pressable>
          </View>
        </View>

        {notice ? <Text style={styles.notice}>{notice}</Text> : null}
        {error ? <Text style={styles.error}>{error}</Text> : null}

        <View style={styles.metrics}>
          <Metric label="TÚNELES" value={String(cache.tunnels.length)} />
          <Metric label="PROCESOS ACTIVOS" value={String(cache.processes.filter((item) => ACTIVE_STATES.has(item.estado)).length)} />
          <Metric label="FOLIOS PENDIENTES" value={String(cache.eligible_folios.length)} />
          <Metric label="BANDEJA" value={String(outbox.length)} warning={unresolved > 0} />
        </View>

        <Text style={styles.sectionTitle}>Túneles</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.tunnelRow}>
          {cache.tunnels.map((tunnel) => {
            const process = tunnel.proceso_activo
              ? cache.processes.find((item) => item.id === tunnel.proceso_activo?.id)
              : null;
            const selected = selectedTunnel?.id === tunnel.id;
            return (
              <Pressable
                key={tunnel.id}
                onPress={() => {
                  setSelectedTunnelId(tunnel.id);
                  setSelectedProcessId(process?.id ?? null);
                  setSelectedPositionId(null);
                  setCreating(false);
                }}
                style={[styles.tunnelCard, selected && styles.tunnelCardSelected]}
              >
                <Text style={styles.tunnelCode}>{tunnel.codigo}</Text>
                <Text style={styles.tunnelName}>{tunnel.nombre}</Text>
                <Text style={styles.tunnelMeta}>{tunnel.capacidad_posiciones} posiciones · {formatTemperature(tunnel.setpoint_habitual)}</Text>
                <Text style={styles.tunnelState}>{process ? stateLabel(process.estado) : tunnel.estado_tecnico === 'operativo' ? 'Disponible' : stateLabel(tunnel.estado_tecnico)}</Text>
                {process ? <Text style={styles.tunnelProcess}>{process.codigo} · v{process.version}</Text> : null}
              </Pressable>
            );
          })}
        </ScrollView>

        {!selectedTunnel ? (
          <View style={styles.emptyPanel}><Text style={styles.muted}>Selecciona un túnel para comenzar.</Text></View>
        ) : !selectedProcess ? (
          <View style={styles.panel}>
            <Text style={styles.panelTitle}>{selectedTunnel.codigo} disponible</Text>
            <Text style={styles.muted}>No existe un proceso activo. Puedes crearlo conectado o esperar que oficina lo programe.</Text>
            {canOperate ? (
              <Pressable onPress={() => setCreating(true)} style={styles.primaryButton}>
                <Text style={styles.primaryButtonText}>Crear proceso</Text>
              </Pressable>
            ) : null}
          </View>
        ) : (
          <>
            <View style={styles.processHeader}>
              <View>
                <Text style={styles.eyebrow}>{selectedTunnel.codigo} · {selectedTunnel.nombre}</Text>
                <Text style={styles.processCode}>{selectedProcess.codigo}</Text>
                <Text style={styles.muted}>{stateLabel(selectedProcess.estado)} · versión {selectedProcess.version} · setpoint {formatTemperature(selectedProcess.setpoint)}</Text>
              </View>
              <View style={styles.processCounts}>
                <Text style={styles.processCountValue}>{selectedProcess.folios.filter((item) => !['retirado', 'cancelado'].includes(item.estado)).length}/{selectedTunnel.capacidad_posiciones}</Text>
                <Text style={styles.processCountLabel}>POSICIONES</Text>
              </View>
            </View>

            <View style={styles.workGrid}>
              <View style={styles.positionPanel}>
                <Text style={styles.panelTitle}>Plano del túnel</Text>
                <Text style={styles.muted}>Selecciona una posición libre antes de escanear.</Text>
                <View style={styles.positions}>
                  {selectedTunnel.posiciones.map((position) => {
                    const assignment = assignmentsByPosition.get(position.id);
                    const selected = selectedPositionId === position.id;
                    return (
                      <Pressable
                        key={position.id}
                        disabled={!position.activa}
                        onPress={() => assignment
                          ? void removeAssignment(assignment.id, assignment.folio?.numero_folio ?? 'folio')
                          : setSelectedPositionId(position.id)}
                        style={[
                          styles.position,
                          assignment && styles.positionOccupied,
                          selected && !assignment && styles.positionSelected,
                          !position.activa && styles.positionDisabled,
                        ]}
                      >
                        <Text style={styles.positionLabel}>{position.numero}</Text>
                        <Text numberOfLines={1} style={styles.positionFolio}>
                          {assignment?.folio?.numero_folio ?? 'Libre'}
                        </Text>
                      </Pressable>
                    );
                  })}
                </View>
              </View>

              <View style={styles.operationPanel}>
                <Text style={styles.panelTitle}>Escaneo de carga</Text>
                <Text style={styles.selectedPosition}>Posición: {selectedPosition?.etiqueta ?? 'selecciona una libre'}</Text>
                <TextInput
                  ref={scannerRef}
                  autoCapitalize="characters"
                  autoCorrect={false}
                  editable={canOperate && ['borrador', 'cargando'].includes(selectedProcess.estado)}
                  onChangeText={setFolioNumber}
                  onSubmitEditing={() => void addFolio()}
                  placeholder="Escanea el folio"
                  placeholderTextColor={colors.muted}
                  returnKeyType="done"
                  style={styles.scannerInput}
                  value={folioNumber}
                />
                <TextInput
                  editable={canOperate && ['borrador', 'cargando'].includes(selectedProcess.estado)}
                  keyboardType="decimal-pad"
                  onChangeText={setInitialTemperature}
                  placeholder="Temperatura inicial opcional"
                  placeholderTextColor={colors.muted}
                  style={styles.input}
                  value={initialTemperature}
                />
                <Pressable
                  disabled={!canOperate || !['borrador', 'cargando'].includes(selectedProcess.estado)}
                  onPress={() => void addFolio()}
                  style={[styles.primaryButton, (!canOperate || !['borrador', 'cargando'].includes(selectedProcess.estado)) && styles.buttonDisabled]}
                >
                  <Text style={styles.primaryButtonText}>Agregar al túnel</Text>
                </Pressable>

                <View style={styles.actionDivider} />
                <ProcessActions
                  process={selectedProcess}
                  canOperate={canOperate}
                  onConfirm={() => Alert.alert('Confirmar armado', 'Después de confirmar ya no deben agregarse pallets.', [
                    { text: 'Volver', style: 'cancel' },
                    { text: 'Confirmar', onPress: () => void queueStateAction('confirmar_armado', 'Armado confirmado', `/api/prefrio/procesos/${selectedProcess.id}/confirmar-armado`, 'listo_para_iniciar') },
                  ])}
                  onStart={() => Alert.alert('Iniciar proceso', 'La composición del túnel quedará cerrada y los folios pasarán a proceso térmico.', [
                    { text: 'Volver', style: 'cancel' },
                    { text: 'Iniciar', onPress: () => void queueStateAction('iniciar', 'Proceso iniciado', `/api/prefrio/procesos/${selectedProcess.id}/iniciar`, 'en_proceso') },
                  ])}
                  onVerify={() => Alert.alert('Enviar a verificación', 'El proceso quedará pendiente de la decisión del supervisor.', [
                    { text: 'Volver', style: 'cancel' },
                    { text: 'Enviar', onPress: () => void queueStateAction('verificar', 'Proceso enviado a verificación', `/api/prefrio/procesos/${selectedProcess.id}/verificar`, 'pendiente_verificacion') },
                  ])}
                  onEvent={setEventDraft}
                />
              </View>
            </View>

            <View style={styles.panel}>
              <Text style={styles.panelTitle}>Últimos eventos</Text>
              {selectedProcess.eventos.slice(0, 8).map((event) => (
                <View key={event.id} style={styles.timelineItem}>
                  <Text style={styles.timelineType}>{stateLabel(event.tipo)}</Text>
                  <Text style={styles.timelineMeta}>{formatDate(event.ocurrido_at)} · {event.usuario?.nombre ?? 'PDA'}</Text>
                  {event.observacion ? <Text style={styles.timelineNote}>{event.observacion}</Text> : null}
                </View>
              ))}
              {!selectedProcess.eventos.length ? <Text style={styles.muted}>Aún no existen eventos.</Text> : null}
            </View>
          </>
        )}

        <View style={styles.panel}>
          <View style={styles.panelHeading}>
            <View><Text style={styles.panelTitle}>Bandeja offline</Text><Text style={styles.muted}>Las operaciones se guardan antes de transmitirse.</Text></View>
            <Text style={styles.queueCount}>{outbox.length}</Text>
          </View>
          {outbox.map((item) => (
            <View key={item.id} style={styles.queueItem}>
              <View style={styles.queueCopy}>
                <Text style={styles.queueLabel}>{item.process_code} · {item.label}</Text>
                <Text style={styles.queueMeta}>{stateLabel(item.status)} · {formatDate(item.created_at)}</Text>
                {item.message ? <Text style={styles.queueError}>{item.message}</Text> : null}
              </View>
              {item.status !== 'pendiente' ? (
                <Pressable onPress={() => void discardCommand(item)} style={styles.smallDangerButton}>
                  <Text style={styles.smallDangerText}>Descartar y refrescar</Text>
                </Pressable>
              ) : null}
            </View>
          ))}
          {!outbox.length ? <Text style={styles.muted}>No existen operaciones pendientes.</Text> : null}
          {selectedProcessId && processQueue.some((item) => item.status === 'conflicto') ? (
            <Text style={styles.warning}>Este proceso tiene un conflicto. Las acciones posteriores permanecen detenidas hasta revisar el estado confirmado.</Text>
          ) : null}
        </View>
      </ScrollView>

      {busy ? <View style={styles.loading}><ActivityIndicator color={colors.cyan} size="large" /></View> : null}

      <Modal animationType="fade" onRequestClose={() => setEventDraft(null)} transparent visible={eventDraft !== null}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.eyebrow}>EVENTO OPERACIONAL</Text>
            <Text style={styles.modalTitle}>{eventDraft?.title}</Text>
            {eventDraft?.requiresTemperature ? (
              <TextInput
                autoFocus
                keyboardType="decimal-pad"
                onChangeText={setEventTemperature}
                placeholder="Temperatura °C"
                placeholderTextColor={colors.muted}
                style={styles.input}
                value={eventTemperature}
              />
            ) : null}
            <TextInput
              multiline
              onChangeText={setEventNote}
              placeholder="Observación opcional"
              placeholderTextColor={colors.muted}
              style={[styles.input, styles.multiline]}
              value={eventNote}
            />
            <View style={styles.modalActions}>
              <Pressable onPress={() => setEventDraft(null)} style={styles.secondaryButton}><Text style={styles.secondaryButtonText}>Cancelar</Text></Pressable>
              <Pressable onPress={() => void registerEvent()} style={styles.primaryButton}><Text style={styles.primaryButtonText}>Registrar</Text></Pressable>
            </View>
          </View>
        </View>
      </Modal>

      <Modal animationType="fade" onRequestClose={() => setCreating(false)} transparent visible={creating}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.eyebrow}>NUEVO PROCESO</Text>
            <Text style={styles.modalTitle}>{selectedTunnel?.codigo}</Text>
            <TextInput keyboardType="decimal-pad" onChangeText={setCreateSetpoint} placeholder="Setpoint" placeholderTextColor={colors.muted} style={styles.input} value={createSetpoint} />
            <TextInput keyboardType="number-pad" onChangeText={setCreateDuration} placeholder="Duración objetivo en minutos" placeholderTextColor={colors.muted} style={styles.input} value={createDuration} />
            <TextInput onChangeText={setCreateFormat} placeholder="Formato de referencia" placeholderTextColor={colors.muted} style={styles.input} value={createFormat} />
            <View style={styles.modalActions}>
              <Pressable onPress={() => setCreating(false)} style={styles.secondaryButton}><Text style={styles.secondaryButtonText}>Cancelar</Text></Pressable>
              <Pressable onPress={() => void createProcess()} style={styles.primaryButton}><Text style={styles.primaryButtonText}>Crear proceso</Text></Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

function ProcessActions({
  process,
  canOperate,
  onConfirm,
  onStart,
  onVerify,
  onEvent,
}: {
  process: PrefrioProcess;
  canOperate: boolean;
  onConfirm: () => void;
  onStart: () => void;
  onVerify: () => void;
  onEvent: (draft: EventDraft) => void;
}) {
  if (!canOperate) return <Text style={styles.muted}>Perfil de consulta: no puedes ejecutar acciones.</Text>;

  if (['borrador', 'cargando'].includes(process.estado)) {
    return <Pressable onPress={onConfirm} style={styles.primaryButton}><Text style={styles.primaryButtonText}>Confirmar armado</Text></Pressable>;
  }

  if (process.estado === 'listo_para_iniciar') {
    return <Pressable onPress={onStart} style={styles.primaryButton}><Text style={styles.primaryButtonText}>Iniciar proceso</Text></Pressable>;
  }

  if (process.estado === 'en_proceso') {
    return (
      <View style={styles.eventGrid}>
        <EventButton label="Inversión" onPress={() => onEvent({ type: 'inversion_registrada', title: 'Registrar inversión', requiresTemperature: false })} />
        <EventButton label="Pausa" onPress={() => onEvent({ type: 'pausa', title: 'Registrar pausa', requiresTemperature: false })} />
        <EventButton label="Reanudar" onPress={() => onEvent({ type: 'reanudacion', title: 'Registrar reanudación', requiresTemperature: false })} />
        <EventButton label="Deshielo" onPress={() => onEvent({ type: 'deshielo', title: 'Registrar deshielo', requiresTemperature: false })} />
        <EventButton label="Lectura" onPress={() => onEvent({ type: 'lectura', title: 'Registrar lectura', requiresTemperature: true })} />
        <Pressable onPress={onVerify} style={styles.primaryButton}><Text style={styles.primaryButtonText}>Enviar a verificación</Text></Pressable>
      </View>
    );
  }

  return <Text style={styles.muted}>El proceso espera una decisión de supervisión.</Text>;
}

function EventButton({ label, onPress }: { label: string; onPress: () => void }) {
  return <Pressable onPress={onPress} style={styles.eventButton}><Text style={styles.eventButtonText}>{label}</Text></Pressable>;
}

function Metric({ label, value, warning = false }: { label: string; value: string; warning?: boolean }) {
  return <View style={styles.metric}><Text style={styles.metricLabel}>{label}</Text><Text style={[styles.metricValue, warning && styles.metricWarning]}>{value}</Text></View>;
}

function commandFor(
  id: string,
  process: PrefrioProcess,
  kind: PrefrioQueuedCommand['kind'],
  label: string,
  route: string,
  payload: PrefrioQueuedCommand['payload'],
): PrefrioQueuedCommand {
  return {
    id,
    process_id: process.id,
    process_code: process.codigo,
    kind,
    label,
    route,
    payload,
    status: 'pendiente',
    attempts: 0,
    created_at: new Date().toISOString(),
    last_attempt_at: null,
    message: null,
  };
}

function nextFreePositionId(process: PrefrioProcess, tunnel: PrefrioTunnel) {
  const occupied = new Set(process.folios
    .filter((item) => !['retirado', 'cancelado'].includes(item.estado))
    .map((item) => item.posicion?.id));
  return tunnel.posiciones.find((item) => item.activa && !occupied.has(item.id))?.id ?? null;
}

function stateLabel(value: string) {
  return value.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
}

function formatTemperature(value: number | null) {
  return value === null ? 'Sin setpoint' : `${value.toFixed(1)} °C`;
}

function formatDate(value: string) {
  return new Date(value).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' });
}

function messageFrom(reason: unknown) {
  return reason instanceof Error ? reason.message : 'Ocurrió un problema inesperado.';
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  page: { padding: 18, gap: 16 },
  boot: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12, backgroundColor: colors.background },
  topbar: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 16 },
  topbarActions: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  eyebrow: { color: colors.cyan, fontSize: 10, fontWeight: '900', letterSpacing: 1.2 },
  title: { color: colors.text, fontSize: 26, fontWeight: '900', marginTop: 4 },
  muted: { color: colors.muted, fontSize: 12, lineHeight: 18 },
  notice: { color: colors.green, backgroundColor: colors.greenDark, padding: 10, borderRadius: 8, fontWeight: '800' },
  error: { color: colors.red, backgroundColor: colors.blocked, padding: 10, borderRadius: 8, fontWeight: '800' },
  warning: { color: colors.amber, marginTop: 10, fontWeight: '800' },
  connection: { paddingHorizontal: 10, paddingVertical: 7, borderRadius: 999 },
  connectionOnline: { backgroundColor: colors.greenDark },
  connectionOffline: { backgroundColor: colors.blocked },
  connectionText: { color: colors.text, fontSize: 9, fontWeight: '900' },
  metrics: { flexDirection: 'row', gap: 10 },
  metric: { flex: 1, padding: 13, borderRadius: 12, backgroundColor: colors.panel, borderWidth: 1, borderColor: colors.borderSoft },
  metricLabel: { color: colors.muted, fontSize: 9, fontWeight: '900' },
  metricValue: { color: colors.text, fontSize: 24, fontWeight: '900', marginTop: 5 },
  metricWarning: { color: colors.amber },
  sectionTitle: { color: colors.text, fontSize: 16, fontWeight: '900' },
  tunnelRow: { gap: 10, paddingBottom: 4 },
  tunnelCard: { width: 230, padding: 14, borderRadius: 14, backgroundColor: colors.panel, borderWidth: 1, borderColor: colors.border },
  tunnelCardSelected: { borderColor: colors.cyan, backgroundColor: colors.selected },
  tunnelCode: { color: colors.cyan, fontWeight: '900', fontSize: 16 },
  tunnelName: { color: colors.text, fontWeight: '800', marginTop: 4 },
  tunnelMeta: { color: colors.muted, fontSize: 11, marginTop: 8 },
  tunnelState: { color: colors.green, fontSize: 11, fontWeight: '900', marginTop: 10 },
  tunnelProcess: { color: colors.text, fontSize: 11, marginTop: 3 },
  panel: { padding: 16, borderRadius: 14, backgroundColor: colors.panel, borderWidth: 1, borderColor: colors.borderSoft, gap: 10 },
  emptyPanel: { minHeight: 150, alignItems: 'center', justifyContent: 'center', borderRadius: 14, backgroundColor: colors.panel },
  panelTitle: { color: colors.text, fontSize: 17, fontWeight: '900' },
  panelHeading: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  processHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 16, borderRadius: 14, backgroundColor: colors.panelStrong, borderWidth: 1, borderColor: colors.cyanDark },
  processCode: { color: colors.text, fontSize: 24, fontWeight: '900', marginTop: 4 },
  processCounts: { alignItems: 'flex-end' },
  processCountValue: { color: colors.cyan, fontSize: 25, fontWeight: '900' },
  processCountLabel: { color: colors.muted, fontSize: 9, fontWeight: '900' },
  workGrid: { flexDirection: 'row', gap: 14, alignItems: 'flex-start' },
  positionPanel: { flex: 2, padding: 16, borderRadius: 14, backgroundColor: colors.panel, borderWidth: 1, borderColor: colors.borderSoft },
  operationPanel: { flex: 1, padding: 16, borderRadius: 14, backgroundColor: colors.panel, borderWidth: 1, borderColor: colors.borderSoft, gap: 10 },
  positions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 14 },
  position: { width: 92, minHeight: 68, justifyContent: 'center', padding: 8, borderRadius: 10, borderWidth: 1, borderColor: colors.freeBorder, backgroundColor: colors.free },
  positionOccupied: { borderColor: colors.palletBorder, backgroundColor: colors.pallet },
  positionSelected: { borderColor: colors.cyan, backgroundColor: colors.selected },
  positionDisabled: { opacity: 0.35 },
  positionLabel: { color: colors.text, fontSize: 15, fontWeight: '900' },
  positionFolio: { color: colors.text, fontSize: 9, marginTop: 4 },
  selectedPosition: { color: colors.cyan, fontWeight: '800' },
  scannerInput: { minHeight: 58, paddingHorizontal: 14, borderRadius: 10, borderWidth: 2, borderColor: colors.cyanDark, backgroundColor: colors.backgroundDeep, color: colors.text, fontSize: 20, fontWeight: '900' },
  input: { minHeight: 46, paddingHorizontal: 12, borderRadius: 9, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep, color: colors.text },
  multiline: { minHeight: 96, paddingTop: 12, textAlignVertical: 'top' },
  primaryButton: { minHeight: 45, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 14, borderRadius: 9, backgroundColor: colors.cyan },
  primaryButtonText: { color: colors.accentText, fontWeight: '900' },
  secondaryButton: { minHeight: 38, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 12, borderRadius: 8, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  secondaryButtonText: { color: colors.text, fontSize: 11, fontWeight: '800' },
  buttonDisabled: { opacity: 0.4 },
  actionDivider: { height: 1, backgroundColor: colors.borderSoft, marginVertical: 4 },
  eventGrid: { gap: 8 },
  eventButton: { minHeight: 40, alignItems: 'center', justifyContent: 'center', borderRadius: 8, borderWidth: 1, borderColor: colors.cyanDark, backgroundColor: colors.panelStrong },
  eventButtonText: { color: colors.cyan, fontWeight: '900' },
  timelineItem: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.borderSoft },
  timelineType: { color: colors.text, fontWeight: '900' },
  timelineMeta: { color: colors.muted, fontSize: 10, marginTop: 2 },
  timelineNote: { color: colors.text, fontSize: 11, marginTop: 4 },
  queueCount: { color: colors.cyan, fontSize: 22, fontWeight: '900' },
  queueItem: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 12, paddingVertical: 9, borderBottomWidth: 1, borderBottomColor: colors.borderSoft },
  queueCopy: { flex: 1 },
  queueLabel: { color: colors.text, fontWeight: '800' },
  queueMeta: { color: colors.muted, fontSize: 10, marginTop: 2 },
  queueError: { color: colors.red, fontSize: 11, marginTop: 4 },
  smallDangerButton: { paddingHorizontal: 10, paddingVertical: 8, borderRadius: 8, borderWidth: 1, borderColor: colors.red },
  smallDangerText: { color: colors.red, fontSize: 10, fontWeight: '900' },
  loading: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(8,12,16,0.68)' },
  modalBackdrop: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: 'rgba(0,0,0,0.72)' },
  modalCard: { width: '100%', maxWidth: 520, padding: 20, gap: 12, borderRadius: 16, backgroundColor: colors.panel, borderWidth: 1, borderColor: colors.cyanDark },
  modalTitle: { color: colors.text, fontSize: 22, fontWeight: '900' },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 10 },
});
