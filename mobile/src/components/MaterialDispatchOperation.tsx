import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  AppState,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';

import { AuthSession, MaterialDispatch } from '../domain/estiba';
import { EstibaApi } from '../services/estibaApi';
import { colors } from '../theme/colors';

type QueueFilter = 'activas' | 'completadas' | 'todas';

type Props = {
  api: EstibaApi;
  auth: AuthSession;
  onConnectionFailure: (reason: unknown) => void;
  onOpenPosition: (cameraId: string, positionId: string) => void;
};

const ALL_STATES: MaterialDispatch['estado'][] = [
  'pendiente',
  'parcial',
  'completado',
  'cancelado',
];

export function MaterialDispatchOperation({
  api,
  auth,
  onConnectionFailure,
  onOpenPosition,
}: Props) {
  const { width } = useWindowDimensions();
  const compact = width < 930;
  const [dispatches, setDispatches] = useState<MaterialDispatch[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [filter, setFilter] = useState<QueueFilter>('activas');
  const [busy, setBusy] = useState(true);
  const [error, setError] = useState('');
  const [lastSync, setLastSync] = useState<string | null>(null);
  const pollInFlight = useRef(false);

  const filtered = useMemo(() => dispatches.filter((dispatch) => {
    if (filter === 'activas') return dispatch.estado === 'pendiente' || dispatch.estado === 'parcial';
    if (filter === 'completadas') return dispatch.estado === 'completado' || dispatch.estado === 'cancelado';
    return true;
  }), [dispatches, filter]);
  const selected = useMemo(
    () => filtered.find((dispatch) => dispatch.id === selectedId)
      ?? filtered[0]
      ?? null,
    [filtered, selectedId],
  );
  const activeCount = dispatches.filter(
    (dispatch) => dispatch.estado === 'pendiente' || dispatch.estado === 'parcial',
  ).length;
  const withdrawals = selected?.items.flatMap((item) => (item.retiros ?? []).map((withdrawal) => ({
    ...withdrawal,
    item: item.item,
    unidad_medida: item.unidad_medida,
  }))).sort((left, right) => right.retirado_at.localeCompare(left.retirado_at)) ?? [];

  useEffect(() => {
    void refresh(false);
    const timer = setInterval(() => void refresh(true), 12000);
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') void refresh(true);
    });

    return () => {
      clearInterval(timer);
      subscription.remove();
    };
  }, []);

  async function refresh(quiet: boolean) {
    if (pollInFlight.current) return;
    pollInFlight.current = true;
    if (!quiet) setBusy(true);
    try {
      const loaded = await api.listMaterialDispatches(auth.token, ALL_STATES);
      setDispatches(loaded);
      setSelectedId((current) => loaded.some((dispatch) => dispatch.id === current)
        ? current
        : loaded.find((dispatch) => dispatch.estado === 'pendiente' || dispatch.estado === 'parcial')?.id
          ?? loaded[0]?.id
          ?? null);
      setError('');
      setLastSync(new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }));
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'No fue posible cargar los despachos.');
      onConnectionFailure(reason);
    } finally {
      pollInFlight.current = false;
      if (!quiet) setBusy(false);
    }
  }

  return (
    <View style={styles.module}>
      <View style={styles.heading}>
        <View>
          <Text style={styles.eyebrow}>ANEXO OPERACIONAL · MATERIALES</Text>
          <Text style={styles.title}>Despachos de materiales</Text>
          <Text style={styles.subtitle}>
            {activeCount} órdenes activas{lastSync ? ` · sincronizado ${lastSync}` : ''}
          </Text>
        </View>
        <Pressable onPress={() => void refresh(false)} style={styles.refreshButton}>
          <Text style={styles.refreshText}>Actualizar</Text>
        </Pressable>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={[styles.workspace, compact && styles.workspaceCompact]}>
        <View style={[styles.queue, compact && styles.queueCompact]}>
          <View style={styles.filters}>
            {(['activas', 'completadas', 'todas'] as QueueFilter[]).map((value) => (
              <Pressable
                key={value}
                onPress={() => setFilter(value)}
                style={[styles.filter, filter === value && styles.filterActive]}
              >
                <Text style={[styles.filterText, filter === value && styles.filterTextActive]}>
                  {value === 'activas' ? 'Activas' : value === 'completadas' ? 'Historial' : 'Todas'}
                </Text>
              </Pressable>
            ))}
          </View>
          <ScrollView contentContainerStyle={styles.queueList} nestedScrollEnabled style={styles.queueScroll}>
            {filtered.map((dispatch) => (
              <Pressable
                key={dispatch.id}
                onPress={() => setSelectedId(dispatch.id)}
                style={[styles.queueItem, selected?.id === dispatch.id && styles.queueItemSelected]}
              >
                <View style={styles.queueTop}>
                  <Text style={styles.queueCode}>{dispatch.codigo}</Text>
                  <StatusBadge state={dispatch.estado} />
                </View>
                <Text numberOfLines={1} style={styles.queueDestination}>{dispatch.destino.nombre}</Text>
                <Text style={styles.queueMeta}>
                  {dispatch.items.length} ítems · {formatDate(dispatch.created_at)}
                </Text>
              </Pressable>
            ))}
            {!filtered.length && !busy ? (
              <View style={styles.emptyQueue}>
                <Text style={styles.emptyText}>No hay órdenes en este filtro.</Text>
              </View>
            ) : null}
          </ScrollView>
        </View>

        <View style={styles.detail}>
          {selected ? (
            <>
              <View style={styles.detailHeader}>
                <View style={styles.detailTitleBlock}>
                  <View style={styles.detailTitleRow}>
                    <Text style={styles.detailCode}>{selected.codigo}</Text>
                    <StatusBadge state={selected.estado} />
                  </View>
                  <Text style={styles.detailDestination}>
                    {selected.destino.nombre} · CC {selected.destino.centro_costo}
                  </Text>
                  <Text style={styles.detailMeta}>
                    Creada {formatDateTime(selected.created_at)}
                    {selected.creado_por ? ` por ${selected.creado_por.nombre}` : ''}
                  </Text>
                </View>
              </View>

              {selected.observacion ? (
                <View style={styles.note}>
                  <Text style={styles.noteLabel}>OBSERVACIÓN</Text>
                  <Text style={styles.noteText}>{selected.observacion}</Text>
                </View>
              ) : null}

              <Text style={styles.sectionTitle}>Ítems y reservas FIFO</Text>
              <View style={styles.itemList}>
                {selected.items.map((item) => (
                  <View key={item.detalle_id} style={styles.itemCard}>
                    <View style={styles.itemHeader}>
                      <View style={styles.itemCopy}>
                        <Text style={styles.itemName}>{item.item.codigo} · {item.item.nombre}</Text>
                        <Text style={styles.itemAmounts}>
                          Solicitado {formatQuantity(item.cantidad_solicitada)} · Retirado {formatQuantity(item.cantidad_despachada)} · Pendiente {formatQuantity(item.cantidad_pendiente)} {item.unidad_medida}
                        </Text>
                      </View>
                      <Text style={styles.reservedAmount}>{formatQuantity(item.cantidad_reservada)} reservadas</Text>
                    </View>
                    <View style={styles.reservations}>
                      {item.sugerencias_fifo.map((suggestion, index) => (
                        <Pressable
                          disabled={!suggestion.camara || !suggestion.posicion}
                          key={`${suggestion.folio_id}-${index}`}
                          onPress={() => {
                            if (suggestion.camara && suggestion.posicion) {
                              onOpenPosition(suggestion.camara.id, suggestion.posicion.id);
                            }
                          }}
                          style={styles.reservation}
                        >
                          <View style={styles.fifoOrder}><Text style={styles.fifoOrderText}>{index + 1}</Text></View>
                          <View style={styles.reservationCopy}>
                            <Text style={styles.folioNumber}>{suggestion.numero_folio}</Text>
                            <Text style={styles.location}>
                              {suggestion.camara && suggestion.posicion
                                ? `${suggestion.camara.codigo} · ${suggestion.posicion.etiqueta}`
                                : 'Sin ubicación operable'}
                            </Text>
                          </View>
                          <Text style={styles.reservationAmount}>
                            {formatQuantity(suggestion.cantidad)} {item.unidad_medida}
                          </Text>
                        </Pressable>
                      ))}
                      {!item.sugerencias_fifo.length ? (
                        <Text style={styles.noReservations}>Sin reservas FIFO activas.</Text>
                      ) : null}
                    </View>
                  </View>
                ))}
              </View>

              <View style={styles.traceHeader}>
                <Text style={styles.sectionTitle}>Trazabilidad de retiros</Text>
                <Text style={styles.traceCount}>{withdrawals.length} registros</Text>
              </View>
              <View style={styles.traceList}>
                {withdrawals.map((withdrawal) => (
                  <View key={withdrawal.id} style={styles.traceRow}>
                    <View style={styles.traceMain}>
                      <Text style={styles.traceFolio}>{withdrawal.folio.numero_folio}</Text>
                      <Text style={styles.traceMeta}>
                        {withdrawal.item.codigo} · {withdrawal.camara?.codigo ?? 'Sin cámara'} · {withdrawal.posicion?.etiqueta ?? 'Sin posición'}
                      </Text>
                      <Text style={styles.traceMeta}>
                        {withdrawal.usuario?.nombre ?? 'Usuario no disponible'} · {withdrawal.dispositivo?.codigo ?? 'Sin dispositivo'} · {formatDateTime(withdrawal.retirado_at)}
                      </Text>
                    </View>
                    <View style={styles.traceResult}>
                      <Text style={styles.traceAmount}>−{formatQuantity(withdrawal.cantidad_retirada)} {withdrawal.unidad_medida}</Text>
                      <Text style={[styles.fifoResult, !withdrawal.siguio_fifo && styles.fifoException]}>
                        {withdrawal.siguio_fifo ? 'FIFO' : 'EXCEPCIÓN FIFO'}
                      </Text>
                    </View>
                  </View>
                ))}
                {!withdrawals.length ? (
                  <View style={styles.emptyTrace}>
                    <Text style={styles.emptyText}>La orden aún no registra retiros.</Text>
                  </View>
                ) : null}
              </View>

              {selected.cancelacion ? (
                <View style={styles.cancelation}>
                  <Text style={styles.cancelationTitle}>ORDEN CANCELADA</Text>
                  <Text style={styles.cancelationText}>{selected.cancelacion.motivo}</Text>
                </View>
              ) : null}
            </>
          ) : (
            <View style={styles.emptyDetail}>
              <Text style={styles.emptyTitle}>Sin despachos de materiales</Text>
              <Text style={styles.emptyText}>Las nuevas órdenes aparecerán automáticamente.</Text>
            </View>
          )}
        </View>
      </View>

      {busy ? (
        <View pointerEvents="none" style={styles.busy}>
          <ActivityIndicator color={colors.cyan} size="large" />
          <Text style={styles.busyText}>Sincronizando despachos…</Text>
        </View>
      ) : null}
    </View>
  );
}

