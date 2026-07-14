import { StatusBar } from 'expo-status-bar';
import { useMemo, useState } from 'react';
import { SafeAreaView, StyleSheet } from 'react-native';

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
    <SafeAreaView style={styles.app}>
      <StatusBar style="light" />
      {auth ? (
        <OperationalScreen api={api} auth={auth} onLogout={() => setAuth(null)} />
      ) : (
        <LoginScreen mode={api.mode} baseUrl={api.baseUrl} onLogin={login} />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  app: {
    flex: 1,
    backgroundColor: colors.background,
  },
});
