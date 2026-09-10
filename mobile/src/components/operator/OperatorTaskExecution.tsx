import type { ReactNode } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View, useWindowDimensions } from 'react-native';

import {
  OperationalTask,
  operationalTaskLabel,
  operationalTaskPositionLabel,
  operationalTaskReason,
} from '../../domain/operationalTasks';
import {
  buildOperatorManeuverAction,
  buildOperatorManeuverSequence,
  type OperatorManeuverSequenceItem,
} from '../../domain/operatorManeuver';
import { operatorTheme as o } from '../../theme/operatorTheme';
import { OperatorEntityCode, OperatorPriorityBadge, OperatorStatusBadge } from './OperatorPrimitives';

type Props = {
  busy: boolean;
  deviceName: string;
  hasPhysicalDestination: boolean;
  leaseExpired: boolean;
  onBack: () => void;
  onComplete: () => void;
  onCompleteDirect: () => void;
  onCompleteTemporary: () => void;
  onImpossible: () => void;
  onMismatch: () => void;
  onRecalculate: () => void;
  onRelease: () => void;
  onStart: () => void;
  operatorName: string;
  secondsRemaining: number | null;
  task: OperationalTask;
};

export function OperatorTaskExecution({
  busy,
  deviceName,
  hasPhysicalDestination,
  leaseExpired,
  onBack,
  onComplete,
  onCompleteDirect,
  onCompleteTemporary,
  onImpossible,
  onMismatch,
  onRecalculate,
  onRelease,
  onStart,
  operatorName,
  secondsRemaining,
  task,
}: Props) {
  const { width } = useWindowDimensions();
  const compact = width < o.breakpoint.compact;
  const action = buildOperatorManeuverAction(task);
  const sequence = buildOperatorManeuverSequence(task);
  const moving = task.estado === 'en_proceso';
  const currentStep = task.secuencia_maniobra ?? task.maniobra?.secuencia_actual ?? 1;
  const totalSteps = task.maniobra?.pasos_totales ?? 1;
  const primaryDisabled = busy
    || leaseExpired
    || (!moving && task.tipo_movimiento !== 'retiro' && !hasPhysicalDestination);

  const runPrimary = () => {
    if (!moving) return onStart();
    if (task.tipo_movimiento !== 'retiro') return onComplete();
    if (task.tipo_paso_maniobra === 'extraccion_temporal') return onCompleteTemporary();
    return onCompleteDirect();
  };

  return (
    <ScrollView contentContainerStyle={styles.content} style={styles.screen}>
      <View style={[styles.header, compact && styles.headerCompact]}>
        <Pressable disabled={busy} onPress={onBack} style={styles.backButton}>
          <Text style={styles.backIcon}>‹</Text>
          <View>
            <Text style={styles.headerTitle}>Mi maniobra</Text>
            <Text style={styles.headerCopy}>Ejecución paso a paso</Text>
          </View>
        </Pressable>
        <View style={styles.headerStatus}>
          <OperatorStatusBadge
            label={moving ? 'EN EJECUCIÓN' : 'PREPARADA'}
            tone={moving ? 'success' : 'info'}
          />
          <Text style={styles.headerManeuver}>{task.maniobra?.titulo ?? operationalTaskLabel(task.plan.tipo)}</Text>
        </View>
      </View>

      <View style={[styles.summary, compact && styles.summaryCompact]}>
        <SummaryCell label="Folio / pallet"><OperatorEntityCode prominent value={task.folio.numero_folio} /></SummaryCell>
        <SummaryCell label="Labor" value={operationalTaskLabel(task.plan.tipo)} />
        <SummaryCell label="Ubicación actual" value={operationalTaskPositionLabel(task.origen)} />
        <SummaryCell label="Operador" value={task.responsable?.nombre ?? operatorName} />
        <SummaryCell label="Dispositivo" value={task.dispositivo?.nombre ?? deviceName} />
      </View>

      <View style={styles.progressPanel}>
        <Text style={styles.progressTitle}>PASO {currentStep} / {totalSteps}</Text>
        <View style={styles.progressRail}>
          {Array.from({ length: totalSteps }, (_, index) => {
            const number = index + 1;
            const state = number < currentStep ? 'complete' : number === currentStep ? 'current' : 'pending';
            return (
              <View key={number} style={styles.progressItem}>
                <View style={[
                  styles.progressDot,
                  state === 'complete' && styles.progressDotComplete,
                  state === 'current' && styles.progressDotCurrent,
                ]}>
                  <Text style={[
                    styles.progressDotText,
                    state !== 'pending' && styles.progressDotTextActive,
                  ]}>{state === 'complete' ? '✓' : number}</Text>
                </View>
                {number < totalSteps ? <View style={[
                  styles.progressLine,
                  number < currentStep && styles.progressLineComplete,
                ]} /> : null}
              </View>
            );
          })}
        </View>
        <Text style={styles.progressNext}>{action.instruction}</Text>
      </View>

      <View style={[styles.instructionGrid, compact && styles.instructionGridCompact]}>
        <View style={styles.actionColumn}>
          <View style={[
            styles.actionPanel,
            action.tone === 'critical' ? styles.actionPanelCritical : styles.actionPanelSuccess,
          ]}>
            <Text style={styles.actionKicker}>{moving ? 'ACCIÓN ACTUAL' : 'SIGUIENTE ACCIÓN'}</Text>
            <Text style={styles.actionVerb}>{action.verb}</Text>
            <Text style={styles.actionCopy}>{action.instruction}</Text>
            <OperatorEntityCode prominent value={task.folio.numero_folio} />
          </View>

          <View style={styles.locationCard}>
            <LocationLine label="Origen" value={operationalTaskPositionLabel(task.origen)} />
            <LocationLine label="Destino" value={action.destination} strong />
            <LocationLine label="Motivo" value={operationalTaskReason(task)} />
          </View>
        </View>

        <PhysicalPattern task={task} />
      </View>

      {!moving && task.tipo_movimiento !== 'retiro' && !hasPhysicalDestination ? (
        <View style={styles.destinationWarning}>
          <View style={styles.destinationWarningCopy}>
            <Text style={styles.destinationWarningTitle}>Destino físico pendiente</Text>
            <Text style={styles.destinationWarningText}>El servidor debe validar una posición antes de retirar el pallet.</Text>
          </View>
          <Pressable disabled={busy || leaseExpired} onPress={onRecalculate} style={styles.recalculateButton}>
            <Text style={styles.recalculateText}>Recalcular destino</Text>
          </Pressable>
        </View>
      ) : null}

      {leaseExpired ? (
        <View style={styles.leaseExpired}>
          <Text style={styles.leaseExpiredTitle}>La reserva venció antes de iniciar</Text>
          <Text style={styles.leaseExpiredCopy}>Vuelve a la bandeja y toma nuevamente la tarea.</Text>
        </View>
      ) : null}

      <Pressable
        disabled={primaryDisabled}
        onPress={runPrimary}
        style={[styles.primaryButton, primaryDisabled && styles.disabled]}
      >
        <Text style={styles.primaryButtonText}>{action.primaryLabel}</Text>
        <Text style={styles.primaryArrow}>›</Text>
      </Pressable>

      <View style={styles.secondaryActions}>
        {task.maniobra ? (
          <>
            <Pressable disabled={busy} onPress={onMismatch} style={styles.secondaryButton}>
              <Text style={styles.secondarySymbol}>×</Text>
              <Text style={styles.secondaryText}>NO COINCIDE</Text>
            </Pressable>
            <Pressable disabled={busy} onPress={onImpossible} style={styles.secondaryButton}>
              <Text style={styles.secondarySymbol}>!</Text>
              <Text style={styles.secondaryText}>NO ES POSIBLE</Text>
            </Pressable>
          </>
        ) : null}
        {!moving ? (
          <Pressable disabled={busy} onPress={onRelease} style={styles.releaseButton}>
            <Text style={styles.releaseText}>Liberar antes de iniciar</Text>
          </Pressable>
        ) : null}
      </View>

      <SequencePanel currentStep={currentStep} items={sequence} />
      <CustodyPanel task={task} />

      <View style={styles.footerActions}>
        <Pressable disabled={busy} onPress={onBack} style={styles.footerBack}>
          <Text style={styles.footerBackText}>‹ VOLVER A MANIOBRAS</Text>
        </Pressable>
        <CommitmentBadge seconds={secondsRemaining} task={task} />
      </View>
    </ScrollView>
  );
}

