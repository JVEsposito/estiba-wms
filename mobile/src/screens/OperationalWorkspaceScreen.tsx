import { useCallback, useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { AuthSession } from '../domain/estiba';
import { EstibaApi } from '../services/estibaApi';
import { getEnvironmentalControlState } from '../services/environmentalControlApi';
import { OperatorHeader } from '../components/operator/OperatorHeader';
import { operatorTheme as o } from '../theme/operatorTheme';
import { OperationalTaskInbox } from '../components/OperationalTaskInbox';
import { EnvironmentalControlScreen } from './EnvironmentalControlScreen';
import { OperationalScreen } from './OperationalScreen';

type Props = {
  api: EstibaApi;
  auth: AuthSession;
  onLogout: () => void;
};

type WorkspaceView = 'labores' | 'ambiente' | 'operacion';

export function OperationalWorkspaceScreen({ api, auth, onLogout }: Props) {
  const [view, setView] = useState<WorkspaceView>(api.mode === 'connected' ? 'labores' : 'operacion');
  const [environmentalDue, setEnvironmentalDue] = useState(0);
  const environmentalAvailable = api.mode === 'connected'
    && auth.usuario.capacidades.puede_consultar_control_ambiental === true;

  const updateEnvironmentalDue = useCallback((due: number) => setEnvironmentalDue(due), []);

  useEffect(() => {
    if (!environmentalAvailable || !api.baseUrl || view === 'ambiente') return;
    let active = true;
    const check = async () => {
      try {
        const response = await getEnvironmentalControlState(api.baseUrl!, auth.token);
        if (active) setEnvironmentalDue(response.camaras.filter((camera) => camera.requiere_control).length);
      } catch {
        // La pantalla Ambiente mostrará el detalle si el servidor no responde.
      }
    };
    void check();
    const timer = setInterval(() => void check(), 60_000);
    return () => { active = false; clearInterval(timer); };
  }, [api.baseUrl, auth.token, environmentalAvailable, view]);

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
      <OperatorHeader
        connected={api.mode === 'connected'}
        deviceName={auth.dispositivo.nombre}
        modeLabel={api.mode === 'connected' ? 'Modo conectado' : api.mode === 'demo' ? 'Modo demostración' : 'Sin configurar'}
        role={auth.usuario.rol}
        userName={auth.usuario.nombre}
      />
      <View style={styles.navigation}>
        <View style={styles.switcherCopy}>
          <Text style={styles.eyebrow}>FRIGORÍFICO · CAMARERO</Text>
          <Text style={styles.switcherTitle}>
            {view === 'labores'
              ? 'Trabajo guiado'
              : view === 'ambiente'
                ? 'Control ambiental horario'
                : 'Plano y operación actual'}
          </Text>
        </View>
        <View style={styles.buttons}>
          <Pressable
            accessibilityRole="tab"
            accessibilityState={{ selected: view === 'labores' }}
            onPress={() => setView('labores')}
            style={[styles.button, view === 'labores' && styles.buttonActive]}
          >
            <Text style={[styles.buttonText, view === 'labores' && styles.buttonTextActive]}>Labores</Text>
          </Pressable>
          {environmentalAvailable ? <Pressable
            accessibilityRole="tab"
            accessibilityState={{ selected: view === 'ambiente' }}
            onPress={() => setView('ambiente')}
            style={[styles.button, view === 'ambiente' && styles.buttonActive]}
          >
            <Text style={[styles.buttonText, view === 'ambiente' && styles.buttonTextActive]}>
              Ambiente{environmentalDue > 0 ? ` · ${environmentalDue}` : ''}
            </Text>
          </Pressable> : null}
          <Pressable
            accessibilityRole="tab"
            accessibilityState={{ selected: view === 'operacion' }}
            onPress={() => setView('operacion')}
            style={[styles.button, view === 'operacion' && styles.buttonActive]}
          >
            <Text style={[styles.buttonText, view === 'operacion' && styles.buttonTextActive]}>Plano y operación</Text>
          </Pressable>
          {view !== 'operacion' ? (
            <Pressable onPress={() => void logoutFromTasks()} style={styles.logout}>
              <Text style={styles.logoutText}>Salir</Text>
            </Pressable>
          ) : null}
        </View>
      </View>

      {environmentalAvailable && environmentalDue > 0 && view !== 'ambiente' ? (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`${environmentalDue} cámaras requieren control ambiental. Revisar ahora.`}
          onPress={() => setView('ambiente')}
          style={styles.environmentalPrompt}
        >
          <Text style={styles.environmentalPromptTitle}>{environmentalDue} {environmentalDue === 1 ? 'cámara requiere' : 'cámaras requieren'} control ambiental</Text>
          <Text style={styles.environmentalPromptAction}>Revisar ahora →</Text>
        </Pressable>
      ) : null}

      <View style={styles.content}>
        {view === 'labores' ? (
          <OperationalTaskInbox api={api} auth={auth} />
        ) : view === 'ambiente' && api.baseUrl ? (
          <EnvironmentalControlScreen auth={auth} baseUrl={api.baseUrl} onDueChange={updateEnvironmentalDue} />
        ) : (
          <OperationalScreen api={api} auth={auth} onLogout={onLogout} />
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: o.color.canvas },
  navigation: {
    minHeight: 76,
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: o.color.border,
    backgroundColor: o.color.surface,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 16,
    flexWrap: 'wrap',
  },
  switcherCopy: { flexShrink: 1 },
  eyebrow: { color: o.color.muted, fontSize: o.type.caption, fontWeight: '800', letterSpacing: 1.2 },
  switcherTitle: { color: o.color.text, fontSize: o.type.heading, fontWeight: '800', marginTop: 2 },
  buttons: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  button: {
    minHeight: o.touch.minimum,
    justifyContent: 'center',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: o.radius.control,
    borderWidth: 1,
    borderColor: o.color.borderStrong,
    backgroundColor: o.color.surface,
  },
  buttonActive: { borderColor: o.color.primary, backgroundColor: o.color.selected, borderLeftWidth: 4 },
  buttonText: { color: o.color.muted, fontSize: o.type.small, fontWeight: '700' },
  buttonTextActive: { color: o.color.primaryPressed },
  logout: { minHeight: o.touch.minimum, justifyContent: 'center', paddingHorizontal: 16, paddingVertical: 10, borderRadius: o.radius.control, borderWidth: 1, borderColor: o.color.critical },
  logoutText: { color: o.color.critical, fontSize: o.type.small, fontWeight: '800' },
  environmentalPrompt: {
    minHeight: 56,
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: o.color.critical,
    borderLeftWidth: 6,
    borderLeftColor: o.color.critical,
    backgroundColor: o.color.criticalSurface,
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  environmentalPromptTitle: { color: o.color.critical, fontSize: o.type.body, fontWeight: '800' },
  environmentalPromptAction: { color: o.color.critical, fontSize: o.type.body, fontWeight: '800' },
  content: { flex: 1, minHeight: 0 },
});
