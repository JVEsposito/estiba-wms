import { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
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
};

export function LoginScreen({ baseUrl, configurationError, mode, onLogin }: LoginScreenProps) {
  const unconfigured = mode === 'unconfigured';
  const [email, setEmail] = useState(mode === 'demo' ? 'operador@estiba.local' : '');
  const [password, setPassword] = useState(mode === 'demo' ? 'password' : '');
  const [deviceCode, setDeviceCode] = useState(mode === 'demo' ? 'TABLET-01' : '');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

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
            <Text style={styles.modeText}>
              {mode === 'demo'
                ? 'DEMOSTRACIÓN LOCAL'
                : unconfigured ? 'API NO CONFIGURADA' : 'API · ' + baseUrl}
            </Text>
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
            {busy ? <ActivityIndicator color="#032022" /> : (
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
                ? 'Configura mobile/.env y reinicia Expo con npm run start:clear.'
                : 'Modo conectado: las operaciones confirmadas se guardan mediante Laravel en MySQL.'}
          </Text>
        </View>
      </ScrollView>
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
        placeholderTextColor="#627C88"
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
  modeChip: {
    alignSelf: 'flex-start',
    marginBottom: 17,
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
  submitText: { color: '#032022', fontSize: 12, fontWeight: '900' },
  submitArrow: { color: '#032022', fontSize: 21, fontWeight: '900' },
  help: { marginTop: 12, color: colors.muted, fontSize: 8, lineHeight: 12, textAlign: 'center' },
});