function SummaryCell({ children, label, value }: { children?: ReactNode; label: string; value?: string }) {
  return (
    <View style={styles.summaryCell}>
      <Text style={styles.summaryLabel}>{label}</Text>
      {children ?? <Text numberOfLines={2} style={styles.summaryValue}>{value}</Text>}
    </View>
  );
}

function LocationLine({ label, strong = false, value }: { label: string; strong?: boolean; value: string }) {
  return (
    <View style={styles.locationLine}>
      <Text style={styles.locationLabel}>{label}</Text>
      <Text style={[styles.locationValue, strong && styles.locationValueStrong]}>{value}</Text>
    </View>
  );
}

function PhysicalPattern({ task }: { task: OperationalTask }) {
  const endpoint = task.estado === 'en_proceso' && task.destino?.posicion ? task.destino : task.origen;
  const position = endpoint?.posicion;
  const camera = endpoint?.camara.nombre ?? 'Origen externo';
  return (
    <View style={styles.patternCard}>
      <View style={styles.patternHeader}>
        <Text style={styles.patternTitle}>Patrón físico</Text>
        <Text style={styles.patternCamera}>{camera}</Text>
      </View>
      {position ? (
        <View style={styles.patternBody}>
          <Text style={styles.patternDepth}>↑ FONDO</Text>
          <View style={styles.rack}>
            <View style={styles.rackRail} />
            <View style={styles.positionBox}>
              <Text style={styles.positionBand}>BANDA {String(position.banda).padStart(2, '0')}</Text>
              <Text style={styles.positionCode}>{position.etiqueta ?? `P${String(position.posicion).padStart(2, '0')} · N${position.nivel}`}</Text>
              <Text style={styles.positionStatus}>{task.estado === 'en_proceso' ? 'DESTINO' : 'ACTUAL'}</Text>
            </View>
            <View style={styles.rackRail} />
          </View>
          <Text style={styles.patternDepth}>ENTRADA ↓</Text>
        </View>
      ) : (
        <View style={styles.patternEmpty}>
          <Text style={styles.patternEmptyTitle}>Sin posición permanente</Text>
          <Text style={styles.patternEmptyCopy}>El WMS mantiene el pallet bajo control de la maniobra.</Text>
        </View>
      )}
    </View>
  );
}

