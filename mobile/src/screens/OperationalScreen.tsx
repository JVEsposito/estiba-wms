import * as Crypto from 'expo-crypto';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  AppState,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';

import { ActionPanel } from '../components/ActionPanel';
import { CameraCard } from '../components/CameraCard';
import {
  LocateFormValue,
  LocateModal,
  MoveModal,
} from '../components/OperationModals';
import { PositionMap } from '../components/PositionMap';
import { RecentMovements } from '../components/RecentMovements';
import {
  AuthSession,
  CameraPlan,
  CameraSummary,
  LocatePayload,
  Movement,
  MovePayload,
  Position,
  SagCondition,
} from '../domain/estiba';
import { EstibaApi } from '../services/estibaApi';
import { ApiError } from '../services/apiError';
import { colors } from '../theme/colors';

type OperationalScreenProps = {
  api: EstibaApi;
  auth: AuthSession;
  onLogout: () => void;
};

export function OperationalScreen({ api, auth, onLogout }: OperationalScreenProps) {
  const { height, width } = useWindowDimensions();
  const wideLayout = width >= 1180 && height >= 700;
  const [cameras, setCameras] = useState<CameraSummary[]>([]);
  const [conditions, setConditions] = useState<SagCondition[]>([]);
  const [selectedCameraId, setSelectedCameraId] = useState<string | null>(null);
  const [plan, setPlan] = useState<CameraPlan | null>(null);
  const [movements, setMovements] = useState<Movement[]>([]);
  const [selectedPositionId, setSelectedPositionId] = useState<string | null>(null);
  const [destinationPlan, setDestinationPlan] = useState<CameraPlan | null>(null);
  const [selectedDestination, setSelectedDestination] = useState<Position | null>(null);
  const [locateVisible, setLocateVisible] = useState(false);
  const [moveVisible, setMoveVisible] = useState(false);
  const [busy, setBusy] = useState(true);
  const [error, setError] = useState('');
  const [modalError, setModalError] = useState('');
  const [notice, setNotice] = useState('');
  const [connectionState, setConnectionState] = useState<'connected' | 'offline'>('connected');
  const [lastSync, setLastSync] = useState<string | null>(null);
  const refreshInFlight = useRef(false);

  const selectedPosition = useMemo(
    () => plan?.posiciones.find((position) => position.id === selectedPositionId) ?? null,
    [plan, selectedPositionId],
  );
  const ownSession = plan?.acceso.modo === 'edicion' && plan.acceso.sesion?.es_propia
    ? plan.acceso.sesion
    : null;
  const canOperate = Boolean(ownSession);

  useEffect(() => {
    void initialize();
  }, []);

  useEffect(() => {
    if (!selectedCameraId || busy || locateVisible || moveVisible) return;

    const timer = setInterval(() => {
      void refreshCurrent({ quiet: true });
    }, 30000);

    return () => clearInterval(timer);
  }, [selectedCameraId, busy, locateVisible, moveVisible]);

  useEffect(() => {
    const subscription = AppState.addEventListener('change', (nextState) => {
      if (nextState === 'active'
        && selectedCameraId
        && !busy
        && !locateVisible
        && !moveVisible) {
        void refreshCurrent({ quiet: true });
      }
    });

    return () => subscription.remove();
  }, [selectedCameraId, busy, locateVisible, moveVisible]);

  async function initialize() {
    setBusy(true);
    setError('');
    try {
      const [loadedCameras, loadedConditions] = await Promise.all([
        api.listCameras(auth.token),
        api.listConditions(auth.token),
      ]);
      setCameras(loadedCameras);
      setConditions(loadedConditions);
      setError('');
      setConnectionState('connected');
      if (loadedCameras[0]) {
        setSelectedCameraId(loadedCameras[0].id);
        await loadCamera(loadedCameras[0].id, false);
      }
    } catch (reason) {
      reportFailure(reason, setError);
    } finally {
      setBusy(false);
    }
  }

  async function loadCamera(cameraId: string, showBusy = true) {
    if (showBusy) setBusy(true);
    setError('');
    setSelectedPositionId(null);
    try {
      const [loadedPlan, loadedMovements] = await Promise.all([
        api.getPlan(auth.token, cameraId),
        api.listRecent(auth.token, cameraId),
      ]);
      setPlan(loadedPlan);
      setMovements(loadedMovements);
      setError('');
      setConnectionState('connected');
      setLastSync(new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }));
    } catch (reason) {
      reportFailure(reason, setError);
    } finally {
      if (showBusy) setBusy(false);
    }
  }

  async function selectCamera(cameraId: string) {
    setSelectedCameraId(cameraId);
    setNotice('');
    await loadCamera(cameraId);
  }

  async function refreshCurrent({ quiet = false }: { quiet?: boolean } = {}) {
    if (!selectedCameraId || refreshInFlight.current) return;

    refreshInFlight.current = true;
    if (!quiet) setBusy(true);

    try {
      const [loadedCameras, loadedPlan, loadedMovements] = await Promise.all([
        api.listCameras(auth.token),
        api.getPlan(auth.token, selectedCameraId),
        api.listRecent(auth.token, selectedCameraId),
      ]);

      setCameras(loadedCameras);
      setPlan(loadedPlan);
      setMovements(loadedMovements);
      setError('');
      setSelectedPositionId((current) => (
        current && loadedPlan.posiciones.some((position) => position.id === current)
          ? current
          : null
      ));
      setConnectionState('connected');
      setLastSync(new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }));
    } catch (reason) {
      reportFailure(reason, setError);
    } finally {
      refreshInFlight.current = false;
      if (!quiet) setBusy(false);
    }
  }

  function toggleSession() {
    if (!plan) return;
    if (plan.acceso.modo === 'solo_lectura') {
      Alert.alert('Cámara en uso', 'Otro operador mantiene la sesión de edición.');
      return;
    }

    if (ownSession) {
      Alert.alert(
        'Cerrar estiba',
        '¿Deseas liberar ' + plan.codigo + '?',
        [
          { text: 'Cancelar', style: 'cancel' },
          { text: 'Cerrar', style: 'destructive', onPress: () => void closeCurrentSession() },
        ],
      );
      return;
    }

    void openCurrentSession();
  }

  async function openCurrentSession() {
    if (!plan) return;
    const succeeded = await runOperation(async () => {
      await api.openSession(auth.token, plan.id);
    });

    if (succeeded) {
      setNotice(`Estiba abierta en ${plan.codigo}. La cámara quedó reservada para esta tablet.`);
      await refreshCurrent();
    }
  }

  async function closeCurrentSession() {
    if (!ownSession) return;
    const cameraCode = plan?.codigo ?? 'la cámara';
    const succeeded = await runOperation(() => api.closeSession(auth.token, ownSession.id));

    if (succeeded) {
      setNotice(`Estiba cerrada en ${cameraCode}. La cámara quedó disponible.`);
      await refreshCurrent();
    }
  }

  async function confirmLocate(form: LocateFormValue) {
    if (!plan || !selectedPosition || !ownSession) return;
    setModalError('');
    const data = {
      condicion_sag_id: form.condicion_sag_id,
      variedad: form.variedad,
      calibre: form.calibre,
      marca: form.marca,
      exportadora: form.exportadora,
    };
    const compactData = Object.fromEntries(
      Object.entries(data).filter(([, value]) => Boolean(value)),
    ) as NonNullable<LocatePayload['datos_folio']>;
    const payload: LocatePayload = {
      operacion_id: Crypto.randomUUID(),
      numero_folio: form.numero_folio,
      tipo_bulto: form.tipo_bulto,
      posicion_destino_id: selectedPosition.id,
      sesion_destino_id: ownSession.id,
      version_destino_conocida: plan.version_plano,
      generado_dispositivo_at: new Date().toISOString(),
      ...(Object.keys(compactData).length ? { datos_folio: compactData } : {}),
    };

    const succeeded = await runOperation(
      () => api.locate(auth.token, payload),
      setModalError,
    );

    if (succeeded) {
      setLocateVisible(false);
      setNotice(
        `Folio ${payload.numero_folio} guardado en el servidor y ubicado en ${positionLabel(selectedPosition)}.`,
      );
      await refreshCurrent();
    }
  }

  async function openMove() {
    if (!plan || !selectedPosition?.folio || !ownSession) return;
    setModalError('');
    setDestinationPlan(plan);
    setSelectedDestination(null);
    setMoveVisible(true);
  }

  async function chooseDestinationCamera(cameraId: string) {
    setBusy(true);
    setSelectedDestination(null);
    setModalError('');
    try {
      const loadedPlan = cameraId === plan?.id
        ? plan
        : await api.getPlan(auth.token, cameraId);
      setDestinationPlan(loadedPlan);
      setConnectionState('connected');
    } catch (reason) {
      reportFailure(reason, setModalError);
    } finally {
      setBusy(false);
    }
  }

  async function confirmMove() {
    if (!plan || !selectedPosition?.folio || !ownSession || !destinationPlan || !selectedDestination) return;
    const folio = selectedPosition.folio;
    const destinationLabel = `${destinationPlan.codigo} · ${positionLabel(selectedDestination)}`;
    setModalError('');

    const succeeded = await runOperation(async () => {
      let destinationSessionId: string;
      if (destinationPlan.id === plan.id) {
        destinationSessionId = ownSession.id;
      } else if (destinationPlan.acceso.modo === 'edicion' && destinationPlan.acceso.sesion?.es_propia) {
        destinationSessionId = destinationPlan.acceso.sesion.id;
      } else if (destinationPlan.acceso.modo === 'disponible') {
        destinationSessionId = (await api.openSession(auth.token, destinationPlan.id)).id;
      } else {
        throw new Error('La cámara de destino no está disponible para edición.');
      }

      const payload: MovePayload = {
        operacion_id: Crypto.randomUUID(),
        folio_id: folio.id,
        posicion_destino_id: selectedDestination.id,
        sesion_origen_id: ownSession.id,
        sesion_destino_id: destinationSessionId,
        version_origen_conocida: plan.version_plano,
        version_destino_conocida: destinationPlan.version_plano,
        generado_dispositivo_at: new Date().toISOString(),
      };
      await api.move(auth.token, payload);
    }, setModalError);

    if (succeeded) {
      setMoveVisible(false);
      setDestinationPlan(null);
      setSelectedDestination(null);
      setNotice(`Folio ${folio.numero_folio} movido y guardado en ${destinationLabel}.`);
      await refreshCurrent();
    }
  }

  async function runOperation(
    operation: () => Promise<void>,
    errorTarget: (message: string) => void = setError,
  ): Promise<boolean> {
    setBusy(true);
    errorTarget('');
    setNotice('');
    try {
      await operation();
      setConnectionState('connected');
      return true;
    } catch (reason) {
      reportFailure(reason, errorTarget);
      return false;
    } finally {
      setBusy(false);
    }
  }

  async function logout() {
    const openCameras = cameras
      .filter((camera) => camera.acceso.modo === 'edicion' && camera.acceso.sesion?.es_propia)
      .map((camera) => camera.codigo);

    if (openCameras.length > 0) {
      Alert.alert(
        'Estibas todavía abiertas',
        `Cierra las sesiones de ${openCameras.join(', ')} antes de finalizar el turno.`,
      );
      return;
    }

    setBusy(true);
    try {
      await api.logout(auth.token);
    } catch {
      // La sesión local se cierra aunque el servidor no responda.
    } finally {
      onLogout();
    }
  }

  function reportFailure(reason: unknown, target: (message: string) => void) {
    if (reason instanceof ApiError) {
      setConnectionState(reason.status === 0 ? 'offline' : 'connected');

      if (reason.status === 401) {
        Alert.alert('Sesión vencida', 'Vuelve a iniciar el turno para continuar.');
        onLogout();
        return;
      }
    }

    target(messageFrom(reason));
  }

  const cameraCards = cameras.map((camera) => (
    <CameraCard
      camera={camera}
      key={camera.id}
      onPress={() => void selectCamera(camera.id)}
      selected={camera.id === selectedCameraId}
    />
  ));
  const connectionLabel = api.mode === 'demo'
    ? 'Demo local'
    : connectionState === 'offline' ? 'API sin conexión' : 'API conectada';
  const connectionColor = api.mode === 'demo'
    ? colors.amber
    : connectionState === 'offline' ? colors.red : colors.green;

  return (
    <View style={styles.screen}>
      <ScrollView contentContainerStyle={styles.page}>
        <View style={styles.topbar}>
          <View style={styles.brand}>
            <View style={styles.brandMark}><Text style={styles.brandIcon}>❄</Text></View>
            <View>
              <Text style={styles.brandName}>ESTIBA WMS</Text>
              <Text style={styles.brandModule}>OPERACIÓN TABLET</Text>
            </View>
          </View>
          <View style={styles.statuses}>
            <Status color={connectionColor} label={connectionLabel} />
            <Status color={canOperate ? colors.cyan : colors.muted} label={canOperate ? 'Editando ' + plan?.codigo : 'Solo consulta'} />
          </View>
          <View style={styles.operator}>
            <View style={styles.avatar}><Text style={styles.avatarText}>{initials(auth.usuario.nombre)}</Text></View>
            <View style={styles.operatorCopy}>
              <Text numberOfLines={1} style={styles.operatorName}>{auth.usuario.nombre}</Text>
              <Text numberOfLines={1} style={styles.deviceName}>{auth.dispositivo.nombre}</Text>
            </View>
            <Pressable onPress={() => void logout()} style={styles.logout}>
              <Text style={styles.logoutText}>Salir</Text>
            </Pressable>
          </View>
        </View>

        {error ? (
          <Pressable onPress={() => setError('')} style={styles.errorBanner}>
            <Text style={styles.errorText}>{error}</Text>
            <Text style={styles.errorClose}>×</Text>
          </Pressable>
        ) : null}

        {notice ? (
          <Pressable onPress={() => setNotice('')} style={styles.noticeBanner}>
            <Text style={styles.noticeText}>{notice}</Text>
            <Text style={styles.noticeClose}>×</Text>
          </Pressable>
        ) : null}

        <View style={[styles.workspace, !wideLayout && styles.workspaceCompact]}>
          {wideLayout ? (
            <View style={styles.cameraPanel}>
              <Text style={styles.sectionEyebrow}>CÁMARAS</Text>
              <Text style={styles.sectionTitle}>Área de trabajo</Text>
              <View style={styles.cameraList}>{cameraCards}</View>
            </View>
          ) : (
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              <View style={styles.cameraListHorizontal}>{cameraCards}</View>
            </ScrollView>
          )}

          {plan ? (
            <View style={[styles.operationArea, !wideLayout && styles.operationAreaCompact]}>
              <PositionMap
                onSelectPosition={(position) => setSelectedPositionId(position.id)}
                plan={plan}
                selectedPositionId={selectedPositionId}
              />
              <ActionPanel
                busy={busy}
                canOperate={canOperate}
                compact={!wideLayout}
                onLocate={() => {
                  setModalError('');
                  setNotice('');
                  setLocateVisible(true);
                }}
                onMove={() => void openMove()}
                onRefresh={() => void refreshCurrent()}
                onToggleSession={toggleSession}
                plan={plan}
                selectedPosition={selectedPosition}
              />
            </View>
          ) : (
            <View style={styles.emptyPlan}>
              <Text style={styles.emptyIcon}>▦</Text>
              <Text style={styles.emptyTitle}>Sin cámaras disponibles</Text>
            </View>
          )}
        </View>

        <RecentMovements lastSync={lastSync} movements={movements} />
      </ScrollView>

      {busy && (
        <View pointerEvents="none" style={styles.busyOverlay}>
          <ActivityIndicator color={colors.cyan} size="large" />
          <Text style={styles.busyText}>Sincronizando…</Text>
        </View>
      )}

      <LocateModal
        busy={busy}
        conditions={conditions}
        error={modalError}
        onCancel={() => {
          setModalError('');
          setLocateVisible(false);
        }}
        onConfirm={confirmLocate}
        plan={plan}
        position={selectedPosition}
        visible={locateVisible}
      />
      <MoveModal
        busy={busy}
        cameras={cameras}
        destinationPlan={destinationPlan}
        error={modalError}
        onCancel={() => {
          setModalError('');
          setMoveVisible(false);
        }}
        onChooseCamera={(cameraId) => void chooseDestinationCamera(cameraId)}
        onConfirm={confirmMove}
        onSelectPosition={setSelectedDestination}
        originPlan={plan}
        originPosition={selectedPosition}
        selectedDestination={selectedDestination}
        visible={moveVisible}
      />
    </View>
  );
}