function StatusBadge({ state }: { state: MaterialDispatch['estado'] }) {
  return (
    <View style={[styles.statusBadge, { borderColor: statusColor(state) }]}>
      <Text style={[styles.statusText, { color: statusColor(state) }]}>{statusLabel(state)}</Text>
    </View>
  );
}

function statusColor(state: MaterialDispatch['estado']) {
  return {
    pendiente: colors.amber,
    parcial: colors.blue,
    completado: colors.green,
    cancelado: colors.red,
  }[state];
}

function statusLabel(state: MaterialDispatch['estado']) {
  return {
    pendiente: 'PENDIENTE',
    parcial: 'PARCIAL',
    completado: 'COMPLETADO',
    cancelado: 'CANCELADO',
  }[state];
}

function formatQuantity(value: string) {
  return Number(value).toLocaleString('es-CL', { maximumFractionDigits: 3 });
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('es-CL');
}

function formatDateTime(value: string) {
  return new Date(value).toLocaleString('es-CL', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

const styles = StyleSheet.create({
  module: { minHeight: 620, borderRadius: 18, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel, overflow: 'hidden', position: 'relative' },
  heading: { padding: 18, borderBottomWidth: 1, borderBottomColor: colors.border, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 14 },
  eyebrow: { color: colors.cyan, fontSize: 8, fontWeight: '900', letterSpacing: 1.2 },
  title: { marginTop: 4, color: colors.text, fontSize: 22, fontWeight: '900' },
  subtitle: { marginTop: 4, color: colors.muted, fontSize: 9 },
  refreshButton: { paddingHorizontal: 14, paddingVertical: 10, borderRadius: 9, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.background },
  refreshText: { color: colors.text, fontSize: 8, fontWeight: '900' },
  error: { margin: 12, padding: 10, borderRadius: 8, borderWidth: 1, borderColor: colors.red, color: colors.red, fontSize: 9 },
  workspace: { minHeight: 530, flexDirection: 'row' },
  workspaceCompact: { flexDirection: 'column' },
  queue: { width: 310, borderRightWidth: 1, borderRightColor: colors.border, backgroundColor: colors.backgroundDeep },
  queueCompact: { width: '100%', maxHeight: 280, borderRightWidth: 0, borderBottomWidth: 1, borderBottomColor: colors.border },
  filters: { padding: 10, flexDirection: 'row', gap: 6 },
  filter: { flex: 1, paddingVertical: 8, borderRadius: 8, borderWidth: 1, borderColor: colors.border, alignItems: 'center' },
  filterActive: { borderColor: colors.cyan, backgroundColor: colors.selected },
  filterText: { color: colors.muted, fontSize: 7, fontWeight: '900' },
  filterTextActive: { color: colors.cyan },
  queueScroll: { maxHeight: 570 },
  queueList: { padding: 10, paddingTop: 0, gap: 7 },
  queueItem: { padding: 11, borderRadius: 10, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  queueItemSelected: { borderColor: colors.cyan, borderLeftWidth: 4, backgroundColor: colors.selected },
  queueTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 7 },
  queueCode: { color: colors.cyan, fontSize: 11, fontWeight: '900' },
  queueDestination: { marginTop: 6, color: colors.text, fontSize: 9, fontWeight: '800' },
  queueMeta: { marginTop: 3, color: colors.muted, fontSize: 7 },
  statusBadge: { paddingHorizontal: 7, paddingVertical: 4, borderRadius: 9, borderWidth: 1 },
  statusText: { fontSize: 6, fontWeight: '900' },
  emptyQueue: { minHeight: 140, alignItems: 'center', justifyContent: 'center' },
  detail: { flex: 1, minWidth: 0, padding: 18, backgroundColor: colors.panel },
  detailHeader: { flexDirection: 'row', justifyContent: 'space-between' },
  detailTitleBlock: { flex: 1 },
  detailTitleRow: { flexDirection: 'row', alignItems: 'center', gap: 9 },
  detailCode: { color: colors.text, fontSize: 24, fontWeight: '900' },
  detailDestination: { marginTop: 6, color: colors.cyan, fontSize: 11, fontWeight: '800' },
  detailMeta: { marginTop: 4, color: colors.muted, fontSize: 8 },
  note: { marginTop: 14, padding: 11, borderRadius: 9, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.background },
  noteLabel: { color: colors.cyan, fontSize: 7, fontWeight: '900', letterSpacing: 1 },
  noteText: { marginTop: 4, color: colors.text, fontSize: 9 },
  sectionTitle: { marginTop: 18, marginBottom: 9, color: colors.text, fontSize: 12, fontWeight: '900' },
  itemList: { gap: 9 },
  itemCard: { borderRadius: 11, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.background, overflow: 'hidden' },
  itemHeader: { padding: 11, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 },
  itemCopy: { flex: 1, minWidth: 0 },
  itemName: { color: colors.text, fontSize: 10, fontWeight: '900' },
  itemAmounts: { marginTop: 4, color: colors.muted, fontSize: 8, lineHeight: 12 },
  reservedAmount: { color: colors.amber, fontSize: 8, fontWeight: '900' },
  reservations: { padding: 9, paddingTop: 0, gap: 6 },
  reservation: { padding: 9, borderRadius: 8, borderWidth: 1, borderColor: colors.borderSoft, backgroundColor: colors.panel, flexDirection: 'row', alignItems: 'center', gap: 8 },
  fifoOrder: { width: 22, height: 22, borderRadius: 11, backgroundColor: colors.cyan, alignItems: 'center', justifyContent: 'center' },
  fifoOrderText: { color: colors.accentText, fontSize: 8, fontWeight: '900' },
  reservationCopy: { flex: 1, minWidth: 0 },
  folioNumber: { color: colors.text, fontSize: 9, fontWeight: '900' },
  location: { marginTop: 2, color: colors.muted, fontSize: 7 },
  reservationAmount: { color: colors.cyan, fontSize: 8, fontWeight: '900' },
  noReservations: { padding: 7, color: colors.muted, fontSize: 8 },
  traceHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  traceCount: { marginTop: 10, color: colors.muted, fontSize: 8 },
  traceList: { gap: 6 },
  traceRow: { padding: 10, borderRadius: 9, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.background, flexDirection: 'row', alignItems: 'center', gap: 10 },
  traceMain: { flex: 1, minWidth: 0 },
  traceFolio: { color: colors.text, fontSize: 9, fontWeight: '900' },
  traceMeta: { marginTop: 2, color: colors.muted, fontSize: 7 },
  traceResult: { alignItems: 'flex-end' },
  traceAmount: { color: colors.text, fontSize: 9, fontWeight: '900' },
  fifoResult: { marginTop: 3, color: colors.green, fontSize: 6, fontWeight: '900' },
  fifoException: { color: colors.amber },
  emptyTrace: { padding: 20, borderRadius: 9, borderWidth: 1, borderColor: colors.border, alignItems: 'center' },
  cancelation: { marginTop: 14, padding: 11, borderRadius: 9, borderWidth: 1, borderColor: colors.red, backgroundColor: colors.background },
  cancelationTitle: { color: colors.red, fontSize: 8, fontWeight: '900' },
  cancelationText: { marginTop: 4, color: colors.text, fontSize: 9 },
  emptyDetail: { minHeight: 420, alignItems: 'center', justifyContent: 'center' },
  emptyTitle: { color: colors.text, fontSize: 16, fontWeight: '900' },
  emptyText: { color: colors.muted, fontSize: 9 },
  busy: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(5,8,11,0.58)', alignItems: 'center', justifyContent: 'center', gap: 8 },
  busyText: { color: colors.text, fontSize: 9, fontWeight: '900' },
});
