import { useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';

import { colors } from '../theme/colors';

type Props = {
  userName: string;
  onChangePassword: (currentPassword: string, newPassword: string) => Promise<void>;
  onLogout: () => void;
};

export function FirstPasswordChangeScreen({ userName, onChangePassword, onLogout }: Props) {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit() {
    setError('');
    if (newPassword.length < 10 || !/\p{L}/u.test(newPassword) || !/\p{N}/u.test(newPassword)) {
      setError('Usa al menos 10 caracteres, con una letra y un número.');
      return;
    }
    if (newPassword !== confirmation) {
      setError('La confirmación no coincide con la contraseña nueva.');
      return;
    }
    if (newPassword === currentPassword) {
      setError('Elige una contraseña distinta de la temporal.');
      return;
    }

    setBusy(true);
    try {
      await onChangePassword(currentPassword, newPassword);
      setCurrentPassword('');
      setNewPassword('');
      setConfirmation('');
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'No se pudo cambiar la contraseña. Intenta nuevamente.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <ScrollView contentContainerStyle={styles.page} keyboardShouldPersistTaps="handled">
      <View style={styles.panel}>
        <Text style={styles.eyebrow}>ESTIBA WMS · PRIMER ACCESO</Text>
        <Text style={styles.title}>Crea tu contraseña</Text>
        <Text style={styles.description}>
          {userName}, tu contraseña actual es temporal. Cámbiala para comenzar a trabajar en la tablet.
        </Text>
        <Text style={styles.label}>Contraseña temporal</Text>
        <TextInput
          accessibilityLabel="Contraseña temporal"
          autoCapitalize="none"
          autoCorrect={false}
          secureTextEntry
          value={currentPassword}
          onChangeText={setCurrentPassword}
          style={styles.input}
        />
        <Text style={styles.label}>Contraseña nueva</Text>
        <TextInput
          accessibilityLabel="Contraseña nueva"
          autoCapitalize="none"
          autoCorrect={false}
          secureTextEntry
          value={newPassword}
          onChangeText={setNewPassword}
          style={styles.input}
        />
        <Text style={styles.hint}>Al menos 10 caracteres, una letra y un número.</Text>
        <Text style={styles.label}>Confirma la contraseña nueva</Text>
        <TextInput
          accessibilityLabel="Confirma la contraseña nueva"
          autoCapitalize="none"
          autoCorrect={false}
          secureTextEntry
          value={confirmation}
          onChangeText={setConfirmation}
          style={styles.input}
        />
        {error ? <Text accessibilityRole="alert" style={styles.error}>{error}</Text> : null}
        <Pressable accessibilityRole="button" disabled={busy} onPress={() => void submit()} style={[styles.button, busy && styles.disabled]}>
          {busy ? <ActivityIndicator color={colors.accentText} /> : <Text style={styles.buttonText}>Guardar contraseña y continuar</Text>}
        </Pressable>
        <Pressable accessibilityRole="button" disabled={busy} onPress={onLogout} style={styles.logout}>
          <Text style={styles.logoutText}>Cerrar sesión</Text>
        </Pressable>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  page: { flexGrow: 1, justifyContent: 'center', alignItems: 'center', padding: 24, backgroundColor: colors.background },
  panel: { width: '100%', maxWidth: 520, padding: 28, borderRadius: 18, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  eyebrow: { color: colors.cyan, fontSize: 12, fontWeight: '800', letterSpacing: 1 },
  title: { color: colors.text, fontSize: 28, fontWeight: '900', marginTop: 12 },
  description: { color: colors.muted, fontSize: 15, lineHeight: 23, marginTop: 12, marginBottom: 14 },
  label: { color: colors.text, fontSize: 14, fontWeight: '700', marginTop: 16, marginBottom: 7 },
  input: { color: colors.text, backgroundColor: colors.backgroundDeep, borderColor: colors.border, borderWidth: 1, borderRadius: 9, paddingHorizontal: 14, minHeight: 49, fontSize: 16 },
  hint: { color: colors.muted, fontSize: 12, marginTop: 6 },
  error: { color: colors.red, marginTop: 18, fontSize: 14 },
  button: { backgroundColor: colors.cyan, borderRadius: 9, alignItems: 'center', justifyContent: 'center', minHeight: 52, marginTop: 25, paddingHorizontal: 12 },
  buttonText: { color: colors.accentText, fontWeight: '900', fontSize: 15 },
  disabled: { opacity: 0.6 },
  logout: { alignItems: 'center', padding: 14, marginTop: 8 },
  logoutText: { color: colors.muted, fontWeight: '700' },
});