function Status({ color, label }: { color: string; label: string }) {
  return (
    <View style={styles.status}>
      <View style={[styles.statusDot, { backgroundColor: color }]} />
      <Text style={styles.statusText}>{label}</Text>
    </View>
  );
}

function initials(name: string) {
  return name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

function positionLabel(position: Position) {
  return position.etiqueta
    ?? `${position.fila}-${position.profundidad}-N${position.nivel}`;
}

function messageFrom(reason: unknown) {
  return reason instanceof Error ? reason.message : 'La operación no pudo completarse.';
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  page: { flexGrow: 1, padding: 12, backgroundColor: colors.background },
  topbar: {
    minHeight: 65,
    marginBottom: 12,
    paddingHorizontal: 12,
    borderRadius: 15,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.backgroundDeep,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  brand: { minWidth: 220, flexDirection: 'row', alignItems: 'center', gap: 10 },
  brandMark: {
    width: 40,
    height: 40,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: colors.cyanDark,
    backgroundColor: colors.selected,
    alignItems: 'center',
    justifyContent: 'center',
  },
  brandIcon: { color: colors.cyan, fontSize: 23 },
  brandName: { color: colors.text, fontSize: 14, fontWeight: '900', letterSpacing: 1.2 },
  brandModule: { marginTop: 2, color: colors.cyan, fontSize: 7, fontWeight: '900', letterSpacing: 1.7 },
  statuses: { flexDirection: 'row', alignItems: 'center', gap: 9 },
  status: {
    paddingHorizontal: 10,
    paddingVertical: 7,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  statusDot: { width: 7, height: 7, borderRadius: 4 },
  statusText: { color: colors.text, fontSize: 8, fontWeight: '800' },
  operator: { minWidth: 230, flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: 8 },
  avatar: { width: 34, height: 34, borderRadius: 10, backgroundColor: colors.selected, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: colors.cyan, fontSize: 10, fontWeight: '900' },
  operatorCopy: { maxWidth: 130 },
  operatorName: { color: colors.text, fontSize: 9, fontWeight: '900' },
  deviceName: { marginTop: 2, color: colors.muted, fontSize: 7 },
  logout: { paddingHorizontal: 10, paddingVertical: 7, borderRadius: 8, borderWidth: 1, borderColor: colors.border },
  logoutText: { color: colors.muted, fontSize: 8, fontWeight: '900' },
  errorBanner: {
    marginBottom: 10,
    padding: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.red,
    backgroundColor: '#421B21',
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
  },
  errorText: { flex: 1, color: '#FFB7B7', fontSize: 9 },
  errorClose: { color: colors.text, fontSize: 16 },
  noticeBanner: {
    marginBottom: 10,
    padding: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.green,
    backgroundColor: colors.greenDark,
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
  },
  noticeText: { flex: 1, color: colors.text, fontSize: 9, fontWeight: '800' },
  noticeClose: { color: colors.text, fontSize: 16 },
  workspace: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  workspaceCompact: { flexDirection: 'column' },
  cameraPanel: {
    width: 268,
    padding: 14,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
  },
  sectionEyebrow: { color: colors.cyan, fontSize: 8, fontWeight: '900', letterSpacing: 1.2 },
  sectionTitle: { marginTop: 3, color: colors.text, fontSize: 17, fontWeight: '900' },
  cameraList: { marginTop: 12, gap: 9 },
  cameraListHorizontal: { paddingBottom: 2, flexDirection: 'row', gap: 10 },
  operationArea: { flex: 1, minWidth: 0, flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  operationAreaCompact: { width: '100%', flexDirection: 'column' },
  emptyPlan: {
    flex: 1,
    minHeight: 350,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
    alignItems: 'center',
    justifyContent: 'center',
  },
  emptyIcon: { color: colors.cyan, fontSize: 40 },
  emptyTitle: { marginTop: 8, color: colors.text, fontSize: 15, fontWeight: '900' },
  busyOverlay: {
    ...StyleSheet.absoluteFill,
    backgroundColor: 'rgba(4,13,19,0.72)',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  busyText: { color: colors.text, fontSize: 11, fontWeight: '900' },
});
