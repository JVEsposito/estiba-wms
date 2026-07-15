import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { ApiMode, LoginPayload } from '../domain/estiba';
import { colors } from '../theme/colors';

type LoginScreenProps = {
  baseUrl: string | null;
  configurationError: string | null;
  mode: ApiMode;
  onLogin: (payload: LoginPayload) => Promise<void>;
  onSaveBaseUrl: (value: string) => Promise<void>;
};

export function LoginScreen({
  baseUrl,
  configurationError,
  mode,
  onLogin,
  onSaveBaseUrl,
}: LoginScreenProps) {
  const unconfigured = mode === 'unconfigured';
  const [email, setEmail] = useState(mode === 'demo' ? 'operador@estiba.local' : '');
  const [password, setPassword] = useState(mode === 'demo' ? 'password' : '');
  const [deviceCode, setDeviceCode] = useState(mode === 'demo' ? 'TABLET-01' : '');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [serverVisible, setServerVisible] = useState(false);
  const [serverUrl, setServerUrl] = useState(baseUrl ?? '');
  const [serverBusy, setServerBusy] = useState(false);
  const [serverError, setServerError] = useState('');

  useEffect(() => setServerUrl(baseUrl ?? ''), [baseUrl]);

  async function submit() {
    setBusy(true);
    setError('');
    try {
      await onLogin({
        email: email.trim().toLowerCase(),
        password,
        codigo_dispositivo: deviceCode.trim().toUpperCase(),
      });
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'No fue posible iniciar el turno.');
    } finally {
      setBusy(false);
    }
  }

  async function saveServer() {
    setServerBusy(true);
    setServerError('');
    try {
      await onSaveBaseUrl(serverUrl);
      setServerVisible(false);
      setError('');
    } catch (reason) {
      setServerError(reason instanceof Error ? reason.message : 'No fue posible guardar el servidor.');
    } finally {
      setServerBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      style={styles.keyboard}
    >
      <ScrollView contentContainerStyle={styles.page} keyboardShouldPersistTaps="handled">
        <View style={styles.brandPanel}>
          <View style={styles.brandMark}><Text style={styles.brandIcon}>❄</Text></View>
          <Text style={styles.eyebrow}>OPERACIÓN EN FRÍO</Text>
          <Text style={styles.brandTitle}>Estiba WMS</Text>
          <Text style={styles.brandCopy}>
            Ubicación, movimiento y trazabilidad de folios desde una interfaz diseñada para tablets.
          </Text>
          <View style={styles.features}>
            <Feature label="Plano actualizado" />
            <Feature label="Edición controlada" />
            <Feature label="Trazabilidad" />
          </View>
        </View>

        <View style={styles.formPanel}>
          <View style={styles.serverRow}>
            <View style={[
              styles.modeChip,
              mode === 'demo' && styles.demoChip,
              unconfigured && styles.unconfiguredChip,
            ]}>
              <View style={[
                styles.modeDot,
                mode === 'demo' && styles.demoDot,
                unconfigured && styles.unconfiguredDot,
              ]} />
              <Text numberOfLines={1} style={styles.modeText}>
                {mode === 'demo'
                  ? 'DEMOSTRACIÓN LOCAL'
                  : unconfigured ? 'API NO CONFIGURADA' : 'API · ' + baseUrl}
              </Text>
            </View>
            {mode !== 'demo' ? (
              <Pressable
                accessibilityRole="button"
                onPress={() => {
                  setServerError('');
                  setServerVisible(true);
                }}
                style={({ pressed }) => [styles.serverButton, pressed && styles.pressed]}
              >
                <Text style={styles.serverButtonText}>⚙ Configurar servidor</Text>
              </Pressable>
            ) : null}
          </View>
          <Text style={styles.formTitle}>Iniciar turno</Text>
          <Text style={styles.formIntro}>
            Usa tus credenciales y el código asignado a esta tablet.
          </Text>

          <Field
            autoCapitalize="none"
            keyboardType="email-address"
            label="Correo electrónico"
            onChangeText={setEmail}
            placeholder="operador@empresa.cl"
            value={email}
          />
          <Field
            autoCapitalize="none"
            label="Contraseña"
            onChangeText={setPassword}
            placeholder="••••••••"
            secureTextEntry
            value={password}
          />
          <Field
            autoCapitalize="characters"
            label="Código de tablet"
            onChangeText={setDeviceCode}
            placeholder="TABLET-01"
            value={deviceCode}
          />

          {configurationError ? (
            <View style={styles.configurationError}>
              <Text style={styles.configurationErrorText}>{configurationError}</Text>
            </View>
          ) : null}

          <Text style={styles.error}>{error}</Text>
          <Pressable
            accessibilityRole="button"
            disabled={busy || unconfigured}
            onPress={submit}
            style={({ pressed }) => [
              styles.submit,
              (busy || unconfigured) && styles.disabled,
              pressed && styles.pressed,
            ]}
          >
            {busy ? <ActivityIndicator color={colors.accentText} /> : (
              <>
                <Text style={styles.submitText}>Acceder al plano</Text>
                <Text style={styles.submitArrow}>→</Text>
              </>
            )}
          </Pressable>

          <Text style={styles.help}>
            {mode === 'demo'
              ? 'Este modo no necesita Laravel. Los cambios viven solo durante esta ejecución.'
              : unconfigured
                ? 'Configura la IP del equipo que ejecuta Laravel para habilitar el acceso.'
                : 'La dirección queda guardada en esta tablet y puede cambiarse sin reinstalar la APK.'}
          </Text>
        </View>
      </ScrollView>

      <Modal
        animationType="fade"
        onRequestClose={() => setServerVisible(false)}
        transparent
        visible={serverVisible}
      >
        <View style={styles.serverOverlay}>
          <KeyboardAvoidingView
            behavior={Platform.OS === 'ios' ? 'padding' : undefined}
            style={styles.serverDialog}
          >
            <View style={styles.serverHeading}>
              <View style={styles.serverHeadingCopy}>
                <Text style={styles.eyebrow}>CONEXIÓN DE LA TABLET</Text>
                <Text style={styles.serverTitle}>Servidor Laravel</Text>
                <Text style={styles.serverIntro}>
                  Ingresa la IP y el puerto. La configuración se conserva aunque cierres la aplicación.
                </Text>
              </View>
              <Pressable onPress={() => setServerVisible(false)} style={styles.serverClose}>
                <Text style={styles.serverCloseText}>×</Text>
              </Pressable>
            </View>

            <View style={styles.field}>
              <Text style={styles.label}>Dirección o IP del servidor</Text>
              <TextInput
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="url"
                onChangeText={setServerUrl}
                placeholder="10.16.104.25:8000"
                placeholderTextColor="#737D82"
                selectionColor={colors.cyan}
                style={styles.input}
                value={serverUrl}
              />
            </View>
            <Text style={styles.serverExample}>Ejemplos: 192.168.1.50:8000 · http://servidor-wms:8000 · https://wms.empresa.cl</Text>
            <Text style={styles.serverError}>{serverError}</Text>

            <View style={styles.serverActions}>
              <Pressable onPress={() => setServerVisible(false)} style={styles.cancelButton}>
                <Text style={styles.cancelButtonText}>Cancelar</Text>
              </Pressable>
              <Pressable
                disabled={serverBusy}
                onPress={() => void saveServer()}
                style={({ pressed }) => [
                  styles.saveServerButton,
                  serverBusy && styles.disabled,
                  pressed && styles.pressed,
                ]}
              >
                {serverBusy
                  ? <ActivityIndicator color={colors.accentText} />
                  : <Text style={styles.saveServerText}>Guardar servidor</Text>}
              </Pressable>
            </View>
          </KeyboardAvoidingView>
        </View>
      </Modal>
    </KeyboardAvoidingView>
  );
}

