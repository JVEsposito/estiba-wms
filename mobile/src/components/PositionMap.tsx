import { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { CameraPlan, Position } from '../domain/estiba';
import { colors } from '../theme/colors';

type PositionMapProps = {
  plan: CameraPlan;
  selectedPositionId: string | null;
  onSelectPosition: (position: Position) => void;
  highlightedFolioIds?: string[];
  suggestedFolioId?: string | null;
};

export function PositionMap({
  highlightedFolioIds = [],
  onSelectPosition,
  plan,
  selectedPositionId,
  suggestedFolioId = null,
}: PositionMapProps) {
  const levels = [...new Set(plan.posiciones.map((position) => position.nivel))].sort((a, b) => a - b);
  const bands = [...new Set(plan.posiciones.map((position) => position.banda))].sort((a, b) => a - b);
  const maxPosition = Math.max(1, ...plan.posiciones.map((position) => position.posicion));
  const [selectedLevel, setSelectedLevel] = useState(levels[0] ?? 1);

  useEffect(() => {
    if (!levels.includes(selectedLevel)) setSelectedLevel(levels[0] ?? 1);
  }, [levels, selectedLevel]);

  useEffect(() => {
    const selected = plan.posiciones.find((position) => position.id === selectedPositionId);
    if (selected && selected.nivel !== selectedLevel) setSelectedLevel(selected.nivel);
  }, [plan, selectedLevel, selectedPositionId]);

  return (
    <View style={styles.panel}>
      <View style={styles.heading}>
        <View style={styles.headingCopy}>
          <Text style={styles.eyebrow}>PLANO DE ESTIBA · {plan.tipo.toUpperCase()}</Text>
          <View style={styles.titleRow}>
            <Text numberOfLines={1} style={styles.title}>{plan.codigo} · {plan.nombre}</Text>
            <Text style={styles.version}>v{plan.version_plano}</Text>
          </View>
          <Text style={styles.subtitle}>{plan.posiciones.length} posiciones configuradas</Text>
        </View>
        <View style={styles.occupancy}>
          <Text style={styles.occupancyLabel}>OCUPACIÓN</Text>
          <Text style={styles.occupancyValue}>{plan.ocupacion.porcentaje}%</Text>
          <Text style={styles.occupancyDetail}>
            {plan.ocupacion.ocupadas} de {plan.ocupacion.total}
          </Text>
        </View>
      </View>

      {plan.acceso.modo === 'solo_lectura' && (
        <View style={styles.lockBanner}>
          <Text style={styles.lockIcon}>⌁</Text>
          <View style={styles.lockCopy}>
            <Text style={styles.lockTitle}>Cámara en modificación</Text>
            <Text style={styles.lockText}>
              {plan.acceso.sesion?.usuario.nombre ?? 'Otro operador'} trabaja desde{' '}
              {plan.acceso.sesion?.dispositivo.nombre ?? 'otra tablet'}. El plano está en consulta.
            </Text>
          </View>
        </View>
      )}

      <View style={styles.toolbar}>
        <View style={styles.legend}>
          <Legend color={colors.free} border={colors.freeBorder} label="Libre" />
          <Legend color={colors.pallet} border={colors.palletBorder} label={plan.contenido === 'materiales' ? 'Material' : 'Pallet'} />
          {plan.contenido !== 'materiales' && <Legend color={colors.saldo} border={colors.saldoBorder} label="Saldo" />}
          <Legend color={colors.blocked} border={colors.blockedBorder} label="Bloqueada" />
        </View>
        <View style={styles.levelPicker}>
          {levels.map((level) => (
            <Pressable
              key={level}
              onPress={() => setSelectedLevel(level)}
              style={[styles.levelButton, selectedLevel === level && styles.levelButtonActive]}
            >
              <Text style={[styles.levelText, selectedLevel === level && styles.levelTextActive]}>
                NIVEL {level}
              </Text>
            </Pressable>
          ))}
        </View>
      </View>

      <View style={styles.orientationRow}>
        <Text style={styles.orientation}>↑ FONDO</Text>
        <Text style={styles.orientationHint}>Bandas verticales · P01 se ocupa primero</Text>
      </View>

      <ScrollView horizontal showsHorizontalScrollIndicator={false}>
        <View style={styles.bandRow}>
          {bands.map((band) => (
            <Band
              band={band}
              key={band}
              level={selectedLevel}
              maxPosition={maxPosition}
              onSelectPosition={onSelectPosition}
              plan={plan}
              selectedPositionId={selectedPositionId}
              highlightedFolioIds={highlightedFolioIds}
              suggestedFolioId={suggestedFolioId}
            />
          ))}
        </View>
      </ScrollView>

      <Text style={styles.entrance}>↓ ENTRADA</Text>
    </View>
  );
}

type BandProps = PositionMapProps & {
  band: number;
  level: number;
  maxPosition: number;
};

function Band({
  band,
  highlightedFolioIds = [],
  level,
  maxPosition,
  onSelectPosition,
  plan,
  selectedPositionId,
  suggestedFolioId = null,
}: BandProps) {
  const positions = Array.from({ length: maxPosition }, (_, index) => index + 1);

  return (
    <View style={styles.band}>
      <Text style={styles.bandHeading}>BANDA {String(band).padStart(2, '0')}</Text>
      {positions.map((number) => {
        const position = plan.posiciones.find((candidate) => (
          candidate.nivel === level
          && candidate.banda === band
          && candidate.posicion === number
        ));

        if (!position) return <View key={number} style={styles.gap} />;

        const blocked = position.estado !== 'activa';
        const occupied = position.ocupada;
        const saldo = position.folio?.tipo_bulto === 'saldo';
        const selected = position.id === selectedPositionId;
        const loadFolio = Boolean(position.folio && highlightedFolioIds?.includes(position.folio.id));
        const suggested = position.folio?.id === suggestedFolioId;

        return (
          <Pressable
            accessibilityLabel={`${position.etiqueta}, ${position.folio?.numero_folio ?? (blocked ? 'bloqueada' : 'libre')}`}
            accessibilityRole="button"
            key={position.id}
            onPress={() => onSelectPosition(position)}
            style={({ pressed }) => [
              styles.cell,
              occupied && styles.occupied,
              saldo && styles.saldo,
              blocked && styles.blocked,
              loadFolio && styles.loadFolio,
              suggested && styles.suggested,
              selected && styles.selected,
              pressed && styles.pressed,
            ]}
          >
            <View style={styles.cellTop}>
              <Text style={styles.positionNumber}>P{String(number).padStart(2, '0')}</Text>
              <Text style={styles.cellLocation}>{position.etiqueta}</Text>
            </View>
            <Text numberOfLines={1} style={styles.cellFolio}>
              {position.folio?.numero_folio ?? (blocked ? 'NO DISP.' : 'LIBRE')}
            </Text>
            <View style={styles.cellMetaRow}>
              <Text numberOfLines={1} style={styles.cellMeta}>
                {position.folio?.material
                  ? `${position.folio.material.item.codigo} · ${position.folio.material.cantidad_actual} ${position.folio.material.unidad_medida}`
                  : position.folio?.variedad ?? (blocked ? position.estado : 'Disponible')}
              </Text>
              <Text style={styles.cellKind}>{occupied ? (saldo ? 'S' : 'P') : '○'}</Text>
            </View>
          </Pressable>
        );
      })}
    </View>
  );
}

function Legend({ color, label, border }: { color: string; label: string; border?: string }) {
  return (
    <View style={styles.legendItem}>
      <View style={[styles.swatch, { backgroundColor: color, borderColor: border ?? colors.border }]} />
      <Text style={styles.legendText}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  panel: {
    flex: 1,
    minWidth: 0,
    padding: 17,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
  },
  heading: { flexDirection: 'row', justifyContent: 'space-between', gap: 16 },
  headingCopy: { flex: 1, minWidth: 0 },
  eyebrow: { color: colors.cyan, fontSize: 9, fontWeight: '900', letterSpacing: 1.2 },
  titleRow: { marginTop: 4, flexDirection: 'row', alignItems: 'center', gap: 8 },
  title: { flexShrink: 1, color: colors.text, fontSize: 19, fontWeight: '900' },
  version: {
    paddingHorizontal: 7,
    paddingVertical: 3,
    borderRadius: 6,
    backgroundColor: colors.borderSoft,
    color: colors.cyan,
    fontSize: 9,
    fontWeight: '800',
  },
  subtitle: { marginTop: 3, color: colors.muted, fontSize: 10 },
  occupancy: { alignItems: 'flex-end' },
  occupancyLabel: { color: colors.muted, fontSize: 8, fontWeight: '800' },
  occupancyValue: { color: colors.text, fontSize: 22, fontWeight: '900' },
  occupancyDetail: { color: colors.muted, fontSize: 9 },
  lockBanner: {
    marginTop: 12,
    padding: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.amber,
    backgroundColor: colors.amberDark,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  lockIcon: { color: colors.amber, fontSize: 24, fontWeight: '900' },
  lockCopy: { flex: 1 },
  lockTitle: { color: colors.amber, fontSize: 11, fontWeight: '900' },
  lockText: { marginTop: 2, color: colors.text, fontSize: 9, lineHeight: 13 },
  toolbar: {
    marginVertical: 12,
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  legend: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  swatch: { width: 14, height: 14, borderRadius: 3, borderWidth: 1 },
  legendText: { color: colors.muted, fontSize: 9, fontWeight: '700' },
  levelPicker: { flexDirection: 'row', gap: 5 },
  levelButton: {
    paddingHorizontal: 10,
    paddingVertical: 7,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
  },
  levelButtonActive: { borderColor: colors.cyan, backgroundColor: colors.cyanDark },
  levelText: { color: colors.muted, fontSize: 8, fontWeight: '900' },
  levelTextActive: { color: colors.cyan },
  orientationRow: { marginBottom: 7, flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  orientation: { color: colors.cyan, fontSize: 9, fontWeight: '900' },
  orientationHint: { color: colors.muted, fontSize: 8 },
  bandRow: { flexDirection: 'row', gap: 8, paddingBottom: 3 },
  band: {
    width: 138,
    padding: 7,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: colors.borderSoft,
    backgroundColor: colors.background,
    gap: 5,
  },
  bandHeading: {
    marginHorizontal: -7,
    marginTop: -7,
    marginBottom: 2,
    paddingVertical: 9,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    borderTopLeftRadius: 10,
    borderTopRightRadius: 10,
    backgroundColor: colors.panelStrong,
    color: colors.cyan,
    textAlign: 'center',
    fontSize: 10,
    fontWeight: '900',
    letterSpacing: .4,
  },
  gap: { height: 76 },
  cell: {
    height: 76,
    padding: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.freeBorder,
    backgroundColor: colors.free,
    justifyContent: 'space-between',
  },
  occupied: { borderColor: colors.palletBorder, backgroundColor: colors.pallet },
  saldo: { borderColor: colors.saldoBorder, backgroundColor: colors.saldo },
  blocked: { borderColor: colors.blockedBorder, backgroundColor: colors.blocked, opacity: 0.82 },
  loadFolio: { borderWidth: 2, borderColor: colors.amber },
  suggested: { borderWidth: 3, borderColor: colors.green },
  selected: { borderWidth: 3, borderColor: colors.cyan },
  pressed: { opacity: 0.72 },
  cellTop: { flexDirection: 'row', justifyContent: 'space-between', gap: 4 },
  positionNumber: { color: colors.cyan, fontSize: 9, fontWeight: '900' },
  cellLocation: { color: colors.muted, fontSize: 8, fontWeight: '800' },
  cellFolio: { color: colors.text, fontSize: 11, fontWeight: '900' },
  cellMetaRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 3 },
  cellMeta: { flex: 1, color: colors.text, fontSize: 8 },
  cellKind: { color: colors.text, fontSize: 9, fontWeight: '900' },
  entrance: { marginTop: 7, color: colors.cyan, fontSize: 9, fontWeight: '900' },
});
