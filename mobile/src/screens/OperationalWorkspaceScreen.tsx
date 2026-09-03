import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { AuthSession } from '../domain/estiba';
import { EstibaApi } from '../services/estibaApi';
import { colors } from '../theme/colors';
import { OperationalTaskInbox } from '../components/OperationalTaskInbox';
import { OperationalScreen } from './OperationalScreen';

type Props = {
  api: EstibaApi;
  auth: AuthSession;
  onLogout: () => void;
};

type WorkspaceView = 'labores' | 'operacion';

export function OperationalWorkspaceScreen({ api, auth, onLogout }: Props) {
  const [view, setView] = useState<WorkspaceView>(api.mode === 'connected' ? 'labores' : 'operacion');

  async function logoutFromTasks() {
    try {
      await api.logout(auth.token);
    } catch {
      // La sesión local se cierra aunque el servidor no responda.
    } finally {
      onLogout();
    }
  }

  return (
    <View style={styles.screen}>
      <View style={styles.switcher}>
        <View style={styles.switcherCopy}>
          <Text style={styles.eyebrow}>FRIGORÍFICO · CAMARERO</Text>
          <Text style={styles.switcherTitle}>
            {view === 'labores' ? 'Trabajo guiado' : 'Plano y operación actual'}
          </Text>
        </View>
        <View style={styles.buttons}>
          <Pressable
            onPress={() => setView('labores')}
            style={[styles.button, view === 'labores' && styles.buttonActive]}
          >
            <Text style={[styles.buttonText, view === 'labores' && styles.buttonTextActive]}>Labores</Text>
          </Pressable>
          <Pressable
            onPress={() => setView('operacion')}
            style={[styles.button, view === 'operacion' && styles.buttonActive]}
          >
            <Text style={[styles.buttonText, view === 'operacion' && styles.buttonTextActive]}>Plano y operación</Text>
          </Pressable>
          {view === 'labores' ? (
            <Pressable onPress={() => void logoutFromTasks()} style={styles.logout}>
              <Text style={styles.logoutText}>Salir</Text>
            </Pressable>
          ) : null}
        </View>
      </View>

      <View style={styles.content}>
        {view === 'labores' ? (
          <OperationalTaskInbox api={api} auth={auth} />
        ) : (
          <OperationalScreen api={api} auth={auth} onLogout={onLogout} />
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  switcher: {
    minHeight: 54,
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    backgroundColor: colors.backgroundDeep,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  switcherCopy: { flexShrink: 1 },
  eyebrow: { color: colors.cyan, fontSize: 7, fontWeight: '900', letterSpacing: 1.2 },
  switcherTitle: { color: colors.text, fontSize: 13, fontWeight: '900', marginTop: 2 },
  buttons: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  button: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
  },
  buttonActive: { borderColor: colors.cyanDark, backgroundColor: colors.selected },
  buttonText: { color: colors.muted, fontSize: 9, fontWeight: '900' },
  buttonTextActive: { color: colors.cyan },
  logout: { paddingHorizontal: 11, paddingVertical: 7, borderRadius: 8, borderWidth: 1, borderColor: colors.red },
  logoutText: { color: colors.red, fontSize: 9, fontWeight: '900' },
  content: { flex: 1, minHeight: 0 },
});
