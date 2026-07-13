import { ScrollView, StyleSheet, Text, View, Pressable } from 'react-native';

import { Camera, Position } from '../domain/dashboard';
import { colors } from '../theme/colors';

type PositionMapProps = {
  camera: Camera;
  selectedPositionId: string | null;
  onSelectPosition: (position: Position) => void;
};

const columns = Array.from({ length: 8 }, (_, index) => index + 1);
const levels = [4, 3, 2, 1];

export function PositionMap({ camera, selectedPositionId, onSelectPosition }: PositionMapProps) {
  return (
    <View style={styles.panel}>
      <View style={styles.titleRow}>
        <View>
          <Text style={styles.eyebrow}>MAPA DE POSICIONES</Text>
          <Text style={styles.title}>{camera.name}</Text>
        </View>
        <Text style={styles.counter}>{camera.occupied} / {camera.capacity}</Text>
      </View>

      <ScrollView horizontal showsHorizontalScrollIndicator={false}>
        <View>
          <View style={styles.columnHeader}>
            <View style={styles.levelSpacer} />
            {columns.map((column) => (
              <Text key={column} style={styles.columnLabel}>{String(column).padStart(2, '0')}</Text>
            ))}
          </View>

          {levels.map((level) => (
            <View key={level} style={styles.levelRow}>
              <View style={styles.levelLabel}>
                <Text style={styles.levelCaption}>NIVEL</Text>
                <Text style={styles.levelNumber}>{String(level).padStart(2, '0')}</Text>
              </View>

              {columns.map((column) => {
                const position = camera.positions.find(
                  (candidate) => candidate.level === level && candidate.column === column,
                );

                if (!position) {
                  return <View key={`${level}-${column}`} style={styles.position} />;
                }

                const selected = position.id === selectedPositionId;

                return (
                  <Pressable
                    accessibilityLabel={`${position.label}, ${position.folio ?? 'libre'}`}
                    accessibilityRole="button"
                    key={position.id}
                    onPress={() => onSelectPosition(position)}
                    style={({ pressed }) => [
                      styles.position,
                      position.folio && styles.positionOccupied,
                      selected && styles.positionSelected,
                      pressed && styles.positionPressed,
                    ]}
                  >
                    <Text style={styles.positionLabel}>{position.label}</Text>
                    <Text numberOfLines={1} style={styles.positionFolio}>
                      {position.folio ?? 'LIBRE'}
                    </Text>
                    {selected && <Text style={styles.selectedText}>SELECCIONADO</Text>}
                  </Pressable>
                );
              })}
            </View>
          ))}
        </View>
      </ScrollView>

      <View style={styles.legend}>
        <Legend color={colors.greenDark} label="Ocupado" />
        <Legend color={colors.free} label="Libre" />
        <Legend color={colors.selected} borderColor={colors.cyan} label="Seleccionado" />
      </View>
    </View>
  );
}

function Legend({ color, label, borderColor }: { color: string; label: string; borderColor?: string }) {
  return (
    <View style={styles.legendItem}>
      <View style={[styles.legendSwatch, { backgroundColor: color, borderColor: borderColor ?? colors.border }]} />
      <Text style={styles.legendText}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  panel: {
    flex: 1,
    minWidth: 0,
    padding: 18,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
  },
  titleRow: {
    marginBottom: 8,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
  },
  eyebrow: {
    color: colors.cyan,
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 1.2,
  },
  title: {
    marginTop: 2,
    color: colors.text,
    fontSize: 21,
    fontWeight: '800',
  },
  counter: {
    color: colors.muted,
    fontSize: 14,
    fontWeight: '700',
  },
  columnHeader: {
    flexDirection: 'row',
    gap: 6,
    marginBottom: 6,
  },
  levelSpacer: {
    width: 54,
  },
  columnLabel: {
    width: 74,
    color: colors.cyan,
    textAlign: 'center',
    fontSize: 12,
    fontWeight: '700',
  },
  levelRow: {
    flexDirection: 'row',
    gap: 6,
    marginBottom: 6,
  },
  levelLabel: {
    width: 54,
    justifyContent: 'center',
  },
  levelCaption: {
    color: colors.muted,
    fontSize: 9,
    fontWeight: '800',
  },
  levelNumber: {
    color: colors.cyan,
    fontSize: 19,
    fontWeight: '800',
  },
  position: {
    width: 74,
    height: 65,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.free,
  },
  positionOccupied: {
    borderColor: colors.green,
    backgroundColor: colors.greenDark,
  },
  positionSelected: {
    borderWidth: 3,
    borderColor: colors.cyan,
    backgroundColor: colors.selected,
  },
  positionPressed: {
    opacity: 0.72,
  },
  positionLabel: {
    color: colors.text,
    fontSize: 13,
    fontWeight: '700',
  },
  positionFolio: {
    maxWidth: 68,
    marginTop: 5,
    color: colors.text,
    fontSize: 10,
  },
  selectedText: {
    position: 'absolute',
    bottom: 2,
    color: colors.cyan,
    fontSize: 6,
    fontWeight: '900',
  },
  legend: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.borderSoft,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 20,
  },
  legendItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
  },
  legendSwatch: {
    width: 20,
    height: 20,
    borderRadius: 4,
    borderWidth: 2,
  },
  legendText: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '700',
    textTransform: 'uppercase',
  },
});
