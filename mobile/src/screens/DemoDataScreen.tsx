import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  useWindowDimensions,
  View,
} from 'react-native';

import {
  createDemoClient,
  createDemoFolio,
  deleteDemoClient,
  deleteDemoFolio,
  DemoDataset,
  loadDemoDataset,
  resetDemoDatabase,
} from '../demo/demoDatabase';
import { colors } from '../theme/colors';

const emptyDataset: DemoDataset = {
  clients: [],
  folios: [],
  auditEntries: 0,
  operationalMovements: 0,
};

type DemoDataScreenProps = {
  onLogout: () => void;
};

export function DemoDataScreen({ onLogout }: DemoDataScreenProps) {
  const { width } = useWindowDimensions();
  const wide = width >= 900;
  const [dataset, setDataset] = useState<DemoDataset>(emptyDataset);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [clientCode, setClientCode] = useState('');
  const [clientName, setClientName] = useState('');
  const [folioPrefix, setFolioPrefix] = useState('');

  const [folioNumber, setFolioNumber] = useState('');
  const [selectedClientId, setSelectedClientId] = useState('');
  const [species, setSpecies] = useState('Cereza');
  const [variety, setVariety] = useState('');
  const [boxes, setBoxes] = useState('');

  const reload = useCallback(async () => {
    const next = await loadDemoDataset();
    setDataset(next);
    setSelectedClientId((current) => (
      next.clients.some((client) => client.id === current)
        ? current
        : next.clients[0]?.id ?? ''
    ));
  }, []);

  useEffect(() => {
    void reload()
      .catch((reason) => setError(messageFrom(reason)))
      .finally(() => setLoading(false));
  }, [reload]);

  async function mutate(action: () => Promise<void>, success: string) {
    setBusy(true);
    setError('');
    setNotice('');
    try {
      await action();
      await reload();
      setNotice(success);
    } catch (reason) {
      setError(messageFrom(reason));
    } finally {
      setBusy(false);
    }
  }

  async function addClient() {
    await mutate(async () => {
      await createDemoClient({ code: clientCode, name: clientName, folioPrefix });
      setClientCode('');
      setClientName('');
      setFolioPrefix('');
    }, 'Cliente guardado en la tablet.');
  }

  async function addFolio() {
    await mutate(async () => {
      await createDemoFolio({
        number: folioNumber,
        clientId: selectedClientId,
        species,
        variety,
        boxes: Number(boxes),
      });
      setFolioNumber('');
      setVariety('');
      setBoxes('');
    }, 'Folio guardado en la tablet.');
  }

  function confirmClientDeletion(id: string, name: string) {
    Alert.alert(
      'Eliminar cliente demo',
      `Se eliminarán ${name} y todos sus folios locales. Esta acción no afecta producción.`,
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Eliminar',
          style: 'destructive',
          onPress: () => void mutate(() => deleteDemoClient(id), 'Cliente demo eliminado.'),
        },
      ],
    );
  }

  function confirmFolioDeletion(id: string, number: string) {
    Alert.alert(
      'Eliminar folio demo',
      `¿Eliminar ${number} de la memoria local?`,
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Eliminar',
          style: 'destructive',
          onPress: () => void mutate(() => deleteDemoFolio(id), 'Folio demo eliminado.'),
        },
      ],
    );
  }

  function confirmReset() {
    Alert.alert(
      'Restaurar escenario inicial',
      'Se borrarán todos los datos que ingresaste en esta APK y volverán los ejemplos iniciales.',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Restaurar',
          style: 'destructive',
          onPress: () => void mutate(resetDemoDatabase, 'Escenario inicial restaurado.'),
        },
      ],
    );
  }

  if (loading) {
    return (
      <View style={styles.loading}>
        <ActivityIndicator color={colors.cyan} size="large" />
        <Text style={styles.loadingText}>Preparando base local…</Text>
      </View>
    );
  }

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      style={styles.root}
    >
      <ScrollView contentContainerStyle={styles.page} keyboardShouldPersistTaps="handled">
        <View style={styles.header}>
          <View style={styles.headerCopy}>
            <Text style={styles.eyebrow}>ESTIBA WMS DEMO · ADMINISTRACIÓN LOCAL</Text>
            <Text style={styles.title}>Datos para tu presentación</Text>
            <Text style={styles.intro}>
              Crea clientes y folios propios. Todo queda únicamente en la memoria interna de esta tablet.
            </Text>
          </View>
          <View style={styles.headerActions}>
            <View style={styles.offlineChip}>
              <View style={styles.offlineDot} />
              <Text style={styles.offlineText}>SQLITE LOCAL · SIN SERVIDOR</Text>
            </View>
            <Pressable disabled={busy} onPress={onLogout} style={styles.secondaryButton}>
              <Text style={styles.secondaryButtonText}>Cerrar demo</Text>
            </Pressable>
          </View>
        </View>

        <View style={styles.metrics}>
          <Metric label="Clientes" value={dataset.clients.length} />
          <Metric label="Folios" value={dataset.folios.length} />
          <Metric label="Movimientos" value={dataset.operationalMovements} />
          <Metric label="Acciones locales" value={dataset.auditEntries} />
        </View>

        {error ? <Message tone="error" text={error} /> : null}
        {notice ? <Message tone="success" text={notice} /> : null}

        <View style={[styles.forms, wide && styles.formsWide]}>
          <View style={styles.card}>
            <Text style={styles.cardEyebrow}>MAESTRO DEMO</Text>
            <Text style={styles.cardTitle}>Nuevo cliente</Text>
            <Text style={styles.cardCopy}>Puedes usar nombres ficticios adaptados a cada presentación.</Text>
            <DemoField
              label="Código"
              onChangeText={setClientCode}
              placeholder="CLIENTE-NORTE"
              value={clientCode}
            />
            <DemoField
              label="Nombre"
              onChangeText={setClientName}
              placeholder="Agrícola Cliente Norte"
              value={clientName}
            />
            <DemoField
              label="Prefijo de folio (máx. 6)"
              onChangeText={setFolioPrefix}
              placeholder="CN"
              value={folioPrefix}
            />
            <PrimaryButton busy={busy} label="Guardar cliente" onPress={() => void addClient()} />
          </View>

          <View style={styles.card}>
            <Text style={styles.cardEyebrow}>OPERACIÓN DEMO</Text>
            <Text style={styles.cardTitle}>Nuevo folio</Text>
            <Text style={styles.cardCopy}>El folio queda disponible para los módulos locales que sumaremos después.</Text>
            <DemoField
              label="Número de folio"
              onChangeText={setFolioNumber}
              placeholder="DEMO-000004"
              value={folioNumber}
            />
            <Text style={styles.fieldLabel}>Cliente</Text>
            <View style={styles.clientChoices}>
              {dataset.clients.map((client) => {
                const selected = selectedClientId === client.id;
                return (
                  <Pressable
                    key={client.id}
                    onPress={() => setSelectedClientId(client.id)}
                    style={[styles.clientChoice, selected && styles.clientChoiceSelected]}
                  >
                    <Text style={[styles.clientChoiceText, selected && styles.clientChoiceTextSelected]}>
                      {client.code}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            <View style={styles.inlineFields}>
              <View style={styles.inlineField}>
                <DemoField label="Especie" onChangeText={setSpecies} placeholder="Cereza" value={species} />
              </View>
              <View style={styles.inlineField}>
                <DemoField label="Variedad" onChangeText={setVariety} placeholder="Regina" value={variety} />
              </View>
              <View style={styles.smallField}>
                <DemoField
                  keyboardType="number-pad"
                  label="Cajas"
                  onChangeText={setBoxes}
                  placeholder="120"
                  value={boxes}
                />
              </View>
            </View>
            <PrimaryButton busy={busy} label="Guardar folio" onPress={() => void addFolio()} />
          </View>
        </View>

        <View style={[styles.tables, wide && styles.tablesWide]}>
          <View style={styles.listCard}>
            <View style={styles.listHeader}>
              <View>
                <Text style={styles.cardEyebrow}>DATOS EDITABLES</Text>
                <Text style={styles.listTitle}>Clientes locales</Text>
              </View>
              <Text style={styles.count}>{dataset.clients.length}</Text>
            </View>
            {dataset.clients.map((client) => (
              <View key={client.id} style={styles.listRow}>
                <View style={styles.rowCopy}>
                  <Text style={styles.rowTitle}>{client.code} · {client.name}</Text>
                  <Text style={styles.rowMeta}>Prefijo {client.folioPrefix} · activo</Text>
                </View>
                <Pressable
                  disabled={busy}
                  onPress={() => confirmClientDeletion(client.id, client.name)}
                  style={styles.deleteButton}
                >
                  <Text style={styles.deleteButtonText}>Eliminar</Text>
                </Pressable>
              </View>
            ))}
          </View>

          <View style={styles.listCard}>
            <View style={styles.listHeader}>
              <View>
                <Text style={styles.cardEyebrow}>TRAZABILIDAD INICIAL</Text>
                <Text style={styles.listTitle}>Folios locales</Text>
              </View>
              <Text style={styles.count}>{dataset.folios.length}</Text>
            </View>
            {dataset.folios.map((folio) => (
              <View key={folio.id} style={styles.listRow}>
                <View style={styles.rowCopy}>
                  <Text style={styles.rowTitle}>{folio.number} · {folio.species} {folio.variety}</Text>
                  <Text style={styles.rowMeta}>
                    {folio.clientCode} · {folio.boxes} cajas · {folio.status}
                  </Text>
                </View>
                <Pressable
                  disabled={busy}
                  onPress={() => confirmFolioDeletion(folio.id, folio.number)}
                  style={styles.deleteButton}
                >
                  <Text style={styles.deleteButtonText}>Eliminar</Text>
                </Pressable>
              </View>
            ))}
          </View>
        </View>

        <View style={styles.resetPanel}>
          <View style={styles.resetCopy}>
            <Text style={styles.resetTitle}>¿Terminaste una demostración?</Text>
            <Text style={styles.resetText}>Restaura los datos ficticios iniciales y deja la tablet lista para la próxima.</Text>
          </View>
          <Pressable disabled={busy} onPress={confirmReset} style={styles.resetButton}>
            <Text style={styles.resetButtonText}>Restaurar escenario</Text>
          </Pressable>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

function messageFrom(reason: unknown): string {
  return reason instanceof Error ? reason.message : 'No fue posible actualizar la base demo.';
}

function Metric({ label, value }: { label: string; value: number }) {
  return (
    <View style={styles.metric}>
      <Text style={styles.metricValue}>{value}</Text>
      <Text style={styles.metricLabel}>{label}</Text>
    </View>
  );
}

function Message({ tone, text }: { tone: 'error' | 'success'; text: string }) {
  return (
    <View style={[styles.message, tone === 'error' ? styles.messageError : styles.messageSuccess]}>
      <Text style={styles.messageText}>{text}</Text>
    </View>
  );
}

type DemoFieldProps = {
  keyboardType?: 'number-pad';
  label: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  value: string;
};

function DemoField({ label, ...props }: DemoFieldProps) {
  return (
    <View style={styles.field}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <TextInput
        {...props}
        autoCapitalize="characters"
        placeholderTextColor="#737D82"
        selectionColor={colors.cyan}
        style={styles.input}
      />
    </View>
  );
}

function PrimaryButton({ busy, label, onPress }: { busy: boolean; label: string; onPress: () => void }) {
  return (
    <Pressable disabled={busy} onPress={onPress} style={[styles.primaryButton, busy && styles.disabled]}>
      {busy ? <ActivityIndicator color={colors.accentText} /> : <Text style={styles.primaryButtonText}>{label} →</Text>}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.background },
  page: { padding: 22, gap: 18 },
  loading: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12, backgroundColor: colors.background },
  loadingText: { color: colors.muted, fontWeight: '800' },
  header: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 18, padding: 22, borderWidth: 1, borderColor: colors.cyanDark, borderRadius: 18, backgroundColor: colors.panel },
  headerCopy: { flex: 1, minWidth: 280 },
  headerActions: { alignItems: 'flex-end', gap: 10 },
  eyebrow: { color: colors.cyan, fontSize: 10, fontWeight: '900', letterSpacing: 1.4 },
  title: { marginTop: 6, color: colors.text, fontSize: 28, fontWeight: '900' },
  intro: { maxWidth: 720, marginTop: 6, color: colors.muted, lineHeight: 20 },
  offlineChip: { flexDirection: 'row', alignItems: 'center', gap: 7, paddingHorizontal: 11, paddingVertical: 7, borderWidth: 1, borderColor: colors.greenDark, borderRadius: 9, backgroundColor: '#10261B' },
  offlineDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.green },
  offlineText: { color: colors.green, fontSize: 9, fontWeight: '900' },
  secondaryButton: { minWidth: 130, paddingHorizontal: 13, paddingVertical: 9, borderWidth: 1, borderColor: colors.border, borderRadius: 9, alignItems: 'center' },
  secondaryButtonText: { color: colors.text, fontSize: 10, fontWeight: '900' },
  metrics: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  metric: { flexGrow: 1, flexBasis: 150, padding: 16, borderWidth: 1, borderColor: colors.border, borderRadius: 14, backgroundColor: colors.panelStrong },
  metricValue: { color: colors.text, fontSize: 26, fontWeight: '900' },
  metricLabel: { marginTop: 2, color: colors.muted, fontSize: 10, fontWeight: '800', textTransform: 'uppercase' },
  message: { padding: 12, borderWidth: 1, borderRadius: 11 },
  messageError: { borderColor: colors.red, backgroundColor: '#421B21' },
  messageSuccess: { borderColor: colors.greenDark, backgroundColor: '#10261B' },
  messageText: { color: colors.text, fontSize: 11, fontWeight: '800' },
  forms: { gap: 16 },
  formsWide: { flexDirection: 'row' },
  card: { flex: 1, padding: 20, borderWidth: 1, borderColor: colors.border, borderRadius: 16, backgroundColor: colors.panel },
  cardEyebrow: { color: colors.cyan, fontSize: 9, fontWeight: '900', letterSpacing: 1.2 },
  cardTitle: { marginTop: 5, color: colors.text, fontSize: 21, fontWeight: '900' },
  cardCopy: { marginTop: 5, marginBottom: 5, color: colors.muted, fontSize: 11, lineHeight: 16 },
  field: { marginTop: 12, gap: 6 },
  fieldLabel: { marginTop: 12, color: colors.text, fontSize: 10, fontWeight: '800' },
  input: { height: 46, paddingHorizontal: 12, borderWidth: 1, borderColor: colors.border, borderRadius: 10, backgroundColor: colors.backgroundDeep, color: colors.text, fontSize: 12 },
  clientChoices: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 7 },
  clientChoice: { paddingHorizontal: 10, paddingVertical: 8, borderWidth: 1, borderColor: colors.border, borderRadius: 9, backgroundColor: colors.backgroundDeep },
  clientChoiceSelected: { borderColor: colors.cyan, backgroundColor: colors.selected },
  clientChoiceText: { color: colors.muted, fontSize: 9, fontWeight: '900' },
  clientChoiceTextSelected: { color: colors.cyan },
  inlineFields: { flexDirection: 'row', alignItems: 'flex-end', gap: 10 },
  inlineField: { flex: 1 },
  smallField: { width: 100 },
  primaryButton: { height: 46, marginTop: 18, paddingHorizontal: 14, borderRadius: 10, backgroundColor: colors.cyan, alignItems: 'center', justifyContent: 'center' },
  primaryButtonText: { color: colors.accentText, fontSize: 11, fontWeight: '900' },
  disabled: { opacity: 0.5 },
  tables: { gap: 16 },
  tablesWide: { flexDirection: 'row', alignItems: 'flex-start' },
  listCard: { flex: 1, overflow: 'hidden', borderWidth: 1, borderColor: colors.border, borderRadius: 16, backgroundColor: colors.panel },
  listHeader: { padding: 17, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.border },
  listTitle: { marginTop: 4, color: colors.text, fontSize: 18, fontWeight: '900' },
  count: { color: colors.cyan, fontSize: 25, fontWeight: '900' },
  listRow: { minHeight: 64, paddingHorizontal: 16, paddingVertical: 11, flexDirection: 'row', alignItems: 'center', gap: 12, borderBottomWidth: 1, borderBottomColor: colors.borderSoft },
  rowCopy: { flex: 1 },
  rowTitle: { color: colors.text, fontSize: 11, fontWeight: '900' },
  rowMeta: { marginTop: 4, color: colors.muted, fontSize: 9 },
  deleteButton: { paddingHorizontal: 10, paddingVertical: 7, borderWidth: 1, borderColor: colors.red, borderRadius: 8 },
  deleteButtonText: { color: colors.red, fontSize: 9, fontWeight: '900' },
  resetPanel: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 16, padding: 18, borderWidth: 1, borderColor: colors.amberDark, borderRadius: 15, backgroundColor: '#241B10' },
  resetCopy: { flex: 1, minWidth: 240 },
  resetTitle: { color: colors.text, fontSize: 15, fontWeight: '900' },
  resetText: { marginTop: 4, color: colors.muted, fontSize: 10 },
  resetButton: { paddingHorizontal: 15, paddingVertical: 11, borderWidth: 1, borderColor: colors.amber, borderRadius: 9 },
  resetButtonText: { color: colors.amber, fontSize: 10, fontWeight: '900' },
});
