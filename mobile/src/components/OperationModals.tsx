import { useEffect, useState } from 'react';
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

import { CameraPlan, CameraSummary, Position, SagCondition } from '../domain/estiba';
import { colors } from '../theme/colors';

export type LocateFormValue = {
  numero_folio: string;
  tipo_bulto: 'pallet' | 'saldo';
  condicion_sag_id?: string;
  variedad?: string;
  calibre?: string;
  marca?: string;
  exportadora?: string;
};

type LocateModalProps = {
  busy: boolean;
  conditions: SagCondition[];
  onCancel: () => void;
  onConfirm: (value: LocateFormValue) => Promise<void>;
  plan: CameraPlan | null;
  position: Position | null;
  visible: boolean;
};

export function LocateModal({
  busy,
  conditions,
  onCancel,
  onConfirm,
  plan,
  position,
  visible,
}: LocateModalProps) {
  const [folio, setFolio] = useState('');
  const [type, setType] = useState<'pallet' | 'saldo'>('pallet');
  const [conditionId, setConditionId] = useState<string>();
  const [variety, setVariety] = useState('');
  const [caliber, setCaliber] = useState('');
  const [brand, setBrand] = useState('');
  const [exporter, setExporter] = useState('');

  useEffect(() => {
    if (!visible) return;
    setFolio('');
    setType('pallet');
    setConditionId(undefined);
    setVariety('');
    setCaliber('');
    setBrand('');
    setExporter('');
  }, [visible]);

  async function submit() {
    if (!folio.trim()) return;
    await onConfirm({
      numero_folio: folio.trim().toUpperCase(),
      tipo_bulto: type,
      condicion_sag_id: conditionId,
      variedad: variety.trim() || undefined,
      calibre: caliber.trim() || undefined,
      marca: brand.trim() || undefined,
      exportadora: exporter.trim() || undefined,
    });
  }

  return (
    <Modal animationType="fade" onRequestClose={onCancel} transparent visible={visible}>
      <View style={styles.backdrop}>
        <View style={styles.dialog}>
          <DialogHeading
            eyebrow="UBICACIÓN INICIAL"
            onClose={onCancel}
            subtitle={'Destino: ' + (plan?.codigo ?? '') + ' · ' + (position?.etiqueta ?? '')}
            title="Registrar folio"
          />
          <ScrollView contentContainerStyle={styles.form} keyboardShouldPersistTaps="handled">
            <FormField
              autoCapitalize="characters"
              label="Número de folio *"
              onChangeText={setFolio}
              placeholder="Ej. 00498127"
              value={folio}
              wide
            />

            <View style={styles.field}>
              <Text style={styles.label}>Tipo de bulto *</Text>
              <View style={styles.choiceRow}>
                <Choice active={type === 'pallet'} label="Pallet completo" onPress={() => setType('pallet')} />
                <Choice active={type === 'saldo'} label="Saldo incompleto" onPress={() => setType('saldo')} />
              </View>
            </View>

            <View style={[styles.field, styles.wide]}>
              <Text style={styles.label}>Condición SAG</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                <View style={styles.choiceRow}>
                  <Choice active={!conditionId} label="Sin especificar" onPress={() => setConditionId(undefined)} />
                  {conditions.map((condition) => (
                    <Choice
                      active={conditionId === condition.id}
                      key={condition.id}
                      label={condition.codigo}
                      onPress={() => setConditionId(condition.id)}
                    />
                  ))}
                </View>
              </ScrollView>
            </View>

            <FormField label="Variedad" onChangeText={setVariety} placeholder="Ej. Santina" value={variety} />
            <FormField label="Calibre" onChangeText={setCaliber} placeholder="Ej. 2J" value={caliber} />
            <FormField label="Marca" onChangeText={setBrand} placeholder="Opcional" value={brand} />
            <FormField label="Exportadora" onChangeText={setExporter} placeholder="Opcional" value={exporter} />
          </ScrollView>

          <DialogActions
            busy={busy}
            confirmDisabled={!folio.trim()}
            confirmLabel="Confirmar ubicación"
            onCancel={onCancel}
            onConfirm={submit}
          />
        </View>
      </View>
    </Modal>
  );
}

