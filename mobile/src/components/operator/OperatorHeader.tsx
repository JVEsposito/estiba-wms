import { StyleSheet, Text, View, useWindowDimensions } from 'react-native';

import { operatorTheme as o } from '../../theme/operatorTheme';

type Props = {
  connected: boolean;
  deviceName: string;
  modeLabel: string;
  role: string;
  userName: string;
};

export function OperatorHeader({ connected, deviceName, modeLabel, role, userName }: Props) {
  const { width } = useWindowDimensions();
  const compact = width < o.breakpoint.compact;

  return (
    <View style={[styles.header, compact && styles.headerCompact]}>
      <View style={styles.brandBlock}>
        <Text accessibilityRole="header" style={styles.brand}>ESTIBA</Text>
        <View style={styles.divider} />
        <View style={styles.productCopy}>
          <Text style={styles.product}>OPERACIÓN FRIGORÍFICO</Text>
          <Text style={styles.device}>{deviceName}</Text>
        </View>
      </View>

      <View style={[styles.contextBlock, compact && styles.contextBlockCompact]}>
        <View accessibilityLabel={`Estado de conexión: ${modeLabel}`} style={styles.connection}>
          <View style={[styles.connectionDot, !connected && styles.connectionDotInactive]} />
          <Text style={styles.connectionText}>{modeLabel}</Text>
        </View>
        <View style={styles.contextDivider} />
        <View style={styles.identity}>
          <Text numberOfLines={1} style={styles.userName}>{userName}</Text>
          <Text numberOfLines={1} style={styles.role}>{humanRole(role)}</Text>
        </View>
      </View>
    </View>
  );
}

function humanRole(role: string) {
  const normalized = role.trim().replaceAll('_', ' ');
  return normalized ? normalized.replace(/^./, (letter) => letter.toUpperCase()) : 'Camarero';
}

const styles = StyleSheet.create({
  header: {
    minHeight: 78,
    paddingHorizontal: o.space[4],
    paddingVertical: o.space[3],
    backgroundColor: o.color.navy,
    borderBottomColor: o.color.borderStrong,
    borderBottomWidth: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: o.space[4],
  },
  headerCompact: {
    alignItems: 'stretch',
    flexDirection: 'column',
  },
  brandBlock: {
    minWidth: 0,
    flexDirection: 'row',
    alignItems: 'center',
    gap: o.space[3],
  },
  brand: {
    color: o.color.onNavy,
    fontSize: 28,
    fontWeight: '900',
    letterSpacing: 0.4,
  },
  divider: {
    width: 1,
    alignSelf: 'stretch',
    backgroundColor: '#78909C',
  },
  productCopy: { minWidth: 0 },
  product: {
    color: o.color.onNavy,
    fontSize: o.type.small,
    fontWeight: '800',
    letterSpacing: 0.4,
  },
  device: {
    color: '#C9D8DF',
    fontSize: o.type.caption,
    marginTop: 2,
  },
  contextBlock: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    gap: o.space[4],
    minWidth: 0,
  },
  contextBlockCompact: { justifyContent: 'space-between' },
  connection: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    gap: o.space[2],
  },
  connectionDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: '#38D47B',
    borderColor: '#A7F0C5',
    borderWidth: 1,
  },
  connectionDotInactive: {
    backgroundColor: '#F2B84B',
    borderColor: '#FFE0A1',
  },
  connectionText: {
    color: '#D8F6E5',
    fontSize: o.type.small,
    fontWeight: '800',
  },
  contextDivider: {
    width: 1,
    height: 42,
    backgroundColor: '#526E7B',
  },
  identity: { minWidth: 0, maxWidth: 220 },
  userName: {
    color: o.color.onNavy,
    fontSize: o.type.small,
    fontWeight: '800',
    textAlign: 'right',
  },
  role: {
    color: '#C9D8DF',
    fontSize: o.type.caption,
    marginTop: 2,
    textAlign: 'right',
  },
});
