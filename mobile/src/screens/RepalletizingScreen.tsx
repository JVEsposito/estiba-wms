import * as Crypto from 'expo-crypto';
import { ReactNode, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { AuthSession } from '../domain/estiba';
import { Repalletizing, RepalletizingFolio } from '../domain/repaletizaje';
import {
  createRepalletizing,
  findRepalletizingFolio,
  listRepalletizings,
} from '../services/repaletizajeApi';
import { colors } from '../theme/colors';

type Props = {
  auth: AuthSession;
  baseUrl: string;
  onLogout: () => void;
};

type Source = RepalletizingFolio & { aporte: string };
type ResultType = 'pallet' | 'saldo';
type FolioStrategy = 'conservar' | 'nuevo';
type HardField = 'cliente' | 'especie' | 'marca' | 'condicion_termica';
type MixField = 'variedad' | 'calibre' | 'envase' | 'categoria' | 'csg' | 'predio' | 'cuartel';

const HARD_FIELDS: Array<{ key: HardField; label: string }> = [
  { key: 'cliente', label: 'cliente' },
  { key: 'especie', label: 'especie' },
  { key: 'marca', label: 'marca' },
  { key: 'condicion_termica', label: 'estado térmico' },
];

const MIX_FIELDS: Array<{ key: MixField; label: string }> = [
  { key: 'variedad', label: 'variedad' },
  { key: 'calibre', label: 'calibre' },
  { key: 'envase', label: 'envase' },
  { key: 'categoria', label: 'categoría' },
  { key: 'csg', label: 'CSG' },
  { key: 'predio', label: 'predio' },
  { key: 'cuartel', label: 'cuartel' },
];

export function RepalletizingScreen({ auth, baseUrl, onLogout }: Props) {
  const [resultType, setResultType] = useState<ResultType>('pallet');
  const [strategy, setStrategy] = useState<FolioStrategy>('conservar');
  const [capacity, setCapacity] = useState('120');
  const [resultNumber, setResultNumber] = useState('');
  const [keptId, setKeptId] = useState('');
  const [lookup, setLookup] = useState('');
  const [sources, setSources] = useState<Source[]>([]);
  const [history, setHistory] = useState<Repalletizing[]>([]);
  const [busy, setBusy] = useState(false);
  const [loadingHistory, setLoadingHistory] = useState(true);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    void reloadHistory();
  }, []);

  const total = useMemo(
    () => sources.reduce((sum, source) => sum + numeric(source.aporte), 0),
    [sources],
  );

  const hardMismatches = useMemo(
    () => HARD_FIELDS.filter(({ key }) => distinctCount(sources, key) > 1),
    [sources],
  );

  const mixFields = useMemo(
    () => MIX_FIELDS.filter(({ key }) => distinctCount(sources, key) > 1),
    [sources],
  );

  const actualResultNumber = strategy === 'conservar'
    ? sources.find((source) => source.id === keptId)?.numero_folio ?? ''
    : resultNumber.trim().toUpperCase();

  async function reloadHistory() {
    setLoadingHistory(true);
    try {
      setHistory(await listRepalletizings(baseUrl, auth.token));
    } catch (reason) {
      setError(messageOf(reason));
    } finally {
      setLoadingHistory(false);
    }
  }

  async function addSource() {
    const number = lookup.trim().toUpperCase();
    setError('');
    setMessage('');
    if (!number) {
      setError('Escanea o escribe un folio.');
      return;
    }
    if (sources.some((source) => source.numero_folio === number)) {
      setError('Ese folio ya fue agregado.');
      return;
    }

    setBusy(true);
    try {
      const source = await findRepalletizingFolio(baseUrl, auth.token, number);
      if (!source.existe) {
        throw new Error('El folio no existe.');
      }
      if (!source.activo || source.tipo_bulto !== 'saldo' || source.cantidad_cajas < 1) {
        throw new Error('El folio no es un saldo activo con cajas disponibles.');
      }
      if (!['pendiente_prefrio', 'prefrio_aprobado'].includes(source.condicion_termica)) {
        throw new Error('El folio posee un estado térmico transitorio o retenido.');
      }

      const target = numeric(capacity);
      const remaining = Math.max(0, target - total);
      const contribution = resultType === 'pallet'
        ? Math.min(source.cantidad_cajas, remaining || source.cantidad_cajas)
        : source.cantidad_cajas;
      setSources((current) => [
        ...current,
        { ...source, aporte: String(contribution) },
      ]);
      if (!keptId) {
        setKeptId(source.id);
      }
      setLookup('');
    } catch (reason) {
      setError(messageOf(reason));
    } finally {
      setBusy(false);
    }
  }

  function updateContribution(id: string, value: string) {
    setSources((current) => current.map((source) => source.id === id
      ? { ...source, aporte: value.replace(/[^0-9]/g, '') }
      : source));
  }

  function removeSource(id: string) {
    setSources((current) => current.filter((source) => source.id !== id));
    if (keptId === id) {
      setKeptId('');
    }
  }

  function clearForm() {
    setSources([]);
    setLookup('');
    setResultNumber('');
    setKeptId('');
    setError('');
  }

  async function submit() {
    setError('');
    setMessage('');
    const target = numeric(capacity);

    if (sources.length < 2) {
      setError('Agrega al menos dos saldos.');
      return;
    }
    if (hardMismatches.length) {
      setError('Cliente, especie, marca y estado térmico deben coincidir.');
      return;
    }
    if (!actualResultNumber) {
      setError('Define el folio resultante.');
      return;
    }
    if (sources.some((source) => (
      numeric(source.aporte) < 1 || numeric(source.aporte) > source.cantidad_cajas
    ))) {
      setError('Revisa las cajas aportadas por cada saldo.');
      return;
    }
    if (resultType === 'pallet' && (!target || total !== target)) {
      setError(`El pallet debe completar exactamente ${target || 'la capacidad indicada'} cajas.`);
      return;
    }
    if (resultType === 'saldo' && target && total >= target) {
      setError('El saldo consolidado debe quedar bajo la capacidad completa.');
      return;
    }

    const kept = sources.find((source) => source.id === keptId);
    if (strategy === 'conservar'
      && (!kept || numeric(kept.aporte) !== kept.cantidad_cajas)) {
      setError('El folio conservado debe aportar todas sus cajas.');
      return;
    }

    setBusy(true);
    try {
      const result = await createRepalletizing(baseUrl, auth.token, {
        operacion_id: Crypto.randomUUID(),
        tipo_resultado: resultType,
        estrategia_folio: strategy,
        numero_folio_resultante: actualResultNumber,
        folio_conservado_id: strategy === 'conservar' ? keptId : null,
        cantidad_objetivo: target || null,
        origenes: sources.map((source) => ({
          folio_id: source.id,
          cantidad_aportada: numeric(source.aporte),
        })),
        observacion: null,
      });
      setMessage(`${result.codigo}: ${result.folio_resultante.numero_folio} confirmado.`);
      clearForm();
      await reloadHistory();
    } catch (reason) {
      setError(messageOf(reason));
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.screen}>
      <View style={styles.header}>
        <View style={styles.headerCopy}>
          <Text style={styles.eyebrow}>VALIDACIÓN · REPALETIZAJES</Text>
          <Text style={styles.title}>Consolidar saldos</Text>
          <Text style={styles.subtitle}>
            Cliente, especie, marca y estado térmico nunca se mezclan.
          </Text>
        </View>
        <View style={styles.headerActions}>
          <Pressable disabled={loadingHistory} onPress={() => void reloadHistory()} style={styles.secondary}>
            <Text style={styles.secondaryText}>↻</Text>
          </Pressable>
          <Pressable onPress={onLogout} style={styles.secondary}>
            <Text style={styles.secondaryText}>Salir</Text>
          </Pressable>
        </View>
      </View>

      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <View style={styles.options}>
          <Toggle active={resultType === 'pallet'} label="Pallet completo" onPress={() => setResultType('pallet')} />
          <Toggle active={resultType === 'saldo'} label="Saldo consolidado" onPress={() => setResultType('saldo')} />
        </View>
        <View style={styles.options}>
          <Toggle active={strategy === 'conservar'} label="Conservar folio" onPress={() => setStrategy('conservar')} />
          <Toggle active={strategy === 'nuevo'} label="Otro folio" onPress={() => setStrategy('nuevo')} />
        </View>

        <Field label="Capacidad del pallet">
          <TextInput
            keyboardType="number-pad"
            onChangeText={setCapacity}
            style={styles.input}
            value={capacity}
          />
        </Field>
        {strategy === 'nuevo' ? (
          <Field label="Folio resultante">
            <TextInput
              autoCapitalize="characters"
              onChangeText={setResultNumber}
              placeholder="Escanear o escribir"
              placeholderTextColor={colors.muted}
              style={styles.input}
              value={resultNumber}
            />
          </Field>
        ) : null}

        <View style={styles.addRow}>
          <TextInput
            autoCapitalize="characters"
            onChangeText={setLookup}
            onSubmitEditing={() => void addSource()}
            placeholder="Escanear o escribir saldo"
            placeholderTextColor={colors.muted}
            returnKeyType="done"
            style={[styles.input, styles.addInput]}
            value={lookup}
          />
          <Pressable disabled={busy} onPress={() => void addSource()} style={styles.primary}>
            <Text style={styles.primaryText}>Agregar</Text>
          </Pressable>
        </View>

        {hardMismatches.length ? (
          <Text style={styles.blocked}>
            BLOQUEADO: {hardMismatches.map(({ label }) => label).join(' · ')}
          </Text>
        ) : null}
        {mixFields.length ? (
          <View style={styles.warningRow}>
            {mixFields.map(({ key, label }) => (
              <Text key={key} style={styles.warning}>⚠ MIX {label.toUpperCase()}</Text>
            ))}
          </View>
        ) : null}

        {sources.map((source) => (
          <View key={source.id} style={styles.sourceCard}>
            <Pressable
              disabled={strategy !== 'conservar'}
              onPress={() => setKeptId(source.id)}
              style={[
                styles.keepButton,
                keptId === source.id && strategy === 'conservar' && styles.keepButtonActive,
              ]}
            >
              <Text style={styles.keepText}>
                {keptId === source.id && strategy === 'conservar' ? '✓ RESULTADO' : 'CONSERVAR'}
              </Text>
            </Pressable>
            <View style={styles.sourceCopy}>
              <Text style={styles.sourceNumber}>{source.numero_folio}</Text>
              <Text style={styles.sourceMeta}>
                {source.cantidad_cajas} cajas · {source.cliente} · {source.especie}
              </Text>
              <Text style={styles.sourceMeta}>
                {source.marca} · {source.calibre} · CSG {source.csg} · {source.condicion_termica}
              </Text>
            </View>
            <Field label="Aporta">
              <TextInput
                keyboardType="number-pad"
                onChangeText={(value) => updateContribution(source.id, value)}
                style={styles.contributionInput}
                value={source.aporte}
              />
            </Field>
            <Text style={styles.remaining}>
              Queda {Math.max(0, source.cantidad_cajas - numeric(source.aporte))}
            </Text>
            <Pressable onPress={() => removeSource(source.id)}>
              <Text style={styles.remove}>Quitar</Text>
            </Pressable>
          </View>
        ))}

        <View style={styles.preview}>
          <Text style={styles.previewLabel}>RESULTADO</Text>
          <Text style={styles.previewNumber}>{actualResultNumber || 'Sin definir'}</Text>
          <Text style={styles.previewMeta}>
            {resultType === 'pallet' ? `${total}/${capacity || '—'} cajas` : `${total} cajas`}
            {' · '}{sources[0]?.condicion_termica ?? '—'}
          </Text>
        </View>

        {message ? <Text style={styles.message}>{message}</Text> : null}
        {error ? <Text style={styles.error}>{error}</Text> : null}
        <View style={styles.confirmActions}>
          <Pressable disabled={busy} onPress={clearForm} style={styles.secondaryWide}>
            <Text style={styles.secondaryText}>Limpiar</Text>
          </Pressable>
          <Pressable disabled={busy} onPress={() => void submit()} style={styles.confirmButton}>
            {busy
              ? <ActivityIndicator color={colors.accentText} />
              : <Text style={styles.primaryText}>Confirmar repaletizaje</Text>}
          </Pressable>
        </View>

        <Text style={styles.sectionTitle}>Repas recientes</Text>
        {loadingHistory ? <ActivityIndicator color={colors.cyan} /> : null}
        {!loadingHistory && !history.length ? (
          <Text style={styles.empty}>Todavía no existen repaletizajes.</Text>
        ) : null}
        {history.map((repa) => (
          <View key={repa.id} style={styles.historyCard}>
            <Text style={styles.sourceNumber}>
              {repa.codigo} · {repa.folio_resultante.numero_folio}
            </Text>
            <Text style={styles.sourceMeta}>
              {repa.tipo_resultado === 'pallet' ? 'Pallet completo' : 'Saldo'} · {repa.cantidad_resultante} cajas · {repa.condicion_termica}
            </Text>
            {repa.campos_mix.length ? (
              <Text style={styles.historyWarning}>MIX: {repa.campos_mix.join(' · ')}</Text>
            ) : null}
          </View>
        ))}
      </ScrollView>
    </View>
  );
}

