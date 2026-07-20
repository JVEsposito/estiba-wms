import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  useWindowDimensions,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import {
  CameraPlan,
  CameraSummary,
  MaterialDestination,
  MaterialDispatch,
  MaterialItem,
  Position,
  SagCondition,
} from '../domain/estiba';
import { colors } from '../theme/colors';

export type LocateFormValue = {
  numero_folio: string;
  tipo_bulto: 'pallet' | 'saldo' | 'material';
  condicion_sag_id?: string;
  variedad?: string;
  calibre?: string;
  marca?: string;
  exportadora?: string;
  item_material_id?: string;
  cantidad?: number;
  lote?: string;
  proveedor?: string;
  observacion_material?: string;
};

type LocateModalProps = {
  busy: boolean;
  conditions: SagCondition[];
  materialItems: MaterialItem[];
  error: string;
  onCancel: () => void;
  onConfirm: (value: LocateFormValue) => Promise<void>;
  plan: CameraPlan | null;
  position: Position | null;
  visible: boolean;
};

export function LocateModal({
  busy,
  conditions,
  materialItems,
  error,
  onCancel,
  onConfirm,
  plan,
  position,
  visible,
}: LocateModalProps) {
  const { height, width } = useWindowDimensions();
  const compact = height < 700 || width < 1000;
  const isMaterial = plan?.contenido === 'materiales';
  const [folio, setFolio] = useState('');
  const [type, setType] = useState<'pallet' | 'saldo' | 'material'>('pallet');
  const [conditionId, setConditionId] = useState<string>();
  const [variety, setVariety] = useState('');
  const [caliber, setCaliber] = useState('');
  const [brand, setBrand] = useState('');
  const [exporter, setExporter] = useState('');
  const [materialSearch, setMaterialSearch] = useState('');
  const [materialClientId, setMaterialClientId] = useState<string>();
  const [materialItemId, setMaterialItemId] = useState<string>();
  const [materialQuantity, setMaterialQuantity] = useState('');
  const [materialLot, setMaterialLot] = useState('');
  const [materialSupplier, setMaterialSupplier] = useState('');
  const [materialObservation, setMaterialObservation] = useState('');
  const materialClients = [...new Map(materialItems.map((item) => [item.cliente.id, item.cliente])).values()];
  const materialSeason = materialItems[0]?.cliente.temporada;
  const filteredMaterialItems = materialItems.filter((item) => (
    item.cliente.id === materialClientId
    && `${item.cliente.codigo} ${item.cliente.nombre} ${item.codigo} ${item.nombre} ${item.categoria ?? ''}`
      .toLowerCase()
      .includes(materialSearch.trim().toLowerCase())
  ));

  useEffect(() => {
    if (!visible) return;
    setFolio('');
    setType(isMaterial ? 'material' : 'pallet');
    setConditionId(undefined);
    setVariety('');
    setCaliber('');
    setBrand('');
    setExporter('');
    setMaterialSearch('');
    setMaterialClientId(undefined);
    setMaterialItemId(undefined);
    setMaterialQuantity('');
    setMaterialLot('');
    setMaterialSupplier('');
    setMaterialObservation('');
  }, [visible, isMaterial]);

  async function submit() {
    if (!folio.trim() || (isMaterial && (!materialItemId || Number(materialQuantity) <= 0))) return;
    await onConfirm({
      numero_folio: folio.trim().toUpperCase(),
      tipo_bulto: type,
      condicion_sag_id: conditionId,
      variedad: variety.trim() || undefined,
      calibre: caliber.trim() || undefined,
      marca: brand.trim() || undefined,
      exportadora: exporter.trim() || undefined,
      item_material_id: materialItemId,
      cantidad: isMaterial ? Number(materialQuantity) : undefined,
      lote: materialLot.trim() || undefined,
      proveedor: materialSupplier.trim() || undefined,
      observacion_material: materialObservation.trim() || undefined,
    });
  }

  return (
    <Modal animationType="fade" onRequestClose={onCancel} transparent visible={visible}>
      <SafeAreaView edges={['top', 'right', 'bottom', 'left']} style={styles.modalSafeArea}>
        <View style={[styles.backdrop, compact && styles.backdropCompact]}>
        <View style={[styles.dialog, compact && styles.dialogCompact]}>
          <DialogHeading
            compact={compact}
            eyebrow="UBICACIÓN INICIAL"
            onClose={onCancel}
            subtitle={'Destino: ' + (plan?.codigo ?? '') + ' · ' + (position?.etiqueta ?? '')}
            title="Registrar folio"
          />
          <ScrollView
            contentContainerStyle={styles.form}
            keyboardShouldPersistTaps="handled"
            nestedScrollEnabled
            style={styles.formScroll}
          >
            <FormField
              autoCapitalize="characters"
              label="Número de folio *"
              onChangeText={setFolio}
              placeholder="Ej. 00498127"
              value={folio}
              wide
            />

            {isMaterial ? (
              <>
                <View style={[styles.field, styles.wide]}>
                  <Text style={styles.label}>Temporada activa</Text>
                  <Text style={styles.emptyInline}>{materialSeason ? `${materialSeason.codigo} · ${materialSeason.nombre}` : 'No existe una temporada activa de materiales.'}</Text>
                </View>
                <View style={[styles.field, styles.wide]}>
                  <Text style={styles.label}>Cliente *</Text>
                  <ScrollView horizontal showsHorizontalScrollIndicator>
                    <View style={styles.choiceRow}>
                      {materialClients.map((client) => (
                        <Choice
                          active={materialClientId === client.id}
                          key={client.id}
                          label={`${client.codigo} · ${client.nombre}`}
                          onPress={() => {
                            setMaterialClientId(client.id);
                            setMaterialItemId(undefined);
                          }}
                        />
                      ))}
                    </View>
                  </ScrollView>
                </View>
                <FormField label="Buscar ítem *" onChangeText={setMaterialSearch} placeholder="Código o descripción" value={materialSearch} wide />
                <View style={[styles.field, styles.wide]}>
                  <Text style={styles.label}>Ítem contenido *</Text>
                  <ScrollView horizontal showsHorizontalScrollIndicator>
                    <View style={styles.choiceRow}>
                      {filteredMaterialItems.map((item) => (
                        <Choice active={materialItemId === item.id} key={item.id} label={`${item.codigo} · ${item.nombre}`} onPress={() => setMaterialItemId(item.id)} />
                      ))}
                    </View>
                  </ScrollView>
                  {filteredMaterialItems.length === 0 && <Text style={styles.emptyInline}>{materialClientId ? 'No hay ítems coincidentes. Solicita su creación en la oficina.' : 'Selecciona primero un cliente.'}</Text>}
                </View>
                <FormField label={`Cantidad inicial *${materialItems.find((item) => item.id === materialItemId)?.unidad_medida ? ` · ${materialItems.find((item) => item.id === materialItemId)?.unidad_medida}` : ''}`} onChangeText={setMaterialQuantity} placeholder="Ej. 450" value={materialQuantity} />
                <FormField label="Lote" onChangeText={setMaterialLot} placeholder="Opcional" value={materialLot} />
                <FormField label="Proveedor" onChangeText={setMaterialSupplier} placeholder="Opcional" value={materialSupplier} />
                <FormField label="Observación" onChangeText={setMaterialObservation} placeholder="Opcional" value={materialObservation} />
              </>
            ) : (
              <>
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
                        <Choice active={conditionId === condition.id} key={condition.id} label={condition.codigo} onPress={() => setConditionId(condition.id)} />
                      ))}
                    </View>
                  </ScrollView>
                </View>

                <FormField label="Variedad" onChangeText={setVariety} placeholder="Ej. Santina" value={variety} />
                <FormField label="Calibre" onChangeText={setCaliber} placeholder="Ej. 2J" value={caliber} />
                <FormField label="Marca" onChangeText={setBrand} placeholder="Opcional" value={brand} />
                <FormField label="Exportadora" onChangeText={setExporter} placeholder="Opcional" value={exporter} />
              </>
            )}
          </ScrollView>

          <ModalError message={error} />

          <DialogActions
            busy={busy}
            compact={compact}
            confirmDisabled={!folio.trim() || (isMaterial && (!materialItemId || Number(materialQuantity) <= 0))}
            confirmLabel="Confirmar ubicación"
            onCancel={onCancel}
            onConfirm={submit}
          />
        </View>
        </View>
      </SafeAreaView>
    </Modal>
  );
}