function SequencePanel({ currentStep, items }: { currentStep: number; items: OperatorManeuverSequenceItem[] }) {
  return (
    <View style={styles.sequencePanel}>
      <View style={styles.panelHeader}>
        <Text style={styles.panelTitle}>Secuencia de la maniobra</Text>
        <Text style={styles.panelMeta}>{items.length} {items.length === 1 ? 'paso' : 'pasos'} en total</Text>
      </View>
      <ScrollView horizontal showsHorizontalScrollIndicator={false}>
        <View style={styles.sequenceRow}>
          {items.map((item) => (
            <View key={item.id} style={[
              styles.sequenceItem,
              item.state === 'current' && styles.sequenceItemCurrent,
              item.state === 'complete' && styles.sequenceItemComplete,
            ]}>
              <View style={styles.sequenceTopline}>
                <View style={[
                  styles.sequenceNumber,
                  item.state === 'current' && styles.sequenceNumberCurrent,
                  item.state === 'complete' && styles.sequenceNumberComplete,
                ]}>
                  <Text style={styles.sequenceNumberText}>{item.state === 'complete' ? '✓' : item.number}</Text>
                </View>
                <Text style={styles.sequenceState}>{item.state === 'complete' ? 'COMPLETADO' : item.number === currentStep ? 'EN CURSO' : 'PENDIENTE'}</Text>
              </View>
              <Text style={styles.sequenceFolio}>{item.folio}</Text>
              <Text numberOfLines={3} style={styles.sequenceRoute}>{item.route}</Text>
              <Text numberOfLines={2} style={styles.sequenceAction}>{item.action}</Text>
            </View>
          ))}
        </View>
      </ScrollView>
    </View>
  );
}