function distinctCount(sources: Source[], field: HardField | MixField): number {
  return new Set(sources.map((source) => normalize(source[field]))).size;
}

function normalize(value: unknown): string {
  return String(value ?? '').trim().toUpperCase();
}

function numeric(value: string): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function messageOf(reason: unknown): string {
  return reason instanceof Error
    ? reason.message
    : 'No fue posible completar la operación.';
}

function Toggle({ active, label, onPress }: {
  active: boolean;
  label: string;
  onPress: () => void;
}) {
  return (
    <Pressable onPress={onPress} style={[styles.toggle, active && styles.toggleActive]}>
      <Text style={[styles.toggleText, active && styles.toggleTextActive]}>{label}</Text>
    </Pressable>
  );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <View style={styles.field}>
      <Text style={styles.fieldLabel}>{label}</Text>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 12,
    padding: 18,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  headerCopy: { flex: 1 },
  headerActions: { flexDirection: 'row', gap: 8 },
  eyebrow: { color: colors.cyan, fontSize: 10, fontWeight: '900', letterSpacing: 1.2 },
  title: { color: colors.text, fontSize: 26, fontWeight: '900', marginTop: 4 },
  subtitle: { color: colors.muted, fontSize: 11, marginTop: 4 },
  content: { padding: 16, gap: 12 },
  options: { flexDirection: 'row', gap: 8 },
  toggle: {
    flex: 1,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.backgroundDeep,
  },
  toggleActive: { borderColor: colors.cyan, backgroundColor: colors.cyanDark },
  toggleText: { color: colors.muted, fontWeight: '900' },
  toggleTextActive: { color: colors.text },
  field: { gap: 5 },
  fieldLabel: { color: colors.muted, fontSize: 10, fontWeight: '900' },
  input: {
    minHeight: 46,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
    color: colors.text,
    paddingHorizontal: 12,
  },
  addRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  addInput: { flex: 1 },
  primary: {
    minHeight: 46,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    backgroundColor: colors.cyan,
    paddingHorizontal: 18,
  },
  primaryText: { color: colors.accentText, fontWeight: '900' },
  secondary: {
    minHeight: 40,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 9,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: 12,
  },
  secondaryText: { color: colors.cyan, fontWeight: '900' },
  blocked: {
    color: colors.red,
    fontWeight: '900',
    borderWidth: 1,
    borderColor: colors.red,
    borderRadius: 9,
    padding: 10,
  },
  warningRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  warning: {
    color: colors.amber,
    borderWidth: 1,
    borderColor: colors.amberDark,
    borderRadius: 999,
    paddingHorizontal: 9,
    paddingVertical: 6,
    fontSize: 9,
    fontWeight: '900',
  },
  sourceCard: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    backgroundColor: colors.panel,
    padding: 10,
    gap: 8,
  },
  sourceCopy: { flex: 1 },
  sourceNumber: { color: colors.text, fontWeight: '900' },
  sourceMeta: { color: colors.muted, fontSize: 10, marginTop: 2 },
  keepButton: {
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    padding: 7,
  },
  keepButtonActive: { borderColor: colors.cyan, backgroundColor: colors.cyanDark },
  keepText: { color: colors.cyan, fontSize: 9, fontWeight: '900' },
  contributionInput: {
    width: 100,
    minHeight: 40,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    backgroundColor: colors.backgroundDeep,
    color: colors.text,
    textAlign: 'center',
  },
  remaining: { color: colors.muted, fontSize: 10 },
  remove: { color: colors.red, fontWeight: '900', fontSize: 10 },
  preview: {
    borderWidth: 1,
    borderColor: colors.cyanDark,
    borderRadius: 12,
    backgroundColor: colors.panel,
    padding: 14,
  },
  previewLabel: { color: colors.muted, fontSize: 9, fontWeight: '900' },
  previewNumber: { color: colors.cyan, fontSize: 22, fontWeight: '900', marginTop: 4 },
  previewMeta: { color: colors.muted, marginTop: 3 },
  message: { color: colors.green, fontWeight: '800' },
  error: { color: colors.red, fontWeight: '800' },
  confirmActions: { flexDirection: 'row', gap: 8 },
  secondaryWide: {
    flex: 1,
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
  },
  confirmButton: {
    flex: 2,
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    backgroundColor: colors.cyan,
  },
  sectionTitle: { color: colors.text, fontSize: 18, fontWeight: '900', marginTop: 12 },
  historyCard: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    backgroundColor: colors.panel,
    padding: 11,
  },
  historyWarning: { color: colors.amber, fontSize: 10, fontWeight: '900', marginTop: 5 },
  empty: { color: colors.muted, textAlign: 'center', padding: 20 },
});