type MoveModalProps = {
  busy: boolean;
  cameras: CameraSummary[];
  destinationPlan: CameraPlan | null;
  error: string;
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
  error,
  onCancel,
  onChooseCamera,
  onConfirm,
  onSelectPosition,
  originPlan,
  originPosition,
  selectedDestination,
  visible,
}: MoveModalProps) {
  const { height, width } = useWindowDimensions();
  const compact = height < 700 || width < 1000;
  const freePositions = destinationPlan?.posiciones.filter((position) => (
    position.estado === 'activa'
    && !position.ocupada
    && position.id !== originPosition?.id
  )) ?? [];

  return (
    <Modal animationType="fade" onRequestClose={onCancel} transparent visible={visible}>
      <SafeAreaView edges={['top', 'right', 'bottom', 'left']} style={styles.modalSafeArea}>
        <View style={[styles.backdrop, compact && styles.backdropCompact]}>
        <View style={[styles.dialog, styles.moveDialog, compact && styles.dialogCompact]}>
          <DialogHeading
            compact={compact}
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
            <Text style={styles.label}>PLANO DE DESTINO · POSICIONES LIBRES</Text>
            <Text style={styles.destinationCount}>
              {destinationPlan ? freePositions.length + ' disponibles' : 'Cargando plano…'}
            </Text>
          </View>

          {!destinationPlan ? (
            <ActivityIndicator color={colors.cyan} style={styles.loader} />
          ) : destinationPlan.acceso.modo === 'solo_lectura' ? (
            <Text style={styles.empty}>La cámara de destino está siendo modificada por otro operador.</Text>
          ) : (
            <DestinationPlanMap
              compact={compact}
              onSelectPosition={onSelectPosition}
              originPositionId={originPosition?.id ?? null}
              plan={destinationPlan}
              selectedPositionId={selectedDestination?.id ?? null}
            />
          )}

          <ModalError message={error} />

          <DialogActions
            busy={busy}
            compact={compact}
            confirmDisabled={!selectedDestination}
            confirmLabel="Confirmar movimiento"
            onCancel={onCancel}
            onConfirm={onConfirm}
          />
        </View>
        </View>
      </SafeAreaView>
    </Modal>
  );
}