function CustodyPanel({ task }: { task: OperationalTask }) {
  const custodias = task.maniobra?.custodias_temporales ?? [];
  return (
    <View style={styles.custodyPanel}>
      <View style={styles.panelHeader}>
        <Text style={styles.panelTitle}>Bajo maniobra</Text>
        <Text style={styles.panelMeta}>{custodias.length} {custodias.length === 1 ? 'pallet' : 'pallets'}</Text>
      </View>
      {custodias.length ? custodias.map((custodia) => (
        <View key={custodia.id} style={styles.custodyRow}>
          <View style={styles.custodyState}><Text style={styles.custodyStateText}>EXTRAÍDO TEMPORALMENTE</Text></View>
          <OperatorEntityCode value={custodia.folio?.numero_folio ?? 'Folio bajo custodia'} />
          <Text style={styles.custodyOrigin}>Origen: {operationalTaskPositionLabel(custodia.origen)}</Text>
        </View>
      )) : (
        <View style={styles.custodyEmpty}>
          <Text style={styles.custodyEmptyText}>No hay pallets extraídos temporalmente en este momento.</Text>
        </View>
      )}
    </View>
  );
}

function CommitmentBadge({ task, seconds }: { task: OperationalTask; seconds: number | null }) {
  if (task.estado === 'en_proceso') {
    return <OperatorStatusBadge label="DESTINO FIJO · PUNTO DE NO RETORNO" tone="critical" />;
  }
  const expired = seconds !== null && seconds <= 0;
  const warning = seconds !== null && seconds > 0 && seconds < 180;
  const prefix = task.reserva?.tipo_compromiso === 'fisica' ? 'RESERVA FÍSICA' : 'TAREA TOMADA';
  return (
    <OperatorStatusBadge
      label={expired ? `${prefix} VENCIDA` : `${prefix} · ${seconds === null ? 'ACTIVA' : formatDuration(seconds)}`}
      tone={expired ? 'critical' : warning ? 'warning' : 'success'}
    />
  );
}

