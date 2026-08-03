import * as Crypto from 'expo-crypto';
import { ReactNode, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { AuthSession } from '../domain/estiba';
import { ProcessDelivery, ProcessLot, ProcessSummary } from '../domain/frutaProceso';
import {
  annulProcessDelivery,
  createProcessDelivery,
  getProcessSummary,
  listProcessLots,
} from '../services/frutaProcesoApi';
import { colors } from '../theme/colors';

type Props = { auth: AuthSession; baseUrl: string; onLogout: () => void };
type StatusFilter = 'abiertos' | 'completados';
type Action =
  | { type: 'deliver'; lot: ProcessLot }
  | { type: 'annul'; lot: ProcessLot; delivery: ProcessDelivery }
  | null;

const EMPTY_SUMMARY: ProcessSummary = {
  temporada: null,
  lotes_abiertos: 0,
  lotes_completados: 0,
  bins_disponibles: 0,
  bins_entregados: 0,
};

export function FrutaProcesoScreen({ auth, baseUrl, onLogout }: Props) {
  const canDeliver = auth.usuario.capacidades.puede_entregar_fruta_proceso === true;
  const [summary, setSummary] = useState(EMPTY_SUMMARY);
  const [lots, setLots] = useState<ProcessLot[]>([]);
  const [filter, setFilter] = useState<StatusFilter>('abiertos');
  const [search, setSearch] = useState('');
  const [busy, setBusy] = useState(true);
  const [actionBusy, setActionBusy] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [expanded, setExpanded] = useState<string | null>(null);
  const [action, setAction] = useState<Action>(null);
  const [operationId, setOperationId] = useState('');
  const [quantity, setQuantity] = useState('');
  const [line, setLine] = useState('');
  const [shift, setShift] = useState<'A' | 'B' | ''>('');
  const [orderNumber, setOrderNumber] = useState('');
  const [observation, setObservation] = useState('');
  const [annulReason, setAnnulReason] = useState('');

  useEffect(() => {
    void load();
    const refresh = setInterval(() => { void load(true); }, 15_000);
    return () => clearInterval(refresh);
  }, [filter]);

  const visibleLots = useMemo(() => {
    const needle = search.trim().toLocaleLowerCase('es-CL');
    if (!needle) return lots;
    return lots.filter((lot) => [
      lot.numero_lote,
      lot.cliente?.nombre,
      lot.camara?.codigo,
      lot.producto.csg,
      lot.producto.predio,
      ...lot.entregas.map((delivery) => delivery.numero_orden),
    ].some((value) => String(value ?? '').toLocaleLowerCase('es-CL').includes(needle)));
  }, [lots, search]);

  async function load(silent = false) {
    if (!silent) { setBusy(true); setError(''); setMessage(''); }
    try {
      const [nextSummary, nextLots] = await Promise.all([
        getProcessSummary(baseUrl, auth.token),
        listProcessLots(baseUrl, auth.token, '', filter),
      ]);
      setSummary(nextSummary); setLots(nextLots);
    } catch (reason) { if (!silent) setError(errorMessage(reason)); }
    finally { if (!silent) setBusy(false); }
  }

  function openDelivery(lot: ProcessLot) {
    setAction({ type: 'deliver', lot }); setOperationId(Crypto.randomUUID());
    setQuantity(''); setLine(''); setShift(''); setOrderNumber(''); setObservation(''); setError('');
  }

  function openAnnul(lot: ProcessLot, delivery: ProcessDelivery) {
    setAction({ type: 'annul', lot, delivery }); setOperationId(Crypto.randomUUID());
    setAnnulReason(''); setError('');
  }

  function closeAction() {
    if (actionBusy) return;
    setAction(null); setError('');
  }

  async function submitAction() {
    if (!action) return;
    setError(''); setMessage('');
    if (action.type === 'deliver') {
      const numericQuantity = Number(quantity);
      if (!Number.isInteger(numericQuantity) || numericQuantity < 1 || numericQuantity > action.lot.progreso.disponibles) {
        setError(`Ingresa entre 1 y ${action.lot.progreso.disponibles} bins.`); return;
      }
      if (!line.trim() || !shift || !orderNumber.trim()) {
        setError('Completa línea, turno y número de orden.'); return;
      }
      setActionBusy(true);
      try {
        const updated = await createProcessDelivery(baseUrl, auth.token, action.lot.id, {
          operacion_id: operationId,
          cantidad_envases: numericQuantity,
          linea_proceso: line.trim(),
          turno: shift,
          numero_orden: orderNumber.trim(),
          observacion: observation.trim() || null,
        });
        replaceLot(updated); setAction(null); setMessage(`Viaje registrado: ${numericQuantity} bins.`);
        setSummary(await getProcessSummary(baseUrl, auth.token));
        if (updated.estado === 'entregado_proceso' && filter === 'abiertos') {
          setLots((current) => current.filter((lot) => lot.id !== updated.id));
        }
      } catch (reason) { setError(errorMessage(reason)); }
      finally { setActionBusy(false); }
      return;
    }

    if (annulReason.trim().length < 5) {
      setError('Ingresa un motivo de al menos 5 caracteres.'); return;
    }
    setActionBusy(true);
    try {
      const updated = await annulProcessDelivery(
        baseUrl,
        auth.token,
        action.delivery.id,
        operationId,
        annulReason.trim(),
      );
      replaceLot(updated); setAction(null); setMessage('Entrega anulada y saldo restituido.');
      setSummary(await getProcessSummary(baseUrl, auth.token));
    } catch (reason) { setError(errorMessage(reason)); }
    finally { setActionBusy(false); }
  }

  function replaceLot(updated: ProcessLot) {
    setLots((current) => current.map((lot) => lot.id === updated.id ? updated : lot));
  }

  return (
    <View style={styles.screen}>
      <View style={styles.header}>
        <View><Text style={styles.eyebrow}>MATERIA PRIMA · CÁMARA → PACKING</Text><Text style={styles.title}>Fruta a proceso</Text><Text style={styles.subtitle}>{summary.temporada ? `${summary.temporada.nombre} · saldo en tiempo real` : 'Sin temporada activa'}</Text></View>
        <View style={styles.headerActions}><Pressable disabled={busy} onPress={() => void load()} style={styles.secondary}><Text style={styles.secondaryText}>↻ Actualizar</Text></Pressable><Pressable onPress={onLogout} style={styles.secondary}><Text style={styles.secondaryText}>Salir</Text></Pressable></View>
      </View>

      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <View style={styles.metrics}>
          <Metric label="Lotes abiertos" value={summary.lotes_abiertos} />
          <Metric label="Bins disponibles" value={summary.bins_disponibles} accent />
          <Metric label="Bins entregados" value={summary.bins_entregados} />
          <Metric label="Completados" value={summary.lotes_completados} />
        </View>

        <View style={styles.filters}>
          <View style={styles.tabs}><FilterButton active={filter === 'abiertos'} label="Abiertos" onPress={() => setFilter('abiertos')} /><FilterButton active={filter === 'completados'} label="Completados" onPress={() => setFilter('completados')} /></View>
          <TextInput onChangeText={setSearch} placeholder="Buscar lote, cámara, CSG u orden" placeholderTextColor={colors.muted} style={styles.search} value={search} />
        </View>

        {message ? <Text style={styles.message}>{message}</Text> : null}
        {error && !action ? <Text style={styles.error}>{error}</Text> : null}
        {busy ? <ActivityIndicator color={colors.cyan} size="large" style={styles.loader} /> : null}
        {!busy && !visibleLots.length ? <Text style={styles.empty}>No hay lotes de bins en este estado.</Text> : null}
        <View style={styles.lots}>
          {visibleLots.map((lot) => (
            <View key={lot.id} style={styles.lotCard}>
              <View style={styles.lotHeading}><View><Text style={styles.lotNumber}>{lot.numero_lote}</Text><Text style={styles.lotCopy}>{lot.cliente?.nombre} · {lot.camara?.codigo}</Text></View><Text style={styles.badge}>{stateLabel(lot.estado)}</Text></View>
              <Text style={styles.product}>{[lot.producto.especie, lot.producto.variedad, lot.producto.calibre].filter(Boolean).join(' · ')}</Text>
              <Text style={styles.origin}>CSG {lot.producto.csg} · {lot.producto.predio}</Text>
              <View style={styles.progressLabels}><Text style={styles.progressStrong}>{lot.progreso.entregados}/{lot.progreso.total} entregados</Text><Text style={styles.progressCopy}>{lot.progreso.disponibles} disponibles</Text></View>
              <View style={styles.progressTrack}><View style={[styles.progressFill, { width: `${Math.min(100, lot.progreso.porcentaje)}%` }]} /></View>
              {canDeliver && lot.progreso.disponibles > 0 ? <Pressable onPress={() => openDelivery(lot)} style={styles.primary}><Text style={styles.primaryText}>+ Registrar viaje físico</Text></Pressable> : null}
              <Pressable onPress={() => setExpanded(expanded === lot.id ? null : lot.id)} style={styles.historyToggle}><Text style={styles.historyToggleText}>{expanded === lot.id ? 'Ocultar movimientos' : `Ver movimientos (${lot.entregas.length})`}</Text></Pressable>
              {expanded === lot.id ? <View style={styles.history}>{lot.entregas.length ? lot.entregas.map((delivery) => <DeliveryRow delivery={delivery} key={delivery.id} onAnnul={() => openAnnul(lot, delivery)} />) : <Text style={styles.historyEmpty}>Sin viajes registrados.</Text>}</View> : null}
            </View>
          ))}
        </View>
      </ScrollView>

      <Modal animationType="slide" onRequestClose={closeAction} transparent visible={action !== null}>
        <View style={styles.modalBackdrop}><View style={styles.modalCard}>
          <ScrollView contentContainerStyle={styles.modalContent} keyboardShouldPersistTaps="handled">
          {action?.type === 'deliver' ? <>
            <Text style={styles.eyebrow}>VIAJE FÍSICO A PACKING</Text><Text style={styles.modalTitle}>Entregar {action.lot.numero_lote}</Text><Text style={styles.modalCopy}>{action.lot.progreso.disponibles} bins disponibles en {action.lot.camara?.codigo}.</Text>
            <Field label="Cantidad de bins *"><TextInput keyboardType="number-pad" onChangeText={setQuantity} placeholder="0" placeholderTextColor={colors.muted} style={styles.input} value={quantity} /></Field>
            <Field label="Línea de proceso *"><TextInput onChangeText={setLine} placeholder="Ej. Línea 1" placeholderTextColor={colors.muted} style={styles.input} value={line} /></Field>
            <Text style={styles.fieldLabel}>Turno *</Text><View style={styles.shiftRow}><FilterButton active={shift === 'A'} label="A" onPress={() => setShift('A')} /><FilterButton active={shift === 'B'} label="B" onPress={() => setShift('B')} /></View>
            <Field label="N° de orden *"><TextInput onChangeText={setOrderNumber} placeholder="Orden de Packing" placeholderTextColor={colors.muted} style={styles.input} value={orderNumber} /></Field>
            <Field label="Observación"><TextInput multiline onChangeText={setObservation} placeholder="Opcional" placeholderTextColor={colors.muted} style={[styles.input, styles.textarea]} value={observation} /></Field>
          </> : action?.type === 'annul' ? <>
            <Text style={styles.eyebrow}>CORRECCIÓN TRAZABLE</Text><Text style={styles.modalTitle}>Anular entrega de {action.delivery.cantidad_envases} bins</Text><Text style={styles.modalCopy}>{action.delivery.linea_proceso} · turno {action.delivery.turno} · orden {action.delivery.numero_orden}</Text>
            <Field label="Motivo obligatorio *"><TextInput multiline onChangeText={setAnnulReason} placeholder="Explica la corrección" placeholderTextColor={colors.muted} style={[styles.input, styles.textarea]} value={annulReason} /></Field>
          </> : null}
          {error ? <Text style={styles.error}>{error}</Text> : null}
          <View style={styles.modalActions}><Pressable disabled={actionBusy} onPress={closeAction} style={styles.secondary}><Text style={styles.secondaryText}>Cancelar</Text></Pressable><Pressable disabled={actionBusy} onPress={() => void submitAction()} style={[styles.primary, actionBusy && styles.disabled]}>{actionBusy ? <ActivityIndicator color={colors.accentText} /> : <Text style={styles.primaryText}>{action?.type === 'annul' ? 'Anular entrega' : 'Confirmar viaje'}</Text>}</Pressable></View>
          </ScrollView>
        </View></View>
      </Modal>
    </View>
  );
}

function Metric({ label, value, accent = false }: { label: string; value: number; accent?: boolean }) {
  return <View style={styles.metric}><Text style={styles.metricLabel}>{label}</Text><Text style={[styles.metricValue, accent && styles.metricAccent]}>{value}</Text></View>;
}
function FilterButton({ active, label, onPress }: { active: boolean; label: string; onPress: () => void }) {
  return <Pressable onPress={onPress} style={[styles.filterButton, active && styles.filterActive]}><Text style={[styles.filterText, active && styles.filterTextActive]}>{label}</Text></Pressable>;
}
function Field({ label, children }: { label: string; children: ReactNode }) {
  return <View style={styles.field}><Text style={styles.fieldLabel}>{label}</Text>{children}</View>;
}
function DeliveryRow({ delivery, onAnnul }: { delivery: ProcessDelivery; onAnnul: () => void }) {
  return <View style={[styles.delivery, delivery.anulado && styles.deliveryVoid]}><View style={styles.deliveryText}><Text style={styles.deliveryQuantity}>{delivery.cantidad_envases} bins</Text><Text style={styles.deliveryDestination}>{delivery.linea_proceso} · turno {delivery.turno} · orden {delivery.numero_orden}</Text><Text style={styles.deliveryMeta}>{delivery.entregado_por?.nombre} · {formatDate(delivery.entregado_at)}{delivery.anulado ? ` · ANULADA: ${delivery.motivo_anulacion}` : ''}</Text></View>{delivery.puede_anular ? <Pressable onPress={onAnnul} style={styles.annul}><Text style={styles.annulText}>Anular</Text></Pressable> : null}</View>;
}
function stateLabel(value: ProcessLot['estado']) { return value === 'asignado_camara' ? 'Disponible' : value === 'entrega_parcial_proceso' ? 'Parcial' : 'Completado'; }
function formatDate(value: string) { const date = new Date(value); return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' }); }
function errorMessage(reason: unknown) { return reason instanceof Error ? reason.message : 'No fue posible completar la operación.'; }

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background }, header: { paddingHorizontal: 16, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: colors.border, backgroundColor: colors.backgroundDeep, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 },
  eyebrow: { color: colors.amber, fontSize: 9, fontWeight: '900', letterSpacing: 1 }, title: { color: colors.text, fontSize: 25, fontWeight: '900' }, subtitle: { color: colors.muted, fontSize: 9, marginTop: 2 }, headerActions: { flexDirection: 'row', gap: 6 }, content: { padding: 13, paddingBottom: 40 },
  metrics: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 }, metric: { minWidth: '47%', flexGrow: 1, borderWidth: 1, borderColor: colors.border, borderRadius: 12, backgroundColor: colors.panel, padding: 12 }, metricLabel: { color: colors.muted, fontSize: 9, fontWeight: '900', textTransform: 'uppercase' }, metricValue: { color: colors.text, fontSize: 24, fontWeight: '900', marginTop: 4 }, metricAccent: { color: colors.amber },
  filters: { marginTop: 11, gap: 8 }, tabs: { flexDirection: 'row', gap: 7 }, filterButton: { minWidth: 70, paddingHorizontal: 14, paddingVertical: 10, borderRadius: 9, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel, alignItems: 'center' }, filterActive: { backgroundColor: colors.cyan, borderColor: colors.cyan }, filterText: { color: colors.muted, fontWeight: '900' }, filterTextActive: { color: colors.accentText }, search: { minHeight: 45, borderWidth: 1, borderColor: colors.border, borderRadius: 10, backgroundColor: colors.backgroundDeep, color: colors.text, paddingHorizontal: 12 },
  loader: { marginTop: 35 }, empty: { color: colors.muted, textAlign: 'center', paddingVertical: 45 }, lots: { marginTop: 10, gap: 10 }, lotCard: { borderWidth: 1, borderColor: colors.border, borderRadius: 14, backgroundColor: colors.panel, padding: 13 }, lotHeading: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8 }, lotNumber: { color: colors.text, fontSize: 20, fontWeight: '900' }, lotCopy: { color: colors.muted, fontSize: 9, marginTop: 3 }, badge: { color: colors.cyan, borderWidth: 1, borderColor: colors.cyanDark, borderRadius: 999, paddingHorizontal: 8, paddingVertical: 5, fontSize: 8, fontWeight: '900', textTransform: 'uppercase' }, product: { color: colors.amber, fontWeight: '900', marginTop: 11 }, origin: { color: colors.muted, fontSize: 9, marginTop: 4 },
  progressLabels: { marginTop: 12, flexDirection: 'row', justifyContent: 'space-between' }, progressStrong: { color: colors.text, fontWeight: '900', fontSize: 11 }, progressCopy: { color: colors.muted, fontSize: 10 }, progressTrack: { height: 9, borderRadius: 999, backgroundColor: colors.backgroundDeep, overflow: 'hidden', marginTop: 7, marginBottom: 11 }, progressFill: { height: '100%', backgroundColor: colors.cyan },
  primary: { minHeight: 45, borderRadius: 10, backgroundColor: colors.cyan, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16 }, primaryText: { color: colors.accentText, fontWeight: '900' }, secondary: { minHeight: 39, borderRadius: 9, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 11 }, secondaryText: { color: colors.text, fontWeight: '800', fontSize: 10 }, historyToggle: { alignItems: 'center', paddingTop: 11 }, historyToggleText: { color: colors.muted, fontSize: 10, fontWeight: '800' }, history: { marginTop: 9, paddingTop: 9, borderTopWidth: 1, borderTopColor: colors.border, gap: 7 }, historyEmpty: { color: colors.muted, fontSize: 9 },
  delivery: { flexDirection: 'row', alignItems: 'center', gap: 8, borderWidth: 1, borderColor: colors.border, borderRadius: 9, backgroundColor: colors.backgroundDeep, padding: 9 }, deliveryVoid: { opacity: .5 }, deliveryText: { flex: 1 }, deliveryQuantity: { color: colors.cyan, fontWeight: '900' }, deliveryDestination: { color: colors.text, fontSize: 9, fontWeight: '800', marginTop: 3 }, deliveryMeta: { color: colors.muted, fontSize: 8, marginTop: 3 }, annul: { borderWidth: 1, borderColor: colors.red, borderRadius: 7, paddingHorizontal: 8, paddingVertical: 7 }, annulText: { color: colors.red, fontSize: 9, fontWeight: '900' },
  modalBackdrop: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(1,8,12,.82)' }, modalCard: { maxHeight: '94%', borderTopLeftRadius: 20, borderTopRightRadius: 20, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel }, modalContent: { padding: 18, paddingBottom: 24 }, modalTitle: { color: colors.text, fontSize: 22, fontWeight: '900', marginTop: 3 }, modalCopy: { color: colors.muted, fontSize: 10, marginTop: 4, marginBottom: 8 }, field: { marginTop: 10, gap: 5 }, fieldLabel: { color: colors.text, fontSize: 10, fontWeight: '800', marginTop: 8 }, input: { minHeight: 44, borderWidth: 1, borderColor: colors.border, borderRadius: 9, backgroundColor: colors.backgroundDeep, color: colors.text, paddingHorizontal: 11 }, textarea: { minHeight: 72, paddingTop: 10, textAlignVertical: 'top' }, shiftRow: { flexDirection: 'row', gap: 8, marginTop: 5 }, modalActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 8, marginTop: 15, paddingTop: 13, borderTopWidth: 1, borderTopColor: colors.border },
  error: { color: colors.red, marginTop: 10, fontWeight: '800' }, message: { color: colors.cyan, borderWidth: 1, borderColor: colors.cyanDark, borderRadius: 9, backgroundColor: colors.backgroundDeep, padding: 10, marginTop: 10 }, disabled: { opacity: .55 },
});