export type MaterialDispatchFormValue = {
  cantidad: number;
  despacho_id?: string;
  destino_material_id?: string;
};

export function MaterialDispatchModal({
  busy,
  canCreate,
  destinations,
  dispatches,
  error,
  onCancel,
  onConfirm,
  position,
  visible,
}: {
  busy: boolean;
  canCreate: boolean;
  destinations: MaterialDestination[];
  dispatches: MaterialDispatch[];
  error: string;
  onCancel: () => void;
  onConfirm: (value: MaterialDispatchFormValue) => Promise<void>;
  position: Position | null;
  visible: boolean;
}) {
  const { height, width } = useWindowDimensions();
  const compact = height < 700 || width < 1000;
  const material = position?.folio?.material;
  const matchingDispatches = dispatches.filter((dispatch) => dispatch.items.some((detail) => (
    detail.item.id === material?.item.id && Number(detail.cantidad_pendiente) > 0
  )));
  const prioritizedDispatches = [...matchingDispatches].sort((left, right) => (
    reservationForFolio(right, material?.item.id, position?.folio?.id)
      - reservationForFolio(left, material?.item.id, position?.folio?.id)
  ));
  const preferredDispatchId = prioritizedDispatches[0]?.id;
  const [dispatchId, setDispatchId] = useState<string>();
  const [destinationId, setDestinationId] = useState<string>();
  const [amount, setAmount] = useState('');

  useEffect(() => {
    if (!visible) return;
    setDispatchId(preferredDispatchId ?? (canCreate ? undefined : matchingDispatches[0]?.id));
    setDestinationId(undefined);
    setAmount('');
  }, [canCreate, preferredDispatchId, visible, position?.folio?.id]);

  const selectedDispatch = matchingDispatches.find((dispatch) => dispatch.id === dispatchId);
  const selectedDetail = selectedDispatch?.items.find((detail) => detail.item.id === material?.item.id);
  const ownReservation = reservationForFolio(
    selectedDispatch,
    material?.item.id,
    position?.folio?.id,
  );
  const followsFifo = ownReservation > 0;
  const maximum = Math.min(
    Number(material?.cantidad_disponible ?? 0) + ownReservation,
    selectedDetail ? Number(selectedDetail.cantidad_pendiente) : Number(material?.cantidad_disponible ?? 0),
  );

  async function submit() {
    const parsed = Number(amount);
    if (parsed <= 0 || parsed > maximum || (!dispatchId && !destinationId)) return;
    await onConfirm({
      cantidad: parsed,
      despacho_id: dispatchId,
      destino_material_id: dispatchId ? undefined : destinationId,
    });
  }

  return (
    <Modal animationType="fade" onRequestClose={onCancel} transparent visible={visible}>
      <SafeAreaView edges={['top', 'right', 'bottom', 'left']} style={styles.modalSafeArea}>
        <View style={[styles.backdrop, compact && styles.backdropCompact]}>
          <View style={[styles.dialog, compact && styles.dialogCompact]}>
            <DialogHeading
              compact={compact}
              eyebrow="DESPACHO DE MATERIALES"
              onClose={onCancel}
              subtitle={`${position?.folio?.numero_folio ?? ''} · ${position?.etiqueta ?? ''}`}
              title={material ? `${material.item.cliente.temporada.codigo} · ${material.item.cliente.codigo} · ${material.item.nombre}` : 'Retirar material'}
            />

            <View style={styles.materialBalance}>
              <View><Text style={styles.label}>SALDO ACTUAL</Text><Text style={styles.materialBalanceValue}>{material?.cantidad_actual ?? '0'} {material?.unidad_medida}</Text></View>
              <View><Text style={styles.label}>DISPONIBLE</Text><Text style={styles.materialBalanceValue}>{material?.cantidad_disponible ?? '0'} {material?.unidad_medida}</Text></View>
            </View>

            <Text style={styles.label}>{canCreate ? 'Orden existente o despacho directo' : 'Orden de despacho asignada'}</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator>
              <View style={styles.choiceRow}>
                {canCreate && <Choice active={!dispatchId} label="Nuevo despacho" onPress={() => { setDispatchId(undefined); setAmount(''); }} />}
                {prioritizedDispatches.map((dispatch) => (
                  <Choice
                    active={dispatchId === dispatch.id}
                    key={dispatch.id}
                    label={materialDispatchLabel(dispatch, material?.item.id, position?.folio?.id, material?.unidad_medida)}
                    onPress={() => { setDispatchId(dispatch.id); setAmount(''); }}
                  />
                ))}
              </View>
            </ScrollView>

            {canCreate && !dispatchId && (
              <View style={[styles.field, styles.wide, styles.materialField]}>
                <Text style={styles.label}>Destino y centro de costo *</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator>
                  <View style={styles.choiceRow}>
                    {destinations.map((destination) => (
                      <Choice active={destinationId === destination.id} key={destination.id} label={`${destination.nombre} · ${destination.centro_costo}`} onPress={() => setDestinationId(destination.id)} />
                    ))}
                  </View>
                </ScrollView>
              </View>
            )}

            {dispatchId && (
              <View style={[styles.fifoNotice, followsFifo === false && styles.fifoNoticeOverride]}>
                <Text style={styles.fifoNoticeTitle}>{followsFifo ? 'Folio sugerido por FIFO' : 'Selección distinta de FIFO'}</Text>
                <Text style={styles.fifoNoticeText}>{selectedDispatch?.destino.nombre} · {selectedDispatch?.destino.centro_costo} · pendiente {selectedDetail?.cantidad_pendiente} {selectedDetail?.unidad_medida}</Text>
                <Text style={styles.fifoReservationText}>Asignado a este folio: {ownReservation} {selectedDetail?.unidad_medida}</Text>
              </View>
            )}

            <View style={[styles.field, styles.wide, styles.materialField]}>
              <Text style={styles.label}>Cantidad a despachar * · máximo {maximum} {material?.unidad_medida}</Text>
              <TextInput keyboardType="decimal-pad" onChangeText={setAmount} placeholder="0" placeholderTextColor="#627C88" selectionColor={colors.cyan} style={styles.input} value={amount} />
            </View>

            <ModalError message={error} />
            <DialogActions busy={busy} compact={compact} confirmDisabled={Number(amount) <= 0 || Number(amount) > maximum || (!dispatchId && !destinationId)} confirmLabel="Confirmar despacho" onCancel={onCancel} onConfirm={submit} />
          </View>
        </View>
      </SafeAreaView>
    </Modal>
  );
}

