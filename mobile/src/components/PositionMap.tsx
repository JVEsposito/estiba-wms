import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { CameraPlan, Position } from '../domain/estiba';
import { colors } from '../theme/colors';

type PositionMapProps = {
  plan: CameraPlan;
  selectedPositionId: string | null;
  onSelectPosition: (position: Position) => void;
};

export function PositionMap({ plan, selectedPositionId, onSelectPosition }: PositionMapProps) {
  const levels = [...new Set(plan.posiciones.map((position) => position.nivel))].sort((a, b) => a - b);
  const rows = [...new Set(plan.posiciones.map((position) => position.fila))].sort();
  const maxDepth = Math.max(1, ...plan.posiciones.map((position) => position.profundidad));

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

      <View style={styles.legend}>
        <Legend color={colors.free} label="Libre" />
        <Legend color={colors.greenDark} label="Pallet" />
        <Legend color={colors.amberDark} border={colors.amber} label="Saldo" />
        <Legend color="#303D45" label="Bloqueada" />
      </View>

      <ScrollView horizontal showsHorizontalScrollIndicator={false}>
        <View style={styles.mapContent}>
          {levels.map((level) => (
            <LevelGrid
              key={level}
              level={level}
              maxDepth={maxDepth}
              onSelectPosition={onSelectPosition}
              plan={plan}
              rows={rows}
              selectedPositionId={selectedPositionId}
            />
          ))}
        </View>
      </ScrollView>
    </View>
  );
}

type LevelGridProps = PositionMapProps & {
  level: number;
  maxDepth: number;
  rows: string[];
};

function LevelGrid({
  level,
  maxDepth,
  onSelectPosition,
  plan,
  rows,
  selectedPositionId,
}: LevelGridProps) {
  const depths = Array.from({ length: maxDepth }, (_, index) => index + 1);

  return (
    <View style={styles.levelGroup}>
      <Text style={styles.levelHeading}>NIVEL {level}</Text>
      <View style={styles.depthRow}>
        <Text style={styles.rowSpacer} />
        {depths.map((depth) => <Text key={depth} style={styles.depthLabel}>P{depth}</Text>)}
      </View>
      {rows.map((row) => (
        <View key={row} style={styles.positionRow}>
          <Text style={styles.rowLabel}>{row}</Text>
          {depths.map((depth) => {
            const position = plan.posiciones.find((candidate) => (
              candidate.nivel === level
              && candidate.fila === row
              && candidate.profundidad === depth
            ));
            if (!position) return <View key={depth} style={styles.gap} />;

            const blocked = position.estado !== 'activa';
            const occupied = position.ocupada;
            const saldo = position.folio?.tipo_bulto === 'saldo';
            const selected = position.id === selectedPositionId;

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
                  selected && styles.selected,
                  pressed && styles.pressed,
                ]}
              >
                <Text style={styles.cellLocation}>{position.etiqueta}</Text>
                <Text numberOfLines={1} style={styles.cellFolio}>
                  {position.folio?.numero_folio ?? (blocked ? 'NO DISP.' : 'LIBRE')}
                </Text>
                <View style={styles.cellMetaRow}>
                  <Text numberOfLines={1} style={styles.cellMeta}>
                    {position.folio?.variedad ?? (blocked ? position.estado : 'Disponible')}
                  </Text>
                  <Text style={styles.cellKind}>{occupied ? (saldo ? 'S' : 'P') : '○'}</Text>
                </View>
              </Pressable>
            );
          })}
        </View>
      ))}
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
  legend: { marginVertical: 12, flexDirection: 'row', flexWrap: 'wrap', gap: 14 },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  swatch: { width: 14, height: 14, borderRadius: 3, borderWidth: 1 },
  legendText: { color: colors.muted, fontSize: 9, fontWeight: '700' },
  mapContent: { gap: 14, paddingBottom: 2 },
  levelGroup: {
    padding: 10,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.borderSoft,
    backgroundColor: colors.background,
  },
  levelHeading: { marginBottom: 7, color: colors.cyan, fontSize: 10, fontWeight: '900' },
  depthRow: { flexDirection: 'row', gap: 5, marginBottom: 5 },
  rowSpacer: { width: 26 },
  depthLabel: { width: 82, color: colors.muted, textAlign: 'center', fontSize: 8, fontWeight: '800' },
  positionRow: { flexDirection: 'row', gap: 5, marginBottom: 5 },
  rowLabel: { width: 26, alignSelf: 'center', color: colors.cyan, textAlign: 'center', fontWeight: '900' },
  gap: { width: 82, height: 64 },
  cell: {
    width: 82,
    height: 64,
    padding: 7,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.free,
    justifyContent: 'space-between',
  },
  occupied: { borderColor: colors.green, backgroundColor: colors.greenDark },
  saldo: { borderColor: colors.amber, backgroundColor: colors.amberDark },
  blocked: { borderColor: '#46545C', backgroundColor: '#303D45', opacity: 0.72 },
  selected: { borderWidth: 3, borderColor: colors.cyan },
  pressed: { opacity: 0.72 },
  cellLocation: { color: colors.muted, fontSize: 8, fontWeight: '800' },
  cellFolio: { color: colors.text, fontSize: 10, fontWeight: '900' },
  cellMetaRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 3 },
  cellMeta: { flex: 1, color: colors.text, fontSize: 7 },
  cellKind: { color: colors.text, fontSize: 8, fontWeight: '900' },
});
