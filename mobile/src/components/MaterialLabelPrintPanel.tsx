import * as Crypto from 'expo-crypto';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import {
  GenerateMaterialLabelsPayload,
  LabelPrintProfile,
  MaterialPrintJob,
  MaterialPrintOutcomePayload,
  MaterialReception,
} from '../domain/materialReception';
import {
  isDirectPrinterAvailable,
  sendToPrinter,
  testPrinterConnection,
} from '../../modules/estiba-printer-socket';
import { MaterialReceptionApi } from '../services/materialReceptionApi';
import {
  loadPrinterConfiguration,
  PrinterConfiguration,
  savePrinterConfiguration,
  validatePrinterConfiguration,
} from '../services/printerConfiguration';
import { colors } from '../theme/colors';

type Props = {
  api: MaterialReceptionApi;
  deviceId: string;
  reception: MaterialReception;
};

type PendingReport = {
  jobId: string;
  payload: MaterialPrintOutcomePayload;
};

const EMPTY_CONFIGURATION: PrinterConfiguration = {
  name: 'Impresora etiquetas',
  host: '',
  port: 9100,
  profileId: '',
};

export function MaterialLabelPrintPanel({ api, deviceId, reception }: Props) {
  const folios = useMemo(() => (reception.detalles || []).flatMap((detail) =>
    detail.bultos.filter((itemPackage) => itemPackage.folio).map((itemPackage) => ({
      id: itemPackage.folio!.id,
      number: itemPackage.folio!.numero_folio,
      item: `${detail.item?.codigo ?? '—'} · ${detail.item?.nombre ?? 'Ítem sin datos'}`,
      quantity: `${itemPackage.cantidad} ${detail.unidad_medida}`,
    }))), [reception]);
  const generation = useRef<{ key: string; operationId: string } | null>(null);
  const [profiles, setProfiles] = useState<LabelPrintProfile[]>([]);
  const [history, setHistory] = useState<MaterialPrintJob[]>([]);
  const [configuration, setConfiguration] = useState<PrinterConfiguration>(EMPTY_CONFIGURATION);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [copies, setCopies] = useState('1');
  const [reason, setReason] = useState('');
  const [showConfiguration, setShowConfiguration] = useState(false);
  const [busy, setBusy] = useState(true);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [pendingReport, setPendingReport] = useState<PendingReport | null>(null);

  useEffect(() => {
    void load();
  }, [api, deviceId, reception.id]);

  async function load() {
    setBusy(true);
    setError('');
    try {
      const [loadedProfiles, loadedHistory, saved] = await Promise.all([
        api.printProfiles(),
        api.printJobs(reception.id),
        loadPrinterConfiguration(deviceId),
      ]);
      setProfiles(loadedProfiles);
      setHistory(loadedHistory);
      const defaultProfile = loadedProfiles.find((profile) => profile.predeterminado)
        ?? loadedProfiles[0];
      setConfiguration({
        ...(saved ?? EMPTY_CONFIGURATION),
        profileId: loadedProfiles.some((profile) => profile.id === saved?.profileId)
          ? saved!.profileId
          : defaultProfile?.id ?? '',
      });
      setSelected(new Set(folios.map((folio) => folio.id)));
    } catch (loadError) {
      setError(errorMessage(loadError));
    } finally {
      setBusy(false);
    }
  }

  async function saveAndTest() {
    setBusy(true);
    setMessage('');
    setError('');
    try {
      const saved = await savePrinterConfiguration(deviceId, configuration);
      setConfiguration(saved);
      const result = await testPrinterConnection(saved.host, saved.port);
      if (result.status !== 'connected') throw new Error(result.message);
      setMessage(`Conexión correcta con ${saved.name} (${saved.host}:${saved.port}).`);
    } catch (testError) {
      setError(errorMessage(testError));
    } finally {
      setBusy(false);
    }
  }

  async function print() {
    if (pendingReport) {
      setError('Primero vuelve a informar el resultado pendiente; no se reenviará la etiqueta automáticamente.');
      return;
    }
    setError('');
    setMessage('');
    if (!isDirectPrinterAvailable()) {
      setError('Esta instalación no incluye impresión IP. Instala la nueva APK antes de probar.');
      return;
    }

    let printer: PrinterConfiguration;
    try {
      printer = validatePrinterConfiguration(configuration);
      await savePrinterConfiguration(deviceId, printer);
    } catch (configurationError) {
      setError(errorMessage(configurationError));
      setShowConfiguration(true);
      return;
    }
    const folioIds = [...selected].sort();
    if (!folioIds.length) {
      setError('Selecciona al menos un folio para imprimir.');
      return;
    }
    const printCopies = Number(copies);
    if (!Number.isInteger(printCopies) || printCopies < 1 || printCopies > 20) {
      setError('Las copias deben ser un número entre 1 y 20.');
      return;
    }
    const printed = new Set(history.flatMap((job) => job.folios.map((folio) => folio.id)));
    const isReprint = folioIds.some((folioId) => printed.has(folioId));
    if (isReprint && reason.trim().length < 5) {
      setError('Indica el motivo de reimpresión para los folios ya generados.');
      return;
    }

    const operationKey = JSON.stringify({
      receptionId: reception.id,
      profileId: printer.profileId,
      folioIds,
      copies: printCopies,
      reason: reason.trim() || null,
    });
    if (generation.current?.key !== operationKey) {
      generation.current = { key: operationKey, operationId: Crypto.randomUUID() };
    }
    const payload: GenerateMaterialLabelsPayload = {
      operacion_id: generation.current.operationId,
      perfil_id: printer.profileId,
      formato: 'zpl',
      canal: 'pda_directa',
      folio_ids: folioIds,
      copias: printCopies,
      motivo_reimpresion: reason.trim() || null,
    };

    setBusy(true);
    try {
      const generated = await api.generateLabels(reception.id, payload);
      const result = await sendToPrinter(printer.host, printer.port, generated.zpl);
      const outcome: MaterialPrintOutcomePayload = {
        operacion_id: Crypto.randomUUID(),
        estado: result.status === 'sent'
          ? 'enviado'
          : result.status === 'indeterminate'
            ? 'indeterminado'
            : 'fallido',
        bytes_enviados: result.bytesSent,
        error: result.status === 'sent' ? null : result.message,
        impresora: {
          nombre: printer.name,
          host: printer.host,
          puerto: printer.port,
        },
      };
      const report = { jobId: generated.jobId, payload: outcome };
      setPendingReport(report);
      await api.reportPrintOutcome(report.jobId, report.payload);
      setPendingReport(null);
      generation.current = null;
      setReason('');
      setHistory(await api.printJobs(reception.id));
      if (outcome.estado === 'enviado') {
        setMessage(`${folioIds.length} etiqueta(s) enviadas a ${printer.name}.`);
      } else if (outcome.estado === 'indeterminado') {
        setError('El envío quedó indeterminado. Revisa físicamente la impresora antes de decidir una reimpresión.');
      } else {
        setError(`No se enviaron datos a la impresora: ${result.message}`);
      }
    } catch (printError) {
      setError(errorMessage(printError));
    } finally {
      setBusy(false);
    }
  }

  async function retryPendingReport() {
    if (!pendingReport) return;
    setBusy(true);
    setError('');
    try {
      await api.reportPrintOutcome(pendingReport.jobId, pendingReport.payload);
      const outcome = pendingReport.payload.estado;
      setPendingReport(null);
      generation.current = null;
      setHistory(await api.printJobs(reception.id));
      setMessage(outcome === 'enviado'
        ? 'Resultado confirmado en la trazabilidad.'
        : 'Resultado registrado. Verifica la impresora antes de reimprimir.');
    } catch (reportError) {
      setError(`El resultado sigue pendiente de informar: ${errorMessage(reportError)}`);
    } finally {
      setBusy(false);
    }
  }

  function toggle(folioId: string) {
    setSelected((current) => {
      const next = new Set(current);
      if (next.has(folioId)) next.delete(folioId);
      else next.add(folioId);
      generation.current = null;
      return next;
    });
  }

  if (!folios.length) return null;

  const generatedFolios = new Set(history.flatMap((job) => job.folios.map((folio) => folio.id)));

  return (
    <View style={styles.panel}>
      <View style={styles.between}>
        <View style={styles.copy}>
          <Text style={styles.eyebrow}>IMPRESIÓN DIRECTA</Text>
          <Text style={styles.title}>Etiquetas por IP</Text>
          <Text style={styles.muted}>
            {configuration.host
              ? `${configuration.name} · ${configuration.host}:${configuration.port}`
              : 'Configura la impresora de esta PDA/tablet.'}
          </Text>
        </View>
        <Action label={showConfiguration ? 'Cerrar configuración' : 'Configurar impresora'} onPress={() => setShowConfiguration(!showConfiguration)} secondary />
      </View>

      {showConfiguration ? (
        <View style={styles.configuration}>
          <Input label="Nombre" value={configuration.name} onChange={(name) => setConfiguration({ ...configuration, name })} />
          <Input label="IPv4" value={configuration.host} onChange={(host) => setConfiguration({ ...configuration, host })} placeholder="192.168.1.50" />
          <Input label="Puerto RAW" value={String(configuration.port)} onChange={(port) => setConfiguration({ ...configuration, port: Number(port.replace(/\D/g, '')) || 0 })} placeholder="9100" numeric />
          <View style={styles.profileList}>
            <Text style={styles.label}>PERFIL / TAMAÑO</Text>
            {profiles.map((profile) => (
              <Pressable
                key={profile.id}
                onPress={() => setConfiguration({ ...configuration, profileId: profile.id })}
                style={[styles.profile, configuration.profileId === profile.id && styles.selected]}
              >
                <Text style={styles.profileTitle}>{profile.nombre}</Text>
                <Text style={styles.muted}>{profile.fabricante} · {profile.ancho_mm}×{profile.alto_mm} mm · {profile.dpi} dpi</Text>
              </Pressable>
            ))}
          </View>
          <Action label="Guardar y probar conexión" onPress={() => void saveAndTest()} />
        </View>
      ) : null}

      <View style={styles.folios}>
        <View style={styles.between}>
          <Text style={styles.label}>FOLIOS A IMPRIMIR</Text>
          <Action
            label={selected.size === folios.length ? 'Quitar todos' : 'Seleccionar todos'}
            onPress={() => setSelected(selected.size === folios.length
              ? new Set()
              : new Set(folios.map((folio) => folio.id)))}
            secondary
            small
          />
        </View>
        {folios.map((folio) => (
          <Pressable
            key={folio.id}
            onPress={() => toggle(folio.id)}
            style={[styles.folio, selected.has(folio.id) && styles.selected]}
          >
            <Text style={styles.checkbox}>{selected.has(folio.id) ? '✓' : '○'}</Text>
            <View style={styles.copy}>
              <Text style={styles.folioNumber}>{folio.number}{generatedFolios.has(folio.id) ? ' · REIMPRESIÓN' : ''}</Text>
              <Text style={styles.muted}>{folio.item} · {folio.quantity}</Text>
            </View>
          </Pressable>
        ))}
      </View>

      <View style={styles.controls}>
        <Input label="Copias por folio" value={copies} onChange={(value) => {
          setCopies(value.replace(/\D/g, ''));
          generation.current = null;
        }} numeric />
        <Input label="Motivo de reimpresión" value={reason} onChange={(value) => {
          setReason(value);
          generation.current = null;
        }} placeholder="Obligatorio si ya fue impresa" />
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}
      {message ? <Text style={styles.success}>{message}</Text> : null}
      {pendingReport ? (
        <View style={styles.pending}>
          <Text style={styles.error}>El servidor aún no recibió el resultado. No se reenviará la etiqueta.</Text>
          <Action label="Reintentar solo el registro" onPress={() => void retryPendingReport()} secondary />
        </View>
      ) : null}
      <View style={styles.between}>
        <Text style={styles.muted}>{history.length} trabajo(s) auditados para esta recepción.</Text>
        <Action label={`Imprimir ${selected.size} folio(s)`} onPress={() => void print()} disabled={busy || Boolean(pendingReport)} />
      </View>
      {busy ? <ActivityIndicator color={colors.cyan} /> : null}
    </View>
  );
}