function reservationForFolio(
  dispatch: MaterialDispatch | undefined,
  itemId: string | undefined,
  folioId: string | undefined,
): number {
  if (!dispatch || !itemId || !folioId) return 0;
  const detail = dispatch.items.find((item) => item.item.id === itemId);
  const reservation = detail?.sugerencias_fifo.find((suggestion) => suggestion.folio_id === folioId);

  return Number(reservation?.cantidad ?? 0);
}

function materialDispatchLabel(
  dispatch: MaterialDispatch,
  itemId: string | undefined,
  folioId: string | undefined,
  unit: string | undefined,
): string {
  const reservation = reservationForFolio(dispatch, itemId, folioId);
  const fifo = reservation > 0 ? ` · FIFO ${reservation} ${unit ?? ''}` : '';

  return `${dispatch.codigo} · ${dispatch.destino.nombre}${fifo}`;
}

function DestinationPlanMap({
  compact,
  onSelectPosition,
  originPositionId,
  plan,
  selectedPositionId,
}: {
  compact: boolean;
  onSelectPosition: (position: Position) => void;
  originPositionId: string | null;
  plan: CameraPlan;
  selectedPositionId: string | null;
}) {
  const levels = [...new Set(plan.posiciones.map((position) => position.nivel))].sort((a, b) => a - b);
  const bands = [...new Set(plan.posiciones.map((position) => position.banda))].sort((a, b) => a - b);
  const maxPosition = Math.max(1, ...plan.posiciones.map((position) => position.posicion));
  const [selectedLevel, setSelectedLevel] = useState(levels[0] ?? 1);

  useEffect(() => {
    setSelectedLevel(levels[0] ?? 1);
  }, [plan.id, plan.version_plano]);

  return (
    <View style={styles.destinationMap}>
      {levels.length > 1 && (
        <View style={styles.destinationLevelPicker}>
          {levels.map((level) => (
            <Pressable
              key={level}
              onPress={() => setSelectedLevel(level)}
              style={[
                styles.destinationLevelButton,
                selectedLevel === level && styles.destinationLevelButtonActive,
              ]}
            >
              <Text style={[
                styles.destinationLevelText,
                selectedLevel === level && styles.destinationLevelTextActive,
              ]}>
                NIVEL {level}
              </Text>
            </Pressable>
          ))}
        </View>
      )}

      <View style={styles.destinationOrientationRow}>
        <Text style={styles.destinationOrientation}>↑ FONDO</Text>
        <Text style={styles.destinationOrientationHint}>Bandas verticales · P01 se ocupa primero</Text>
      </View>

      <ScrollView
        nestedScrollEnabled
        style={[styles.destinationScroll, compact && styles.destinationScrollCompact]}
      >
        <ScrollView
          contentContainerStyle={styles.destinationBandRow}
          horizontal
          nestedScrollEnabled
          showsHorizontalScrollIndicator
        >
          {bands.map((band) => (
            <DestinationBand
              band={band}
              key={band}
              level={selectedLevel}
              maxPosition={maxPosition}
              onSelectPosition={onSelectPosition}
              originPositionId={originPositionId}
              plan={plan}
              selectedPositionId={selectedPositionId}
            />
          ))}
        </ScrollView>
      </ScrollView>

      <Text style={styles.destinationEntrance}>↓ ENTRADA</Text>
    </View>
  );
}

