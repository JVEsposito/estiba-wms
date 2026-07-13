import { StyleSheet, Text, View } from 'react-native';

import { RecentMovement } from '../domain/dashboard';
import { colors } from '../theme/colors';

type RecentMovementsProps = {
  movements: RecentMovement[];
};

const labels = {
  ingreso: 'INGRESO',
  reubicacion: 'REUBICACIÓN',
  traslado: 'TRASLADO',
} as const;

export function RecentMovements({ movements }: RecentMovementsProps) {
  return (
    <View style={styles.panel}>
      <Text style={styles.title}>ÚLTIMOS MOVIMIENTOS</Text>
      <View style={styles.list}>
        {movements.map((movement) => (
          <View key={movement.id} style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.time}>{movement.time}</Text>
              <Text style={[styles.type, movement.type === 'traslado' && styles.typeTransfer]}>
                {labels[movement.type]}
              </Text>
            </View>
            <Text style={styles.folio}>{movement.folio}</Text>
            <Text numberOfLines={1} style={styles.route}>{movement.source}</Text>
            <Text numberOfLines={1} style={styles.destination}>→ {movement.destination}</Text>
          </View>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  panel: {
    marginTop: 14,
    padding: 16,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
  },
  title: {
    marginBottom: 10,
    color: colors.cyan,
    fontSize: 13,
    fontWeight: '800',
    letterSpacing: 1,
  },
  list: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  card: {
    minWidth: 240,
    flex: 1,
    padding: 13,
    borderRadius: 12,
    borderLeftWidth: 5,
    borderLeftColor: colors.green,
    backgroundColor: colors.panelStrong,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  time: {
    color: colors.muted,
    fontSize: 11,
  },
  type: {
    paddingHorizontal: 7,
    paddingVertical: 3,
    borderRadius: 5,
    borderWidth: 1,
    borderColor: colors.green,
    color: colors.green,
    fontSize: 9,
    fontWeight: '800',
  },
  typeTransfer: {
    borderColor: colors.cyan,
    color: colors.cyan,
  },
  folio: {
    marginTop: 8,
    color: colors.text,
    fontSize: 20,
    fontWeight: '900',
  },
  route: {
    marginTop: 4,
    color: colors.muted,
    fontSize: 11,
  },
  destination: {
    marginTop: 4,
    color: colors.cyan,
    fontSize: 12,
    fontWeight: '700',
  },
});