function Action({ label, onPress, secondary = false, small = false, disabled = false }: {
  label: string;
  onPress: () => void;
  secondary?: boolean;
  small?: boolean;
  disabled?: boolean;
}) {
  return (
    <Pressable
      disabled={disabled}
      onPress={onPress}
      style={[styles.button, secondary && styles.buttonSecondary, small && styles.buttonSmall, disabled && styles.disabled]}
    >
      <Text style={styles.buttonText}>{label}</Text>
    </Pressable>
  );
}

function Input({ label, value, onChange, placeholder, numeric = false }: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  numeric?: boolean;
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        keyboardType={numeric ? 'number-pad' : 'default'}
        onChangeText={onChange}
        placeholder={placeholder}
        placeholderTextColor={colors.muted}
        style={styles.input}
        value={value}
      />
    </View>
  );
}

function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : 'Ocurrió un error inesperado.';
}

const styles = StyleSheet.create({
  panel: { gap: 12, padding: 15, borderRadius: 13, borderWidth: 1, borderColor: colors.cyanDark, backgroundColor: colors.panel },
  between: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap' },
  copy: { flex: 1 },
  eyebrow: { color: colors.cyan, fontSize: 9, fontWeight: '900', letterSpacing: 1.1 },
  title: { color: colors.text, fontSize: 16, fontWeight: '900', marginTop: 3 },
  muted: { color: colors.muted, fontSize: 10, lineHeight: 16, marginTop: 2 },
  configuration: { gap: 9, padding: 12, borderRadius: 11, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep },
  controls: { flexDirection: 'row', flexWrap: 'wrap', gap: 9 },
  field: { minWidth: 180, flexGrow: 1, flexBasis: 190, gap: 5 },
  label: { color: colors.muted, fontSize: 9, fontWeight: '900', textTransform: 'uppercase', letterSpacing: 0.4 },
  input: { minHeight: 42, borderRadius: 9, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep, color: colors.text, paddingHorizontal: 10, fontWeight: '700' },
  profileList: { gap: 6 },
  profile: { padding: 9, borderRadius: 8, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  profileTitle: { color: colors.text, fontSize: 11, fontWeight: '900' },
  selected: { borderColor: colors.cyan, backgroundColor: colors.selected },
  folios: { gap: 7 },
  folio: { flexDirection: 'row', alignItems: 'center', gap: 9, padding: 10, borderRadius: 9, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep },
  checkbox: { width: 20, color: colors.cyan, fontSize: 16, fontWeight: '900' },
  folioNumber: { color: colors.text, fontSize: 11, fontWeight: '900' },
  button: { alignSelf: 'flex-start', paddingHorizontal: 14, paddingVertical: 9, borderRadius: 9, backgroundColor: colors.cyan },
  buttonSecondary: { borderWidth: 1, borderColor: colors.cyanDark, backgroundColor: colors.selected },
  buttonSmall: { paddingHorizontal: 9, paddingVertical: 6 },
  buttonText: { color: colors.accentText, fontSize: 9, fontWeight: '900' },
  disabled: { opacity: 0.45 },
  error: { color: colors.red, fontSize: 10, fontWeight: '800', lineHeight: 16 },
  success: { color: colors.green, fontSize: 10, fontWeight: '800', lineHeight: 16 },
  pending: { gap: 8, padding: 10, borderRadius: 9, borderWidth: 1, borderColor: colors.blockedBorder, backgroundColor: colors.blocked },
});
