import { StatusBar } from 'expo-status-bar';
import * as ScreenOrientation from 'expo-screen-orientation';
import { useEffect, useMemo, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';

import { AuthSession, LoginPayload } from './src/domain/estiba';
import { LoginScreen } from './src/screens/LoginScreen';
import { OperationalScreen } from './src/screens/OperationalScreen';
import { PrefrioScreen } from './src/screens/PrefrioScreen';
import { ValidationScreen } from './src/screens/ValidationScreen';
import { ValidationMpScreen } from './src/screens/ValidationMpScreen';
import { loadApiBaseUrl, saveApiBaseUrl } from './src/services/apiConfiguration';
import { applyAvailableUpdate } from './src/services/appUpdates';
import { createEstibaApi } from './src/services/estibaApi';
import { colors } from './src/theme/colors';

type MobileModule = 'operacion' | 'validacion' | 'validacion_mp' | 'prefrio';

export default function App() {
  const [baseUrl, setBaseUrl] = useState<string | null>(null);
  const [configurationLoaded, setConfigurationLoaded] = useState(false);
  const [auth, setAuth] = useState<AuthSession | null>(null);
  const [activeModule, setActiveModule] = useState<MobileModule | null>(null);
  const api = useMemo(() => createEstibaApi(baseUrl), [baseUrl]);

  useEffect(() => {
    void loadApiBaseUrl()
      .then(setBaseUrl)
      .catch(() => setBaseUrl(null))
      .finally(() => setConfigurationLoaded(true));
    void applyAvailableUpdate();
  }, []);

  useEffect(() => {
    const orientation = activeModule === 'validacion' || activeModule === 'validacion_mp'
      ? ScreenOrientation.OrientationLock.PORTRAIT_UP
      : activeModule
        ? ScreenOrientation.OrientationLock.LANDSCAPE
        : ScreenOrientation.OrientationLock.DEFAULT;

    void ScreenOrientation.lockAsync(orientation).catch(() => {
      // Algunos equipos administrados pueden impedir que la aplicación cambie
      // la orientación. La interfaz responsiva sigue siendo utilizable.
    });
  }, [activeModule]);

  async function login(payload: LoginPayload) {
    const session = await api.login(payload);
    setAuth(session);
    setActiveModule(defaultModule(session));
  }

  async function configureServer(value: string) {
    const configuredUrl = await saveApiBaseUrl(value);
    setAuth(null);
    setActiveModule(null);
    setBaseUrl(configuredUrl);
  }

  function clearSession() {
    setAuth(null);
    setActiveModule(null);
  }

  async function logoutPersistentModule() {
    if (auth) {
      try {
        await api.logout(auth.token);
      } catch {
        // Las bandejas locales permanecen guardadas aunque el servidor no responda.
      }
    }
    clearSession();
  }

  const modules = auth ? availableModules(auth) : [];

  return (
    <SafeAreaProvider>
      <SafeAreaView edges={['top', 'right', 'bottom', 'left']} style={styles.app}>
        <StatusBar style="light" />
        {!configurationLoaded ? (
          <View style={styles.boot}>
            <ActivityIndicator color={colors.cyan} size="large" />
            <Text style={styles.bootText}>Preparando Estiba WMS…</Text>
          </View>
        ) : auth ? (
          <View style={styles.workspace}>
            {modules.length > 1 && activeModule ? (
              <View style={styles.moduleStrip}>
                <Text style={styles.moduleStripText}>Módulo activo: {moduleLabel(activeModule)}</Text>
                <Pressable onPress={() => setActiveModule(null)} style={styles.changeModule}>
                  <Text style={styles.changeModuleText}>Cambiar módulo</Text>
                </Pressable>
              </View>
            ) : null}
            {!activeModule ? (
              <ModuleSelection
                modules={modules}
                onSelect={setActiveModule}
                userName={auth.usuario.nombre}
              />
            ) : activeModule === 'validacion' ? (
              <ValidationScreen
                auth={auth}
                baseUrl={api.baseUrl}
                onLogout={() => void logoutPersistentModule()}
              />
            ) : activeModule === 'prefrio' ? (
              <PrefrioScreen
                auth={auth}
                baseUrl={api.baseUrl}
                onLogout={() => void logoutPersistentModule()}
              />
            ) : activeModule === 'validacion_mp' ? (
              <ValidationMpScreen auth={auth} baseUrl={api.baseUrl ?? ''} onLogout={() => void logoutPersistentModule()} />
            ) : (
              <OperationalScreen api={api} auth={auth} onLogout={clearSession} />
            )}
          </View>
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

function availableModules(auth: AuthSession): MobileModule[] {
  const capabilities = auth.usuario.capacidades;
  const modules: MobileModule[] = [];
  const canOperate = capabilities.puede_operar_productos
    || capabilities.puede_operar_materiales
    || capabilities.puede_consultar_cargas
    || capabilities.puede_consultar_despachos_materiales;

  if (canOperate) modules.push('operacion');
  if (capabilities.puede_validar_pallets) modules.push('validacion');
  if (capabilities.puede_validar_mp) modules.push('validacion_mp');
  if (capabilities.puede_consultar_prefrio) modules.push('prefrio');

  return modules;
}

function defaultModule(auth: AuthSession): MobileModule | null {
  const modules = availableModules(auth);
  return modules.length === 1 ? modules[0] : null;
}

function moduleLabel(module: MobileModule) {
  return module === 'validacion'
    ? 'Validación'
    : module === 'validacion_mp'
      ? 'Validación MP'
    : module === 'prefrio'
      ? 'Prefrío'
      : 'Operación frigorífico';
}

function ModuleSelection({ modules, onSelect, userName }: { modules: MobileModule[]; onSelect: (module: MobileModule) => void; userName: string }) {
  return (
    <View style={styles.selector}>
      <Text style={styles.selectorEyebrow}>ESTIBA WMS · TURNO</Text>
      <Text style={styles.selectorTitle}>Selecciona el área de trabajo</Text>
      <Text style={styles.selectorCopy}>{userName}, tu perfil posee acceso a más de un módulo.</Text>
      <View style={styles.selectorCards}>
        {modules.includes('validacion') ? (
          <Pressable onPress={() => onSelect('validacion')} style={styles.selectorCard}>
            <Text style={styles.selectorIcon}>✓</Text>
            <Text style={styles.selectorCardTitle}>Validación</Text>
            <Text style={styles.selectorCardCopy}>Escanear pallets, aprobar, observar y sincronizar capturas.</Text>
          </Pressable>
        ) : null}
        {modules.includes('validacion_mp') ? (
          <Pressable onPress={() => onSelect('validacion_mp')} style={styles.selectorCard}>
            <Text style={styles.selectorIcon}>⌁</Text>
            <Text style={styles.selectorCardTitle}>Validación MP</Text>
            <Text style={styles.selectorCardCopy}>Recibir correlativos de Romana, contar envases y preparar segregaciones.</Text>
          </Pressable>
        ) : null}
        {modules.includes('prefrio') ? (
          <Pressable onPress={() => onSelect('prefrio')} style={styles.selectorCard}>
            <Text style={styles.selectorIcon}>◫</Text>
            <Text style={styles.selectorCardTitle}>Prefrío</Text>
            <Text style={styles.selectorCardCopy}>Cargar túneles, iniciar procesos, registrar eventos y enviar a verificación.</Text>
          </Pressable>
        ) : null}
        {modules.includes('operacion') ? (
          <Pressable onPress={() => onSelect('operacion')} style={styles.selectorCard}>
            <Text style={styles.selectorIcon}>❄</Text>
            <Text style={styles.selectorCardTitle}>Operación frigorífico</Text>
            <Text style={styles.selectorCardCopy}>Cámaras, materiales, cargas y despachos.</Text>
          </Pressable>
        ) : null}
      </View>
      {!modules.length ? <Text style={styles.noModule}>El perfil no posee un módulo móvil habilitado.</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  app: { flex: 1, backgroundColor: colors.background },
  workspace: { flex: 1 },
  boot: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 },
  bootText: { color: colors.muted, fontSize: 12, fontWeight: '800' },
  moduleStrip: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, paddingHorizontal: 18, paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.border, backgroundColor: colors.backgroundDeep },
  moduleStripText: { color: colors.muted, fontSize: 10, fontWeight: '900', textTransform: 'uppercase' },
  changeModule: { paddingHorizontal: 11, paddingVertical: 6, borderRadius: 8, borderWidth: 1, borderColor: colors.cyanDark },
  changeModuleText: { color: colors.cyan, fontSize: 10, fontWeight: '900' },
  selector: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: colors.background },
  selectorEyebrow: { color: colors.cyan, fontSize: 11, fontWeight: '900', letterSpacing: 1.4 },
  selectorTitle: { color: colors.text, fontSize: 28, fontWeight: '900', marginTop: 7, textAlign: 'center' },
  selectorCopy: { color: colors.muted, marginTop: 8, textAlign: 'center' },
  selectorCards: { width: '100%', maxWidth: 1120, flexDirection: 'row', gap: 16, marginTop: 28 },
  selectorCard: { flex: 1, minHeight: 220, justifyContent: 'center', padding: 24, borderRadius: 18, borderWidth: 1, borderColor: colors.cyanDark, backgroundColor: colors.panel },
  selectorIcon: { color: colors.cyan, fontSize: 34, fontWeight: '900' },
  selectorCardTitle: { color: colors.text, fontSize: 21, fontWeight: '900', marginTop: 14 },
  selectorCardCopy: { color: colors.muted, lineHeight: 20, marginTop: 7 },
  noModule: { color: colors.red, marginTop: 24, fontWeight: '800' },
});
