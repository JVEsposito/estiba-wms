import * as Crypto from 'expo-crypto';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from 'react-native';

import {
  EnvironmentalCameraState,
  EnvironmentalControlDraft,
  EnvironmentalControlPayload,
} from '../domain/environmentalControl';
import { AuthSession } from '../domain/estiba';
import {
  EntityCode,
  EstibaAlert,
  EstibaButton,
  EstibaEmpty,
  EstibaField,
  EstibaHeading,
  EstibaPanel,
  EstibaSignal,
} from '../components/ui/EstibaPrimitives';
import { ApiError } from '../services/apiError';
import {
  getEnvironmentalControlState,
  registerEnvironmentalControl,
} from '../services/environmentalControlApi';
import {
  clearEnvironmentalDraft,
  loadEnvironmentalDraft,
  saveEnvironmentalDraft,
} from '../services/environmentalControlDraftStore';
import { estibaTokens as t, type EstibaTone } from '../theme/estibaTokens';

type Props = { auth: AuthSession; baseUrl: string; onDueChange?: (due: number) => void };
type FieldErrors = Partial<Record<'start' | 'middle' | 'end', string>>;

const labels = { start: 'Inicio', middle: 'Medio', end: 'Fondo' } as const;

function emptyDraft(): EnvironmentalControlDraft {
  return {
    cameraId: null,
    cameraCode: null,
    start: '',
    middle: '',
    end: '',
    operationId: Crypto.randomUUID(),
    capturedAt: null,
  };
}

function parseTemperature(value: string) {
  const normalized = value.trim().replace(',', '.');
  if (!/^-?\d+(?:\.\d{1,2})?$/.test(normalized)) return null;
  const number = Number(normalized);
  return Number.isFinite(number) && number >= -999.99 && number <= 999.99 ? number : null;
}

function time(value?: string | null) {
  if (!value) return 'Sin registro';
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? 'Hora no disponible'
    : date.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
}

function statusPresentation(camera: EnvironmentalCameraState): { label: string; tone: EstibaTone; detail: string } {
  if (camera.estado === 'vigente') {
    return { label: 'Vigente', tone: 'success', detail: `Hasta ${time(camera.ultimo_registro?.vigente_hasta)}` };
  }
  if (camera.estado === 'vencido') {
    return { label: 'Vencido', tone: 'critical', detail: `Venció ${time(camera.vencido_desde)}` };
  }
  return { label: 'Pendiente', tone: 'warning', detail: 'Sin control registrado' };
}

function errorMessage(reason: unknown) {
  return reason instanceof Error ? reason.message : 'No fue posible completar la operación.';
}

