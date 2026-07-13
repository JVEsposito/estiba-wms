import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Camera } from '../domain/dashboard';
import { colors } from '../theme/colors';

type CameraCardProps = {
  camera: Camera;
  selected: boolean;
  onPress: () => void;
};

export function CameraCard({ camera, selected, onPress }: CameraCardProps) {
  const statusText = camera.status === 'editing' ? 'EN EDICIÓN' : 'DISPONIBLE';

  return (
    <Pressable
      accessibilityLabel={`Abrir ${camera.name}`}
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [
        styles.card,
        selected && styles.cardSelected,
        pressed && styles.cardPressed,
      ]}
    >
      <View style={styles.summary}>
        <Text style={styles.name}>{camera.name}</Text>
        <Text style={styles.occupancy}>
          <Text style={styles.occupancyValue}>{camera.occupied}</Text>
          <Text style={styles.capacity}> / {camera.capacity}</Text>
        </Text>
        <Text style={styles.caption}>posiciones ocupadas</Text>
        <View style={[styles.status, camera.status === 'editing' && styles.statusEditing]}>
          <Text style={[styles.statusText, camera.status === 'editing' && styles.statusTextEditing]}>
            {statusText}
          </Text>
        </View>
      </View>

      <View style={styles.preview}>
        {camera.positions.map((position) => (
          <View
            key={position.id}
            style={[styles.previewCell, position.folio && styles.previewCellOccupied]}
          />
        ))}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    minWidth: 250,
    flex: 1,
    minHeight: 142,
    padding: 16,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
    flexDirection: 'row',
    gap: 12,
  },
  cardSelected: {
    borderWidth: 2,
    borderColor: colors.cyan,
    backgroundColor: colors.panelStrong,
  },
  cardPressed: {
    opacity: 0.82,
  },
  summary: {
    flex: 1,
  },
  name: {
    color: colors.text,
    fontSize: 21,
    fontWeight: '700',
  },
  occupancy: {
    marginTop: 8,
  },
  occupancyValue: {
    color: colors.green,
    fontSize: 29,
    fontWeight: '800',
  },
  capacity: {
    color: colors.muted,
    fontSize: 17,
    fontWeight: '700',
  },
  caption: {
    color: colors.muted,
    fontSize: 12,
  },
  status: {
    alignSelf: 'flex-start',
    marginTop: 9,
    paddingHorizontal: 8,
    paddingVertical: 5,
    borderRadius: 7,
    borderWidth: 1,
    borderColor: colors.green,
  },
  statusEditing: {
    borderColor: colors.amber,
  },
  statusText: {
    color: colors.green,
    fontSize: 10,
    fontWeight: '800',
  },
  statusTextEditing: {
    color: colors.amber,
  },
  preview: {
    width: 96,
    alignSelf: 'center',
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 4,
    padding: 8,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
  },
  previewCell: {
    width: 7,
    height: 11,
    borderRadius: 2,
    backgroundColor: colors.muted,
    opacity: 0.72,
  },
  previewCellOccupied: {
    backgroundColor: colors.green,
    opacity: 1,
  },
});
