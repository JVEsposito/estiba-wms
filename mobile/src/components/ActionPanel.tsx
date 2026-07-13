import { Pressable, StyleSheet, Text, View } from 'react-native';

import { ActiveSession, Position } from '../domain/dashboard';
import { colors } from '../theme/colors';

type ActionPanelProps = {
  readOnly: boolean;
  selectedPosition: Position | null;
  session: ActiveSession;
  onMove: () => void;
  onScan: () => void;
};

export function ActionPanel({ readOnly, selectedPosition, session, onMove, onScan }: ActionPanelProps) {
  return (
    <View style={styles.panel}>
      <ActionButton icon="▥" label="ESCANEAR FOLIO" onPress={onScan} />
      <ActionButton icon="⇄" label="MOVER BULTO" onPress={onMove} />

      <View style={styles.selection}>
        <Text style={styles.sectionTitle}>Posición seleccionada</Text>
        <Text style={styles.selectionValue}>{selectedPosition?.label ?? 'Ninguna'}</Text>
        <Text style={styles.selectionDetail}>
          {selectedPosition?.folio ?? 'Selecciona una posición del mapa'}
        </Text>
      </View>

      <View style={styles.session}>
        <Text style={styles.sectionTitle}>{readOnly ? 'Modo consulta' : 'Sesión activa'}</Text>
        <View style={styles.operatorRow}>
          <View style={styles.avatar}><Text style={styles.avatarText}>●</Text></View>
          <View>
            <Text style={styles.muted}>Operador</Text>
            <Text style={styles.operator}>{session.operator}</Text>
          </View>
        </View>
        <View style={styles.divider} />
        <Text style={styles.muted}>Tiempo transcurrido</Text>
        <Text style={styles.elapsed}>{session.elapsed}</Text>
      </View>
    </View>
  );
}

function ActionButton({ icon, label, onPress }: { icon: string; label: string; onPress: () => void }) {
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [styles.actionButton, pressed && styles.actionPressed]}
    >
      <Text style={styles.actionIcon}>{icon}</Text>
      <Text style={styles.actionLabel}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  panel: {
    width: 246,
    gap: 12,
  },
  actionButton: {
    minHeight: 96,
    paddingHorizontal: 18,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.cyan,
    backgroundColor: colors.blue,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    shadowColor: colors.shadow,
    shadowOpacity: 0.22,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 7 },
  },
  actionPressed: {
    opacity: 0.78,
    transform: [{ scale: 0.99 }],
  },
  actionIcon: {
    color: colors.text,
    fontSize: 43,
    fontWeight: '300',
  },
  actionLabel: {
    flex: 1,
    color: colors.text,
    fontSize: 18,
    fontWeight: '900',
  },
  selection: {
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
  },
  selectionValue: {
    marginTop: 6,
    color: colors.text,
    fontSize: 22,
    fontWeight: '800',
  },
  selectionDetail: {
    marginTop: 3,
    color: colors.muted,
    fontSize: 12,
  },
  session: {
    padding: 16,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
  },
  sectionTitle: {
    color: colors.cyan,
    fontSize: 14,
    fontWeight: '800',
  },
  operatorRow: {
    marginTop: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 11,
  },
  avatar: {
    width: 43,
    height: 43,
    borderRadius: 22,
    borderWidth: 1,
    borderColor: colors.cyan,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.selected,
  },
  avatarText: {
    color: colors.cyan,
    fontSize: 25,
  },
  muted: {
    color: colors.muted,
    fontSize: 11,
  },
  operator: {
    color: colors.text,
    fontSize: 17,
    fontWeight: '800',
  },
  divider: {
    height: 1,
    marginVertical: 12,
    backgroundColor: colors.border,
  },
  elapsed: {
    marginTop: 2,
    color: colors.cyan,
    fontSize: 23,
    fontWeight: '800',
  },
});
