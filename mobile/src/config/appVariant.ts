import * as Updates from 'expo-updates';

function enabled(value: string | undefined): boolean {
  return value?.trim().toLowerCase() === 'true';
}

export const isDemoOnlyBuild = enabled(process.env.EXPO_PUBLIC_DEMO_ONLY);
export const isDemoRuntime = isDemoOnlyBuild || enabled(process.env.EXPO_PUBLIC_DEMO_MODE);

/**
 * Variante del APK. Se fija al compilar o publicar el bundle:
 * - `tablet`: operación de cámaras, Prefrío, Materiales y validaciones.
 * - `pda`: equipo de mano (Unitech EA520) solo para Validación PT y MP.
 *
 * Cada variante tiene su propio canal EAS (`production` y `pda`), de modo que
 * una actualización publicada para la tablet nunca llega a la PDA.
 */
export type AppVariant = 'tablet' | 'pda';

// El canal viene del binario instalado, no del bundle, y cuando existe manda:
// un bundle publicado por error en el canal equivocado no convierte una tablet
// en PDA ni al revés. Sin canal (Expo Go, `expo start`, exportación web) se usa
// la variable de entorno.
function nativeChannel(): string | null {
  try {
    return Updates.channel ?? null;
  } catch {
    return null;
  }
}

function resolveVariant(): AppVariant {
  const channel = nativeChannel();
  if (channel) return channel === 'pda' ? 'pda' : 'tablet';
  return process.env.EXPO_PUBLIC_APP_VARIANT?.trim().toLowerCase() === 'pda' ? 'pda' : 'tablet';
}

export const appVariant: AppVariant = resolveVariant();
export const isPdaBuild = appVariant === 'pda';

/** Módulos que la PDA puede abrir; el resto del perfil se ignora en ese equipo. */
export const PDA_MODULES = ['validacion', 'validacion_mp'] as const;

export const deviceNoun = isPdaBuild ? 'PDA' : 'tablet';