function DestinationBand({
  band,
  level,
  maxPosition,
  onSelectPosition,
  originPositionId,
  plan,
  selectedPositionId,
}: {
  band: number;
  level: number;
  maxPosition: number;
  onSelectPosition: (position: Position) => void;
  originPositionId: string | null;
  plan: CameraPlan;
  selectedPositionId: string | null;
}) {
  const positions = Array.from({ length: maxPosition }, (_, index) => index + 1);

  return (
    <View style={styles.destinationBand}>
      <Text style={styles.destinationBandHeading}>BANDA {String(band).padStart(2, '0')}</Text>
      {positions.map((number) => {
        const position = plan.posiciones.find((candidate) => (
          candidate.nivel === level
          && candidate.banda === band
          && candidate.posicion === number
        ));

        if (!position) return <View key={number} style={styles.destinationGap} />;

        const isOrigin = position.id === originPositionId;
        const available = position.estado === 'activa' && !position.ocupada && !isOrigin;
        const status = isOrigin
          ? 'ORIGEN'
          : position.ocupada
            ? `OCUPADA${position.folio?.numero_folio ? ` · ${position.folio.numero_folio}` : ''}`
            : position.estado === 'activa' ? 'LIBRE' : 'NO DISPONIBLE';

        return (
          <Pressable
            accessibilityLabel={`${position.etiqueta ?? `Banda ${band}, posición ${number}`}, ${status}`}
            accessibilityRole="button"
            disabled={!available}
            key={position.id}
            onPress={() => onSelectPosition(position)}
            style={({ pressed }) => [
              styles.destination,
              !available && styles.destinationUnavailable,
              selectedPositionId === position.id && styles.destinationSelected,
              pressed && styles.pressed,
            ]}
          >
            <Text style={styles.destinationPositionNumber}>P{String(number).padStart(2, '0')}</Text>
            <Text numberOfLines={1} style={styles.destinationLabel}>{position.etiqueta}</Text>
            <Text numberOfLines={1} style={styles.destinationMeta}>{status}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

function DialogHeading({
  compact,
  eyebrow,
  onClose,
  subtitle,
  title,
}: {
  compact?: boolean;
  eyebrow: string;
  onClose: () => void;
  subtitle: string;
  title: string;
}) {
  return (
    <View style={[styles.heading, compact && styles.headingCompact]}>
      <View style={styles.headingCopy}>
        <Text style={styles.eyebrow}>{eyebrow}</Text>
        <Text style={[styles.title, compact && styles.titleCompact]}>{title}</Text>
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

function ModalError({ message }: { message: string }) {
  if (!message) return null;

  return (
    <View accessibilityLiveRegion="assertive" style={styles.modalError}>
      <Text style={styles.modalErrorTitle}>No fue posible confirmar la operación</Text>
      <Text style={styles.modalErrorText}>{message}</Text>
    </View>
  );
}

function DialogActions({
  busy,
  compact,
  confirmDisabled,
  confirmLabel,
  onCancel,
  onConfirm,
}: {
  busy: boolean;
  compact?: boolean;
  confirmDisabled: boolean;
  confirmLabel: string;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  return (
    <View style={[styles.actions, compact && styles.actionsCompact]}>
      <Pressable disabled={busy} onPress={onCancel} style={[styles.cancel, compact && styles.buttonCompact]}>
        <Text style={styles.cancelText}>Cancelar</Text>
      </Pressable>
      <Pressable
        disabled={busy || confirmDisabled}
        onPress={onConfirm}
        style={[
          styles.confirm,
          compact && styles.buttonCompact,
          (busy || confirmDisabled) && styles.disabled,
        ]}
      >
        {busy ? <ActivityIndicator color={colors.accentText} /> : <Text style={styles.confirmText}>{confirmLabel}</Text>}
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  modalSafeArea: { flex: 1, backgroundColor: 'rgba(0,0,0,0.72)' },
  backdrop: {
    flex: 1,
    padding: 24,
    alignItems: 'center',
    justifyContent: 'center',
  },
  backdropCompact: { padding: 8 },
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
  dialogCompact: { width: '100%', maxHeight: '100%', padding: 14, borderRadius: 14 },
  moveDialog: { maxWidth: 900 },
  heading: { marginBottom: 16, flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  headingCompact: { marginBottom: 10 },
  headingCopy: { flex: 1 },
  eyebrow: { color: colors.cyan, fontSize: 9, fontWeight: '900', letterSpacing: 1.2 },
  title: { marginTop: 4, color: colors.text, fontSize: 23, fontWeight: '900' },
  titleCompact: { fontSize: 19 },
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
  formScroll: { flexShrink: 1 },
  form: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, paddingBottom: 2 },
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
  destinationMap: {
    minHeight: 100,
    padding: 8,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
  },
  destinationLevelPicker: { marginBottom: 8, flexDirection: 'row', gap: 6 },
  destinationLevelButton: {
    paddingHorizontal: 9,
    paddingVertical: 6,
    borderRadius: 7,
    borderWidth: 1,
    borderColor: colors.border,
  },
  destinationLevelButtonActive: { borderColor: colors.cyan, backgroundColor: colors.cyanDark },
  destinationLevelText: { color: colors.muted, fontSize: 8, fontWeight: '900' },
  destinationLevelTextActive: { color: colors.cyan },
  destinationOrientationRow: {
    marginBottom: 6,
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
  },
  destinationOrientation: { color: colors.cyan, fontSize: 8, fontWeight: '900' },
  destinationOrientationHint: { color: colors.muted, fontSize: 7 },
  destinationScroll: { minHeight: 80, maxHeight: 270, flexShrink: 1 },
  destinationScrollCompact: { maxHeight: 210 },
  destinationBandRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 7, paddingBottom: 3 },
  destinationBand: {
    width: 122,
    padding: 6,
    borderRadius: 9,
    borderWidth: 1,
    borderColor: colors.borderSoft,
    backgroundColor: colors.panel,
    gap: 5,
  },
  destinationBandHeading: {
    marginHorizontal: -6,
    marginTop: -6,
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    borderTopLeftRadius: 8,
    borderTopRightRadius: 8,
    backgroundColor: colors.panelStrong,
    color: colors.cyan,
    textAlign: 'center',
    fontSize: 9,
    fontWeight: '900',
  },
  destination: {
    height: 66,
    padding: 7,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
    justifyContent: 'space-between',
  },
  destinationSelected: { borderColor: colors.cyan, backgroundColor: colors.selected },
  destinationUnavailable: { opacity: 0.48, backgroundColor: colors.panelStrong },
  destinationGap: { height: 66 },
  destinationPositionNumber: { color: colors.cyan, fontSize: 8, fontWeight: '900' },
  destinationLabel: { color: colors.text, fontSize: 9, fontWeight: '900' },
  destinationMeta: { color: colors.muted, fontSize: 7 },
  destinationEntrance: { marginTop: 6, color: colors.cyan, fontSize: 8, fontWeight: '900' },
  loader: { height: 150 },
  empty: { paddingVertical: 30, color: colors.muted, fontSize: 10, textAlign: 'center' },
  emptyInline: { color: colors.muted, fontSize: 8 },
  materialBalance: { marginBottom: 14, flexDirection: 'row', gap: 10 },
  materialBalanceValue: { marginTop: 4, color: colors.text, fontSize: 17, fontWeight: '900' },
  materialField: { marginTop: 14 },
  fifoNotice: { marginTop: 14, padding: 10, borderRadius: 9, borderWidth: 1, borderColor: colors.green, backgroundColor: '#12372F' },
  fifoNoticeOverride: { borderColor: colors.amber, backgroundColor: colors.amberDark },
  fifoNoticeTitle: { color: colors.text, fontSize: 9, fontWeight: '900' },
  fifoNoticeText: { marginTop: 3, color: colors.muted, fontSize: 8 },
  fifoReservationText: { marginTop: 5, color: colors.text, fontSize: 9, fontWeight: '900' },
  modalError: {
    marginTop: 10,
    padding: 10,
    borderRadius: 9,
    borderWidth: 1,
    borderColor: colors.red,
    backgroundColor: '#421B21',
  },
  modalErrorTitle: { color: '#FFD0D0', fontSize: 9, fontWeight: '900' },
  modalErrorText: { marginTop: 3, color: '#FFB7B7', fontSize: 9, lineHeight: 13 },
  actions: { marginTop: 18, flexDirection: 'row', justifyContent: 'flex-end', gap: 10 },
  actionsCompact: { marginTop: 10 },
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
  confirmText: { color: colors.accentText, fontSize: 10, fontWeight: '900' },
  buttonCompact: { height: 40 },
  disabled: { opacity: 0.4 },
  pressed: { opacity: 0.72 },
});