type MoveModalProps = {
  busy: boolean;
  cameras: CameraSummary[];
  destinationPlan: CameraPlan | null;
  onCancel: () => void;
  onChooseCamera: (cameraId: string) => void;
  onConfirm: () => Promise<void>;
  onSelectPosition: (position: Position) => void;
  originPlan: CameraPlan | null;
  originPosition: Position | null;
  selectedDestination: Position | null;
  visible: boolean;
};

export function MoveModal({
  busy,
  cameras,
  destinationPlan,
  onCancel,
  onChooseCamera,
  onConfirm,
  onSelectPosition,
  originPlan,
  originPosition,
  selectedDestination,
  visible,
}: MoveModalProps) {
  const freePositions = destinationPlan?.posiciones.filter((position) => (
    position.estado === 'activa'
    && !position.ocupada
    && position.id !== originPosition?.id
  )) ?? [];

  return (
    <Modal animationType="fade" onRequestClose={onCancel} transparent visible={visible}>
      <View style={styles.backdrop}>
        <View style={[styles.dialog, styles.moveDialog]}>
          <DialogHeading
            eyebrow="MOVIMIENTO DE FOLIO"
            onClose={onCancel}
            subtitle={'Origen: ' + (originPlan?.codigo ?? '') + ' · ' + (originPosition?.etiqueta ?? '')}
            title={'Mover ' + (originPosition?.folio?.numero_folio ?? 'folio')}
          />

          <Text style={styles.label}>Cámara de destino</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false}>
            <View style={styles.choiceRow}>
              {cameras.filter((camera) => camera.estado === 'activa').map((camera) => (
                <Choice
                  active={destinationPlan?.id === camera.id}
                  key={camera.id}
                  label={camera.codigo}
                  onPress={() => onChooseCamera(camera.id)}
                />
              ))}
            </View>
          </ScrollView>

          <View style={styles.destinationHeading}>
            <Text style={styles.label}>POSICIONES LIBRES</Text>
            <Text style={styles.destinationCount}>
              {destinationPlan ? freePositions.length + ' disponibles' : 'Cargando plano…'}
            </Text>
          </View>

          {!destinationPlan ? (
            <ActivityIndicator color={colors.cyan} style={styles.loader} />
          ) : destinationPlan.acceso.modo === 'solo_lectura' ? (
            <Text style={styles.empty}>La cámara de destino está siendo modificada por otro operador.</Text>
          ) : (
            <ScrollView
              contentContainerStyle={styles.destinationGrid}
              style={styles.destinationScroll}
            >
              {freePositions.map((position) => (
                <Pressable
                  key={position.id}
                  onPress={() => onSelectPosition(position)}
                  style={({ pressed }) => [
                    styles.destination,
                    selectedDestination?.id === position.id && styles.destinationSelected,
                    pressed && styles.pressed,
                  ]}
                >
                  <Text style={styles.destinationLabel}>{position.etiqueta}</Text>
                  <Text style={styles.destinationMeta}>
                    Fila {position.fila} · Prof. {position.profundidad} · Nivel {position.nivel}
                  </Text>
                </Pressable>
              ))}
              {freePositions.length === 0 && <Text style={styles.empty}>No hay posiciones libres.</Text>}
            </ScrollView>
          )}

          <DialogActions
            busy={busy}
            confirmDisabled={!selectedDestination}
            confirmLabel="Confirmar movimiento"
            onCancel={onCancel}
            onConfirm={onConfirm}
          />
        </View>
      </View>
    </Modal>
  );
}

function DialogHeading({
  eyebrow,
  onClose,
  subtitle,
  title,
}: {
  eyebrow: string;
  onClose: () => void;
  subtitle: string;
  title: string;
}) {
  return (
    <View style={styles.heading}>
      <View style={styles.headingCopy}>
        <Text style={styles.eyebrow}>{eyebrow}</Text>
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.subtitle}>{subtitle}</Text>
      </View>
      <Pressable accessibilityLabel="Cerrar" onPress={onClose} style={styles.close}>
        <Text style={styles.closeText}>×</Text>
      </Pressable>
    </View>
  );
}

