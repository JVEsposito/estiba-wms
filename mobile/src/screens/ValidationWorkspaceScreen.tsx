import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { AuthSession } from '../domain/estiba';
import { colors } from '../theme/colors';
import { RepalletizingScreen } from './RepalletizingScreen';
import { ValidationScreen } from './ValidationScreen';

type Props = {
  auth: AuthSession;
  baseUrl: string | null;
  onLogout: () => void;
};

type Section = 'validacion' | 'repaletizaje';

export function ValidationWorkspaceScreen({ auth, baseUrl, onLogout }: Props) {
  const [section, setSection] = useState<Section>('validacion');

  return (
    <View style={styles.screen}>
      <View style={styles.tabs}>
        <Pressable
          onPress={() => setSection('validacion')}
          style={[styles.tab, section === 'validacion' && styles.tabActive]}
        >
          <Text style={[styles.tabText, section === 'validacion' && styles.tabTextActive]}>
            Validar folio
          </Text>
        </Pressable>
        <Pressable
          disabled={!baseUrl}
          onPress={() => setSection('repaletizaje')}
          style={[
            styles.tab,
            section === 'repaletizaje' && styles.tabActive,
            !baseUrl && styles.tabDisabled,
          ]}
        >
          <Text style={[styles.tabText, section === 'repaletizaje' && styles.tabTextActive]}>
            Repaletizajes
          </Text>
        </Pressable>
      </View>

      <View style={styles.content}>
        {section === 'repaletizaje' && baseUrl ? (
          <RepalletizingScreen auth={auth} baseUrl={baseUrl} onLogout={onLogout} />
        ) : (
          <ValidationScreen auth={auth} baseUrl={baseUrl} onLogout={onLogout} />
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.backgroundDeep },
  tabs: {
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 12,
    paddingTop: 10,
    paddingBottom: 8,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    backgroundColor: colors.backgroundDeep,
  },
  tab: {
    flex: 1,
    minHeight: 42,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    backgroundColor: colors.panel,
  },
  tabActive: { borderColor: colors.cyan, backgroundColor: colors.cyanDark },
  tabDisabled: { opacity: 0.45 },
  tabText: { color: colors.muted, fontWeight: '900' },
  tabTextActive: { color: colors.text },
  content: { flex: 1 },
});