export function EnvironmentalControlScreen({ auth, baseUrl, onDueChange }: Props) {
  const { width } = useWindowDimensions();
  const compact = width < 840;
  const [status, setStatus] = useState<EnvironmentalCameraState[]>([]);
  const [frequency, setFrequency] = useState(60);
  const [generatedAt, setGeneratedAt] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [statusError, setStatusError] = useState('');
  const [draft, setDraft] = useState<EnvironmentalControlDraft>(emptyDraft);
  const [draftReady, setDraftReady] = useState(false);
  const [confirming, setConfirming] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState('');
  const [success, setSuccess] = useState('');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const canRegister = auth.usuario.capacidades.puede_registrar_control_ambiental === true;

  const loadStatus = useCallback(async (manual = false) => {
    manual ? setRefreshing(true) : setLoading(true);
    try {
      const response = await getEnvironmentalControlState(baseUrl, auth.token);
      setStatus(response.camaras);
      onDueChange?.(response.camaras.filter((camera) => camera.requiere_control).length);
      setFrequency(response.frecuencia_minutos);
      setGeneratedAt(response.generado_at);
      setStatusError('');
    } catch (reason) {
      setStatusError(errorMessage(reason));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [auth.token, baseUrl, onDueChange]);

  useEffect(() => {
    void loadStatus();
    const timer = setInterval(() => void loadStatus(), 60_000);
    return () => clearInterval(timer);
  }, [loadStatus]);

  useEffect(() => {
    let active = true;
    void loadEnvironmentalDraft(auth.usuario.id, auth.dispositivo.id).catch(() => null).then((saved) => {
      if (!active) return;
      if (saved) setDraft(saved);
      setDraftReady(true);
    });
    return () => { active = false; };
  }, [auth.dispositivo.id, auth.usuario.id]);

  const selectedCamera = status.find((item) => item.camara.id === draft.cameraId) ?? null;
  const hasSelection = selectedCamera !== null || draft.cameraId !== null;
  const due = status.filter((item) => item.requiere_control).length;
  const current = status.length - due;
  const lockedAttempt = draft.capturedAt !== null;

  const remember = useCallback((next: EnvironmentalControlDraft) => {
    setDraft(next);
    void saveEnvironmentalDraft(auth.usuario.id, auth.dispositivo.id, next).catch(() => {
      setSubmitError('No fue posible guardar el borrador en este equipo. Revisa el almacenamiento antes de enviar.');
    });
  }, [auth.dispositivo.id, auth.usuario.id]);

  function selectCamera(camera: EnvironmentalCameraState) {
    if (!canRegister || !camera.requiere_control || lockedAttempt) return;
    remember({ ...emptyDraft(), cameraId: camera.camara.id, cameraCode: camera.camara.codigo });
    setConfirming(false);
    setSubmitError('');
    setSuccess('');
    setFieldErrors({});
  }

  function changeField(field: 'start' | 'middle' | 'end', value: string) {
    if (lockedAttempt) return;
    remember({ ...draft, [field]: value });
    setFieldErrors((currentErrors) => ({ ...currentErrors, [field]: undefined }));
    setConfirming(false);
    setSubmitError('');
  }

  function values() {
    return {
      start: parseTemperature(draft.start),
      middle: parseTemperature(draft.middle),
      end: parseTemperature(draft.end),
    };
  }

  function review() {
    const parsed = values();
    const errors: FieldErrors = {};
    (Object.keys(parsed) as Array<keyof typeof parsed>).forEach((field) => {
      if (parsed[field] === null) errors[field] = 'Ingresa un número con máximo dos decimales.';
    });
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0) return;
    setConfirming(true);
    setSubmitError('');
    setSuccess('');
  }

  async function submit() {
    if (!draft.cameraId) return;
    const parsed = values();
    if (parsed.start === null || parsed.middle === null || parsed.end === null) {
      review();
      return;
    }
    const exactAttempt = draft.capturedAt
      ? draft
      : { ...draft, capturedAt: new Date().toISOString() };
    if (!draft.capturedAt) {
      try {
        await saveEnvironmentalDraft(auth.usuario.id, auth.dispositivo.id, exactAttempt);
        setDraft(exactAttempt);
      } catch {
        setSubmitError('No fue posible asegurar el intento en este equipo. Libera espacio y vuelve a intentarlo.');
        return;
      }
    }
    const payload: EnvironmentalControlPayload = {
      operacion_id: exactAttempt.operationId,
      temperatura_inicio_c: parsed.start,
      temperatura_medio_c: parsed.middle,
      temperatura_fondo_c: parsed.end,
      capturado_at: exactAttempt.capturedAt!,
    };
    setSubmitting(true);
    setSubmitError('');
    try {
      await registerEnvironmentalControl(baseUrl, auth.token, exactAttempt.cameraId!, payload);
      await clearEnvironmentalDraft(auth.usuario.id, auth.dispositivo.id);
      setDraft(emptyDraft());
      setConfirming(false);
      setSuccess(`Control de ${exactAttempt.cameraCode ?? 'la cámara'} registrado correctamente.`);
      await loadStatus(true);
    } catch (reason) {
      const message = reason instanceof ApiError && reason.status === 409
        ? `${reason.message} Actualiza el estado antes de descartar este intento.`
        : errorMessage(reason);
      setSubmitError(message);
      setConfirming(true);
    } finally {
      setSubmitting(false);
    }
  }

  async function discardAttempt() {
    try {
      await clearEnvironmentalDraft(auth.usuario.id, auth.dispositivo.id);
    } catch {
      setSubmitError('No fue posible eliminar el intento guardado del equipo.');
      return;
    }
    setDraft(emptyDraft());
    setConfirming(false);
    setSubmitError('');
    setFieldErrors({});
    await loadStatus(true);
  }

  const cards = useMemo(() => [...status].sort((a, b) => {
    const priority = { vencido: 0, pendiente: 1, vigente: 2 };
    return priority[a.estado] - priority[b.estado] || a.camara.codigo.localeCompare(b.camara.codigo);
  }), [status]);

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      keyboardShouldPersistTaps="handled"
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadStatus(true)} />}
    >
      <EstibaHeading
        eyebrow="CADENA DE FRÍO · CONTROL HORARIO"
        title="Estado ambiental de cámaras"
        description={`Registra inicio, medio y fondo cada ${frequency} minutos. Última consulta: ${time(generatedAt)}.`}
        actions={<EstibaButton label="Actualizar estado" variant="secondary" busy={refreshing} onPress={() => void loadStatus(true)} />}
      />

      <View style={styles.summary}>
        <View style={[styles.summaryItem, due > 0 ? styles.summaryDue : styles.summaryOk]}>
          <Text style={styles.summaryValue}>{due}</Text>
          <Text style={styles.summaryLabel}>Requieren control</Text>
        </View>
        <View style={[styles.summaryItem, styles.summaryOk]}>
          <Text style={styles.summaryValue}>{current}</Text>
          <Text style={styles.summaryLabel}>Controles vigentes</Text>
        </View>
        <View style={styles.identity}>
          <Text style={styles.identityLabel}>RESPONSABLE Y EQUIPO</Text>
          <Text style={styles.identityValue}>{auth.usuario.nombre}</Text>
          <Text style={styles.identityDetail}>{auth.dispositivo.codigo} · {auth.dispositivo.nombre}</Text>
        </View>
      </View>

      {statusError ? <EstibaAlert title="No se pudo actualizar el estado" detail={statusError} tone="critical" live /> : null}
      {success ? <EstibaAlert title="Registro confirmado" detail={success} tone="success" live /> : null}
      {lockedAttempt ? <EstibaAlert
        title="Intento pendiente de confirmación"
        detail="Se conservan exactamente la cámara, las lecturas, la hora y el UUID. Reintenta el mismo envío o descártalo después de actualizar el estado."
        tone="warning"
        live
      /> : null}

      <View style={[styles.workspace, compact && styles.workspaceCompact]}>
        <EstibaPanel title="Cámaras de producto terminado" style={styles.cameraPanel}>
          {loading && status.length === 0 ? <Text style={styles.muted}>Consultando cámaras…</Text> : null}
          {!loading && cards.length === 0 ? <EstibaEmpty title="Sin cámaras disponibles" description="No existen cámaras PT activas visibles para este perfil." /> : null}
          <View style={styles.cameraGrid}>
            {cards.map((camera) => {
              const presentation = statusPresentation(camera);
              const selected = draft.cameraId === camera.camara.id;
              const readings = camera.ultimo_registro?.temperaturas;
              return <View key={camera.camara.id} style={[styles.cameraCard, selected && styles.cameraSelected]}>
                <View style={styles.cameraHeader}>
                  <View style={styles.cameraTitle}>
                    <EntityCode value={camera.camara.codigo} large />
                    <Text style={styles.cameraName}>{camera.camara.nombre}</Text>
                  </View>
                  <EstibaSignal label={presentation.label} tone={presentation.tone} />
                </View>
                <Text style={styles.muted}>{presentation.detail}</Text>
                {readings ? <View style={styles.readings}>
                  <Text style={styles.reading}>Inicio <Text style={styles.readingValue}>{readings.inicio_c.toFixed(2)} °C</Text></Text>
                  <Text style={styles.reading}>Medio <Text style={styles.readingValue}>{readings.medio_c.toFixed(2)} °C</Text></Text>
                  <Text style={styles.reading}>Fondo <Text style={styles.readingValue}>{readings.fondo_c.toFixed(2)} °C</Text></Text>
                </View> : null}
                {camera.requiere_control && canRegister ? <EstibaButton
                  label={selected ? 'Cámara seleccionada' : 'Registrar control'}
                  variant={selected ? 'secondary' : 'primary'}
                  disabled={lockedAttempt || selected}
                  onPress={() => selectCamera(camera)}
                /> : null}
              </View>;
            })}
          </View>
        </EstibaPanel>

        <EstibaPanel title="Captura ambiental" style={styles.formPanel}>
          {!canRegister ? <EstibaAlert title="Perfil de consulta" detail="Puedes revisar la vigencia, pero este perfil no registra controles ambientales." tone="info" /> : null}
          {canRegister && !hasSelection ? <EstibaEmpty title="Selecciona una cámara pendiente" description="Las cámaras vencidas aparecen primero. Una lectura vigente no admite otro control dentro de la misma hora." /> : null}
          {canRegister && hasSelection ? <>
            <View style={styles.selectedCamera}>
              <Text style={styles.eyebrow}>CÁMARA SELECCIONADA</Text>
              <EntityCode value={selectedCamera?.camara.codigo ?? draft.cameraCode ?? 'Cámara no disponible'} large />
              <Text style={styles.muted}>{selectedCamera?.camara.nombre ?? 'La cámara ya no aparece en el estado actual.'}</Text>
            </View>
            <View style={[styles.fields, compact && styles.fieldsCompact]}>
              {(Object.keys(labels) as Array<keyof typeof labels>).map((field) => <View key={field} style={styles.field}>
                <EstibaField
                  label={`${labels[field]} (°C)`}
                  value={draft[field]}
                  onChangeText={(value) => changeField(field, value)}
                  editable={!lockedAttempt && !submitting}
                  keyboardType="numbers-and-punctuation"
                  placeholder="Ej. -0,80"
                  error={fieldErrors[field]}
                  selectTextOnFocus
                />
              </View>)}
            </View>
            {confirming || lockedAttempt ? <View style={styles.confirmation}>
              <Text style={styles.confirmationTitle}>Confirma las tres lecturas</Text>
              <Text style={styles.confirmationValues}>
                Inicio {draft.start} °C · Medio {draft.middle} °C · Fondo {draft.end} °C
              </Text>
              <Text style={styles.muted}>El registro quedará asociado a {auth.usuario.nombre} y {auth.dispositivo.codigo}.</Text>
            </View> : null}
            {submitError ? <EstibaAlert title="El envío no quedó confirmado" detail={submitError} tone="critical" live /> : null}
            <View style={styles.formActions}>
              {!confirming && !lockedAttempt ? <EstibaButton label="Revisar lecturas" onPress={review} /> : null}
              {confirming || lockedAttempt ? <EstibaButton
                label={lockedAttempt ? 'Reintentar mismo envío' : 'Confirmar y enviar'}
                variant="confirm"
                busy={submitting}
                onPress={() => void submit()}
              /> : null}
              {confirming && !lockedAttempt ? <EstibaButton label="Volver a editar" variant="secondary" onPress={() => setConfirming(false)} /> : null}
              {lockedAttempt ? <EstibaButton label="Actualizar y descartar intento" variant="critical" disabled={submitting} onPress={() => void discardAttempt()} /> : null}
            </View>
          </> : null}
          {!draftReady ? <Text style={styles.muted}>Revisando capturas pendientes del equipo…</Text> : null}
        </EstibaPanel>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: t.color.canvas },
  content: { padding: t.space[6], gap: t.space[6] },
  summary: { flexDirection: 'row', flexWrap: 'wrap', gap: t.space[3] },
  summaryItem: { minWidth: 170, minHeight: 92, flexGrow: 1, padding: t.space[4], borderWidth: 1, borderTopWidth: 4, backgroundColor: t.color.surface },
  summaryDue: { borderColor: t.signal.critical.border, borderTopColor: t.signal.critical.text },
  summaryOk: { borderColor: t.color.border, borderTopColor: t.signal.success.text },
  summaryValue: { color: t.color.text, fontSize: t.fontSize.display, fontWeight: t.fontWeight.bold, fontVariant: ['tabular-nums'] },
  summaryLabel: { color: t.color.muted, fontSize: t.fontSize.small, fontWeight: t.fontWeight.strong },
  identity: { minWidth: 250, flexGrow: 2, justifyContent: 'center', padding: t.space[4], borderWidth: 1, borderColor: t.color.border, backgroundColor: t.color.surface, gap: t.space[1] },
  identityLabel: { color: t.color.muted, fontSize: t.fontSize.caption, fontWeight: t.fontWeight.bold, letterSpacing: 1 },
  identityValue: { color: t.color.text, fontSize: t.fontSize.heading, fontWeight: t.fontWeight.bold },
  identityDetail: { color: t.color.muted, fontSize: t.fontSize.small },
  workspace: { flexDirection: 'row', alignItems: 'flex-start', gap: t.space[4] },
  workspaceCompact: { flexDirection: 'column' },
  cameraPanel: { flex: 3, minWidth: 0 },
  formPanel: { flex: 2, minWidth: 0 },
  cameraGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: t.space[3] },
  cameraCard: { minWidth: 260, flexBasis: 300, flexGrow: 1, padding: t.space[4], borderWidth: 1, borderColor: t.color.border, backgroundColor: t.color.subtle, gap: t.space[3] },
  cameraSelected: { borderWidth: 3, borderColor: t.color.primary, backgroundColor: t.color.selected },
  cameraHeader: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'flex-start', justifyContent: 'space-between', gap: t.space[3] },
  cameraTitle: { flex: 1, minWidth: 130, gap: t.space[1] },
  cameraName: { color: t.color.text, fontSize: t.fontSize.body, fontWeight: t.fontWeight.strong },
  muted: { color: t.color.muted, fontSize: t.fontSize.small, lineHeight: t.fontSize.small * t.lineHeight.body },
  readings: { flexDirection: 'row', flexWrap: 'wrap', gap: t.space[3] },
  reading: { color: t.color.muted, fontSize: t.fontSize.small },
  readingValue: { color: t.color.text, fontWeight: t.fontWeight.bold, fontVariant: ['tabular-nums'] },
  selectedCamera: { paddingBottom: t.space[3], borderBottomWidth: 1, borderBottomColor: t.color.border, gap: t.space[1] },
  eyebrow: { color: t.color.muted, fontSize: t.fontSize.caption, fontWeight: t.fontWeight.bold, letterSpacing: 1 },
  fields: { flexDirection: 'row', gap: t.space[3] },
  fieldsCompact: { flexDirection: 'column' },
  field: { flex: 1, minWidth: 120 },
  confirmation: { padding: t.space[4], borderWidth: 1, borderColor: t.color.inputBorder, backgroundColor: t.color.subtle, gap: t.space[2] },
  confirmationTitle: { color: t.color.text, fontSize: t.fontSize.heading, fontWeight: t.fontWeight.bold },
  confirmationValues: { color: t.color.text, fontSize: t.fontSize.body, fontWeight: t.fontWeight.strong, fontVariant: ['tabular-nums'] },
  formActions: { flexDirection: 'row', flexWrap: 'wrap', gap: t.space[3] },
});