function FormField({
  label,
  wide,
  ...props
}: {
  autoCapitalize?: 'characters';
  label: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  value: string;
  wide?: boolean;
}) {
  return (
    <View style={[styles.field, wide && styles.wide]}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        {...props}
        placeholderTextColor="#627C88"
        selectionColor={colors.cyan}
        style={styles.input}
      />
    </View>
  );
}

function Choice({ active, label, onPress }: { active: boolean; label: string; onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.choice, active && styles.choiceActive, pressed && styles.pressed]}
    >
      <Text style={[styles.choiceText, active && styles.choiceTextActive]}>{label}</Text>
    </Pressable>
  );
}

function DialogActions({
  busy,
  confirmDisabled,
  confirmLabel,
  onCancel,
  onConfirm,
}: {
  busy: boolean;
  confirmDisabled: boolean;
  confirmLabel: string;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  return (
    <View style={styles.actions}>
      <Pressable disabled={busy} onPress={onCancel} style={styles.cancel}>
        <Text style={styles.cancelText}>Cancelar</Text>
      </Pressable>
      <Pressable
        disabled={busy || confirmDisabled}
        onPress={onConfirm}
        style={[styles.confirm, (busy || confirmDisabled) && styles.disabled]}
      >
        {busy ? <ActivityIndicator color="#032022" /> : <Text style={styles.confirmText}>{confirmLabel}</Text>}
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    padding: 24,
    backgroundColor: 'rgba(0,0,0,0.72)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  dialog: {
    width: '92%',
    maxWidth: 820,
    maxHeight: '94%',
    padding: 20,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
  },
  moveDialog: { maxWidth: 900 },
  heading: { marginBottom: 16, flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  headingCopy: { flex: 1 },
  eyebrow: { color: colors.cyan, fontSize: 9, fontWeight: '900', letterSpacing: 1.2 },
  title: { marginTop: 4, color: colors.text, fontSize: 23, fontWeight: '900' },
  subtitle: { marginTop: 4, color: colors.muted, fontSize: 10 },
  close: {
    width: 36,
    height: 36,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  closeText: { color: colors.text, fontSize: 24, lineHeight: 25 },
  form: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  field: { width: '48%', gap: 6 },
  wide: { width: '100%' },
  label: { color: colors.text, fontSize: 9, fontWeight: '800' },
  input: {
    height: 44,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
    color: colors.text,
    fontSize: 12,
  },
  choiceRow: { flexDirection: 'row', gap: 8 },
  choice: {
    minHeight: 38,
    paddingHorizontal: 12,
    borderRadius: 9,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
    alignItems: 'center',
    justifyContent: 'center',
  },
  choiceActive: { borderColor: colors.cyan, backgroundColor: colors.selected },
  choiceText: { color: colors.muted, fontSize: 9, fontWeight: '800' },
  choiceTextActive: { color: colors.cyan },
  destinationHeading: {
    marginTop: 16,
    marginBottom: 8,
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  destinationCount: { color: colors.muted, fontSize: 8 },
  destinationScroll: { maxHeight: 260 },
  destinationGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  destination: {
    width: '31.8%',
    padding: 11,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
  },
  destinationSelected: { borderColor: colors.cyan, backgroundColor: colors.selected },
  destinationLabel: { color: colors.text, fontSize: 11, fontWeight: '900' },
  destinationMeta: { marginTop: 4, color: colors.muted, fontSize: 7 },
  loader: { height: 150 },
  empty: { paddingVertical: 30, color: colors.muted, fontSize: 10, textAlign: 'center' },
  actions: { marginTop: 18, flexDirection: 'row', justifyContent: 'flex-end', gap: 10 },
  cancel: {
    minWidth: 110,
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cancelText: { color: colors.text, fontSize: 10, fontWeight: '900' },
  confirm: {
    minWidth: 165,
    height: 44,
    paddingHorizontal: 14,
    borderRadius: 10,
    backgroundColor: colors.cyan,
    alignItems: 'center',
    justifyContent: 'center',
  },
  confirmText: { color: '#032022', fontSize: 10, fontWeight: '900' },
  disabled: { opacity: 0.4 },
  pressed: { opacity: 0.72 },
});
