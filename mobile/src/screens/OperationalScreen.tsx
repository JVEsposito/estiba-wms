import * as Crypto from 'expo-crypto';
import { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
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
import { colors } from '../theme/colors';

type OperationalScreenProps = {
  api: EstibaApi;
  auth: AuthSession;
  onLogout: () => void;
};

export function OperationalScreen({ api, auth, onLogout }: OperationalScreenProps) {
  const { width } = useWindowDimensions();
  const wideLayout = width >= 1180;
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
  const [lastSync, setLastSync] = useState<string | null>(null);

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
      if (loadedCameras[0]) {
        setSelectedCameraId(loadedCameras[0].id);
        await loadCamera(loadedCameras[0].id, false);
      }
    } catch (reason) {
      setError(messageFrom(reason));
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
      setLastSync(new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }));
    } catch (reason) {
      setError(messageFrom(reason));
    } finally {
      if (showBusy) setBusy(false);
    }
  }

  async function selectCamera(cameraId: string) {
    setSelectedCameraId(cameraId);
    await loadCamera(cameraId);
  }

  async function refreshCurrent() {
    if (!selectedCameraId) return;
    setBusy(true);
    setError('');
    try {
      const loadedCameras = await api.listCameras(auth.token);
      setCameras(loadedCameras);
      await loadCamera(selectedCameraId, false);
    } catch (reason) {
      setError(messageFrom(reason));
    } finally {
      setBusy(false);
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
    await runOperation(async () => {
      await api.openSession(auth.token, plan.id);
      await refreshCurrent();
    });
  }

  async function closeCurrentSession() {
    if (!ownSession) return;
    await runOperation(async () => {
      await api.closeSession(auth.token, ownSession.id);
      await refreshCurrent();
    });
  }

  async function confirmLocate(form: LocateFormValue) {
    if (!plan || !selectedPosition || !ownSession) return;
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

    await runOperation(async () => {
      await api.locate(auth.token, payload);
      setLocateVisible(false);
      await refreshCurrent();
    });
  }

  async function openMove() {
    if (!plan || !selectedPosition?.folio || !ownSession) return;
    setDestinationPlan(plan);
    setSelectedDestination(null);
    setMoveVisible(true);
  }

  async function chooseDestinationCamera(cameraId: string) {
    setBusy(true);
    setSelectedDestination(null);
    setError('');
    try {
      const loadedPlan = cameraId === plan?.id
        ? plan
        : await api.getPlan(auth.token, cameraId);
      setDestinationPlan(loadedPlan);
    } catch (reason) {
      setError(messageFrom(reason));
    } finally {
      setBusy(false);
    }
  }

  async function confirmMove() {
    if (!plan || !selectedPosition?.folio || !ownSession || !destinationPlan || !selectedDestination) return;
    const folio = selectedPosition.folio;

    await runOperation(async () => {
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
      setMoveVisible(false);
      setDestinationPlan(null);
      setSelectedDestination(null);
      await refreshCurrent();
    });
  }

  async function runOperation(operation: () => Promise<void>) {
    setBusy(true);
    setError('');
    try {
      await operation();
    } catch (reason) {
      setError(messageFrom(reason));
    } finally {
      setBusy(false);
    }
  }

  async function logout() {
    setBusy(true);
    try {
      await api.logout(auth.token);
    } catch {
      // La sesión local se cierra aunque el servidor no responda.
    } finally {
      onLogout();
    }
  }

  const cameraCards = cameras.map((camera) => (
    <CameraCard
      camera={camera}
      key={camera.id}
      onPress={() => void selectCamera(camera.id)}
      selected={camera.id === selectedCameraId}
    />
  ));

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
            <Status color={api.mode === 'demo' ? colors.amber : colors.green} label={api.mode === 'demo' ? 'Demo local' : 'API conectada'} />
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
            <View style={styles.operationArea}>
              <PositionMap
                onSelectPosition={(position) => setSelectedPositionId(position.id)}
                plan={plan}
                selectedPositionId={selectedPositionId}
              />
              <ActionPanel
                busy={busy}
                canOperate={canOperate}
                onLocate={() => setLocateVisible(true)}
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
        onCancel={() => setLocateVisible(false)}
        onConfirm={confirmLocate}
        plan={plan}
        position={selectedPosition}
        visible={locateVisible}
      />
      <MoveModal
        busy={busy}
        cameras={cameras}
        destinationPlan={destinationPlan}
        onCancel={() => setMoveVisible(false)}
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