function formatDuration(seconds: number) {
  const minutes = Math.floor(seconds / 60);
  const remainder = seconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: o.color.canvas },
  content: { gap: o.space[3], paddingBottom: o.space[8] },
  header: { minHeight: 64, paddingHorizontal: o.space[3], flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: o.space[4], borderWidth: 1, borderColor: o.color.border, borderRadius: o.radius.panel, backgroundColor: o.color.surface },
  headerCompact: { alignItems: 'stretch', flexDirection: 'column', paddingVertical: o.space[3] },
  backButton: { minHeight: o.touch.minimum, flexDirection: 'row', alignItems: 'center', gap: o.space[3] },
  backIcon: { width: 42, color: o.color.onNavy, backgroundColor: o.color.navy, borderRadius: o.radius.control, fontSize: 40, lineHeight: 48, fontWeight: '500', textAlign: 'center' },
  headerTitle: { color: o.color.text, fontSize: o.type.heading, fontWeight: '900' },
  headerCopy: { color: o.color.muted, fontSize: o.type.small },
  headerStatus: { flexShrink: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: o.space[3] },
  headerManeuver: { maxWidth: 300, color: o.color.text, fontSize: o.type.body, fontWeight: '900', textAlign: 'right' },
  summary: { flexDirection: 'row', borderWidth: 1, borderColor: o.color.border, borderRadius: o.radius.panel, backgroundColor: o.color.surface },
  summaryCompact: { flexWrap: 'wrap' },
  summaryCell: { flex: 1, minWidth: 150, minHeight: 86, padding: o.space[3], justifyContent: 'center', borderRightWidth: 1, borderRightColor: o.color.border },
  summaryLabel: { color: o.color.muted, fontSize: o.type.caption, fontWeight: '800', marginBottom: o.space[1] },
  summaryValue: { color: o.color.text, fontSize: o.type.body, lineHeight: 21, fontWeight: '900' },
  progressPanel: { minHeight: 78, padding: o.space[3], flexDirection: 'row', alignItems: 'center', gap: o.space[4], borderWidth: 1, borderColor: o.color.border, borderRadius: o.radius.panel, backgroundColor: o.color.surface },
  progressTitle: { color: o.color.text, fontSize: o.type.body, fontWeight: '900' },
  progressRail: { flexDirection: 'row', alignItems: 'center' },
  progressItem: { flexDirection: 'row', alignItems: 'center' },
  progressDot: { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center', backgroundColor: o.color.disabled },
  progressDotCurrent: { backgroundColor: o.color.primary },
  progressDotComplete: { backgroundColor: o.color.success },
  progressDotText: { color: o.color.disabledText, fontSize: o.type.small, fontWeight: '900' },
  progressDotTextActive: { color: o.color.onPrimary },
  progressLine: { width: 36, height: 3, backgroundColor: o.color.disabled },
  progressLineComplete: { backgroundColor: o.color.success },
  progressNext: { flex: 1, color: o.color.muted, fontSize: o.type.small, lineHeight: 20, textAlign: 'right' },
  instructionGrid: { flexDirection: 'row', gap: o.space[3] },
  instructionGridCompact: { flexDirection: 'column' },
  actionColumn: { flex: 1.75, minWidth: 0, gap: o.space[3] },
  actionPanel: { minHeight: 250, padding: o.space[6], alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderRadius: o.radius.panel },
  actionPanelCritical: { borderColor: o.color.critical, backgroundColor: o.color.criticalSurface },
  actionPanelSuccess: { borderColor: o.color.success, backgroundColor: o.color.successSurface },
  actionKicker: { color: o.color.muted, fontSize: o.type.caption, fontWeight: '900', letterSpacing: 1.2 },
  actionVerb: { color: o.color.text, fontSize: 54, lineHeight: 62, fontWeight: '900', marginTop: o.space[2] },
  actionCopy: { maxWidth: 620, color: o.color.text, fontSize: o.type.body, lineHeight: 23, fontWeight: '700', textAlign: 'center', marginBottom: o.space[3] },
  locationCard: { padding: o.space[4], gap: o.space[2], borderWidth: 1, borderColor: o.color.border, borderRadius: o.radius.panel, backgroundColor: o.color.surface },
  locationLine: { minHeight: 38, flexDirection: 'row', gap: o.space[3] },
  locationLabel: { width: 76, color: o.color.muted, fontSize: o.type.small, fontWeight: '800' },
  locationValue: { flex: 1, color: o.color.text, fontSize: o.type.small, lineHeight: 20 },
  locationValueStrong: { color: o.color.primaryPressed, fontWeight: '900' },
  patternCard: { flex: 1, minWidth: 250, overflow: 'hidden', borderWidth: 1, borderColor: o.color.borderStrong, borderRadius: o.radius.panel, backgroundColor: o.color.surface },
  patternHeader: { minHeight: 52, paddingHorizontal: o.space[3], flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: o.space[2], backgroundColor: o.color.navy },
  patternTitle: { color: o.color.onNavy, fontSize: o.type.body, fontWeight: '900' },
  patternCamera: { flex: 1, color: '#D9E5EA', fontSize: o.type.small, textAlign: 'right' },
  patternBody: { flex: 1, minHeight: 300, padding: o.space[4], alignItems: 'center', justifyContent: 'space-between' },
  patternDepth: { color: o.color.muted, fontSize: o.type.caption, fontWeight: '900', letterSpacing: 0.8 },
  rack: { width: '84%', flexDirection: 'row', alignItems: 'stretch', justifyContent: 'center' },
  rackRail: { width: 14, minHeight: 160, borderWidth: 2, borderColor: o.color.navy, backgroundColor: o.color.border },
  positionBox: { flex: 1, minHeight: 160, padding: o.space[3], alignItems: 'center', justifyContent: 'center', borderTopWidth: 8, borderBottomWidth: 8, borderColor: o.color.warning, backgroundColor: o.color.selected },
  positionBand: { color: o.color.muted, fontSize: o.type.caption, fontWeight: '900' },
  positionCode: { color: o.color.text, fontSize: o.type.heading, fontWeight: '900', textAlign: 'center', marginTop: o.space[2] },
  positionStatus: { color: o.color.primaryPressed, fontSize: o.type.caption, fontWeight: '900', marginTop: o.space[2] },
  patternEmpty: { flex: 1, minHeight: 260, padding: o.space[6], alignItems: 'center', justifyContent: 'center' },
  patternEmptyTitle: { color: o.color.text, fontSize: o.type.body, fontWeight: '900', textAlign: 'center' },
  patternEmptyCopy: { color: o.color.muted, fontSize: o.type.small, lineHeight: 20, textAlign: 'center', marginTop: o.space[2] },
  destinationWarning: { padding: o.space[3], flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: o.space[3], borderWidth: 1, borderColor: o.color.warning, borderRadius: o.radius.panel, backgroundColor: o.color.warningSurface },
  destinationWarningCopy: { flex: 1 },
  destinationWarningTitle: { color: o.color.warning, fontSize: o.type.body, fontWeight: '900' },
  destinationWarningText: { color: o.color.text, fontSize: o.type.small, marginTop: o.space[1] },
  recalculateButton: { minHeight: o.touch.minimum, paddingHorizontal: o.space[4], alignItems: 'center', justifyContent: 'center', borderRadius: o.radius.control, backgroundColor: o.color.warning },
  recalculateText: { color: o.color.onPrimary, fontSize: o.type.small, fontWeight: '900' },
  leaseExpired: { padding: o.space[3], borderWidth: 1, borderColor: o.color.critical, borderRadius: o.radius.panel, backgroundColor: o.color.criticalSurface },
  leaseExpiredTitle: { color: o.color.critical, fontSize: o.type.body, fontWeight: '900' },
  leaseExpiredCopy: { color: o.color.text, fontSize: o.type.small, marginTop: o.space[1] },
  primaryButton: { minHeight: 72, paddingHorizontal: o.space[6], flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: o.space[3], borderRadius: o.radius.control, backgroundColor: o.color.success },
  primaryButtonText: { color: o.color.onPrimary, fontSize: o.type.heading, fontWeight: '900' },
  primaryArrow: { color: o.color.onPrimary, fontSize: 38, lineHeight: 40 },
  disabled: { opacity: 0.42 },
  secondaryActions: { flexDirection: 'row', flexWrap: 'wrap', gap: o.space[3] },
  secondaryButton: { minHeight: o.touch.prominent, flex: 1, minWidth: 220, paddingHorizontal: o.space[4], flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: o.space[3], borderWidth: 1, borderColor: o.color.borderStrong, borderRadius: o.radius.control, backgroundColor: o.color.surface },
  secondarySymbol: { width: 30, height: 30, borderWidth: 2, borderColor: o.color.text, borderRadius: 15, color: o.color.text, fontSize: 20, lineHeight: 26, fontWeight: '900', textAlign: 'center' },
  secondaryText: { color: o.color.text, fontSize: o.type.body, fontWeight: '900' },
  releaseButton: { minHeight: o.touch.prominent, paddingHorizontal: o.space[4], alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: o.color.critical, borderRadius: o.radius.control, backgroundColor: o.color.surface },
  releaseText: { color: o.color.critical, fontSize: o.type.small, fontWeight: '900' },
  sequencePanel: { overflow: 'hidden', borderWidth: 1, borderColor: o.color.border, borderRadius: o.radius.panel, backgroundColor: o.color.surface },
  panelHeader: { minHeight: 52, paddingHorizontal: o.space[3], flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: o.space[3], backgroundColor: o.color.navy },
  panelTitle: { color: o.color.onNavy, fontSize: o.type.body, fontWeight: '900' },
  panelMeta: { color: '#D9E5EA', fontSize: o.type.small },
  sequenceRow: { flexDirection: 'row' },
  sequenceItem: { width: 260, minHeight: 150, padding: o.space[3], gap: o.space[2], borderRightWidth: 1, borderRightColor: o.color.border, backgroundColor: o.color.surfaceMuted },
  sequenceItemCurrent: { borderTopWidth: 5, borderTopColor: o.color.primary, backgroundColor: o.color.selected },
  sequenceItemComplete: { borderTopWidth: 5, borderTopColor: o.color.success, backgroundColor: o.color.successSurface },
  sequenceTopline: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: o.space[2] },
  sequenceNumber: { width: 30, height: 30, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: o.color.disabled },
  sequenceNumberCurrent: { backgroundColor: o.color.primary },
  sequenceNumberComplete: { backgroundColor: o.color.success },
  sequenceNumberText: { color: o.color.onPrimary, fontSize: o.type.small, fontWeight: '900' },
  sequenceState: { color: o.color.muted, fontSize: o.type.caption, fontWeight: '900' },
  sequenceFolio: { color: o.color.text, fontSize: o.type.body, fontWeight: '900' },
  sequenceRoute: { color: o.color.muted, fontSize: o.type.small, lineHeight: 19 },
  sequenceAction: { color: o.color.primaryPressed, fontSize: o.type.small, fontWeight: '900' },
  custodyPanel: { overflow: 'hidden', borderWidth: 1, borderColor: o.color.border, borderRadius: o.radius.panel, backgroundColor: o.color.surface },
  custodyRow: { minHeight: 66, padding: o.space[3], flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: o.space[3], borderBottomWidth: 1, borderBottomColor: o.color.border },
  custodyState: { paddingHorizontal: o.space[2], paddingVertical: o.space[1], borderWidth: 1, borderColor: o.color.warning, borderRadius: o.radius.control, backgroundColor: o.color.warningSurface },
  custodyStateText: { color: o.color.warning, fontSize: o.type.caption, fontWeight: '900' },
  custodyOrigin: { flex: 1, minWidth: 220, color: o.color.muted, fontSize: o.type.small },
  custodyEmpty: { minHeight: 92, padding: o.space[4], alignItems: 'center', justifyContent: 'center' },
  custodyEmptyText: { color: o.color.muted, fontSize: o.type.small, textAlign: 'center' },
  footerActions: { minHeight: 70, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: o.space[3] },
  footerBack: { minHeight: o.touch.minimum, paddingHorizontal: o.space[4], alignItems: 'center', justifyContent: 'center', borderRadius: o.radius.control, backgroundColor: o.color.navyRaised },
  footerBackText: { color: o.color.onNavy, fontSize: o.type.small, fontWeight: '900' },
});
