import { StatusBar } from 'expo-status-bar';
import { useEffect, useMemo, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';

import { AuthSession, LoginPayload } from './src/domain/estiba';
import { LoginScreen } from './src/screens/LoginScreen';
import { OperationalScreen } from './src/screens/OperationalScreen';
import { loadApiBaseUrl, saveApiBaseUrl } from './src/services/apiConfiguration';
import { applyAvailableUpdate } from './src/services/appUpdates';
import { createEstibaApi } from './src/services/estibaApi';
import { colors } from './src/theme/colors';

export default function App() {
  const [baseUrl, setBaseUrl] = useState<string | null>(null);
  const [configurationLoaded, setConfigurationLoaded] = useState(false);
  const [auth, setAuth] = useState<AuthSession | null>(null);
  const api = useMemo(() => createEstibaApi(baseUrl), [baseUrl]);

  useEffect(() => {
    void loadApiBaseUrl()
      .then(setBaseUrl)
      .catch(() => setBaseUrl(null))
      .finally(() => setConfigurationLoaded(true));
    void applyAvailableUpdate();
  }, []);

  async function login(payload: LoginPayload) {
    setAuth(await api.login(payload));
  }

  async function configureServer(value: string) {
    const configuredUrl = await saveApiBaseUrl(value);
    setAuth(null);
    setBaseUrl(configuredUrl);
  }

  return (
    <SafeAreaProvider>
      <SafeAreaView edges={['top', 'right', 'bottom', 'left']} style={styles.app}>
        <StatusBar style="light" />
        {!configurationLoaded ? (
          <View style={styles.boot}>
            <ActivityIndicator color={colors.cyan} size="large" />
            <Text style={styles.bootText}>Preparando operación de cámaras…</Text>
          </View>
        ) : auth ? (
          <OperationalScreen api={api} auth={auth} onLogout={() => setAuth(null)} />
        ) : (
          <LoginScreen
            baseUrl={api.baseUrl}
            configurationError={api.configurationError}
            mode={api.mode}
            onLogin={login}
            onSaveBaseUrl={configureServer}
          />
        )}
      </SafeAreaView>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  app: {
    flex: 1,
    backgroundColor: colors.background,
  },
  boot: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  bootText: { color: colors.muted, fontSize: 12, fontWeight: '800' },
});