type FieldProps = {
  autoCapitalize: 'none' | 'characters';
  keyboardType?: 'email-address';
  label: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  secureTextEntry?: boolean;
  value: string;
};

function Field({ label, ...props }: FieldProps) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        {...props}
        placeholderTextColor="#737D82"
        selectionColor={colors.cyan}
        style={styles.input}
      />
    </View>
  );
}

function Feature({ label }: { label: string }) {
  return (
    <View style={styles.feature}>
      <View style={styles.featureDot} />
      <Text style={styles.featureText}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  keyboard: { flex: 1, backgroundColor: colors.backgroundDeep },
  page: { flexGrow: 1, flexDirection: 'row' },
  brandPanel: {
    flex: 1.05,
    minHeight: 520,
    padding: 52,
    justifyContent: 'center',
    backgroundColor: colors.panel,
  },
  brandMark: {
    width: 58,
    height: 58,
    marginBottom: 24,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.cyanDark,
    backgroundColor: colors.selected,
    alignItems: 'center',
    justifyContent: 'center',
  },
  brandIcon: { color: colors.cyan, fontSize: 34 },
  eyebrow: { color: colors.cyan, fontSize: 10, fontWeight: '900', letterSpacing: 1.8 },
  brandTitle: { marginTop: 10, color: colors.text, fontSize: 58, fontWeight: '900', lineHeight: 62 },
  brandCopy: { maxWidth: 430, marginTop: 22, color: colors.muted, fontSize: 15, lineHeight: 23 },
  features: { marginTop: 32, flexDirection: 'row', flexWrap: 'wrap', gap: 18 },
  feature: { flexDirection: 'row', alignItems: 'center', gap: 7 },
  featureDot: { width: 7, height: 7, borderRadius: 4, backgroundColor: colors.green },
  featureText: { color: colors.text, fontSize: 11, fontWeight: '700' },
  formPanel: {
    flex: 0.95,
    minHeight: 520,
    paddingHorizontal: 64,
    paddingVertical: 38,
    justifyContent: 'center',
    backgroundColor: colors.backgroundDeep,
  },
  serverRow: {
    marginBottom: 17,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  modeChip: {
    maxWidth: '62%',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.green,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  demoChip: { borderColor: colors.amber },
  unconfiguredChip: { borderColor: colors.red },
  modeDot: { width: 7, height: 7, borderRadius: 4, backgroundColor: colors.green },
  demoDot: { backgroundColor: colors.amber },
  unconfiguredDot: { backgroundColor: colors.red },
  modeText: { maxWidth: 360, color: colors.muted, fontSize: 8, fontWeight: '900' },
  serverButton: {
    minHeight: 38,
    paddingHorizontal: 12,
    borderRadius: 9,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
    alignItems: 'center',
    justifyContent: 'center',
  },
  serverButtonText: { color: colors.cyan, fontSize: 9, fontWeight: '900' },
  formTitle: { color: colors.text, fontSize: 30, fontWeight: '900' },
  formIntro: { marginTop: 7, marginBottom: 17, color: colors.muted, fontSize: 12 },
  field: { marginTop: 11, gap: 6 },
  label: { color: colors.text, fontSize: 10, fontWeight: '800' },
  input: {
    height: 48,
    paddingHorizontal: 13,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
    color: colors.text,
    fontSize: 13,
  },
  error: { minHeight: 17, marginTop: 8, color: colors.red, fontSize: 9 },
  configurationError: {
    marginTop: 12,
    padding: 10,
    borderRadius: 9,
    borderWidth: 1,
    borderColor: colors.red,
    backgroundColor: '#421B21',
  },
  configurationErrorText: { color: '#FFB7B7', fontSize: 9, lineHeight: 13 },
  submit: {
    height: 50,
    paddingHorizontal: 16,
    borderRadius: 11,
    backgroundColor: colors.cyan,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  disabled: { opacity: 0.5 },
  pressed: { opacity: 0.75 },
  submitText: { color: colors.accentText, fontSize: 12, fontWeight: '900' },
  submitArrow: { color: colors.accentText, fontSize: 21, fontWeight: '900' },
  help: { marginTop: 12, color: colors.muted, fontSize: 8, lineHeight: 12, textAlign: 'center' },
  serverOverlay: {
    flex: 1,
    padding: 24,
    backgroundColor: 'rgba(5,8,11,.82)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  serverDialog: {
    width: '100%',
    maxWidth: 620,
    padding: 22,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panelStrong,
  },
  serverHeading: { flexDirection: 'row', justifyContent: 'space-between', gap: 18 },
  serverHeadingCopy: { flex: 1 },
  serverTitle: { marginTop: 4, color: colors.text, fontSize: 24, fontWeight: '900' },
  serverIntro: { marginTop: 6, color: colors.muted, fontSize: 10, lineHeight: 15 },
  serverClose: {
    width: 38,
    height: 38,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  serverCloseText: { color: colors.text, fontSize: 24, lineHeight: 26 },
  serverExample: { marginTop: 8, color: colors.muted, fontSize: 8, lineHeight: 12 },
  serverError: { minHeight: 17, marginTop: 8, color: colors.red, fontSize: 9 },
  serverActions: { marginTop: 12, flexDirection: 'row', justifyContent: 'flex-end', gap: 10 },
  cancelButton: {
    minWidth: 110,
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cancelButtonText: { color: colors.text, fontSize: 10, fontWeight: '900' },
  saveServerButton: {
    minWidth: 170,
    height: 44,
    paddingHorizontal: 16,
    borderRadius: 10,
    backgroundColor: colors.cyan,
    alignItems: 'center',
    justifyContent: 'center',
  },
  saveServerText: { color: colors.accentText, fontSize: 10, fontWeight: '900' },
});
