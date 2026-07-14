import { StatusBar } from 'expo-status-bar';
import { useMemo, useState } from 'react';
import { StyleSheet } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';

import { AuthSession, LoginPayload } from './src/domain/estiba';
import { LoginScreen } from './src/screens/LoginScreen';
import { OperationalScreen } from './src/screens/OperationalScreen';
import { createEstibaApi } from './src/services/estibaApi';
import { colors } from './src/theme/colors';

export default function App() {
  const api = useMemo(() => createEstibaApi(), []);
  const [auth, setAuth] = useState<AuthSession | null>(null);

  async function login(payload: LoginPayload) {
    setAuth(await api.login(payload));
  }

  return (
    <SafeAreaProvider>
      <SafeAreaView edges={['top', 'right', 'bottom', 'left']} style={styles.app}>
        <StatusBar style="light" />
        {auth ? (
          <OperationalScreen api={api} auth={auth} onLogout={() => setAuth(null)} />
        ) : (
          <LoginScreen
            baseUrl={api.baseUrl}
            configurationError={api.configurationError}
            mode={api.mode}
            onLogin={login}
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
});
