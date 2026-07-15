import AsyncStorage from '@react-native-async-storage/async-storage';

const API_URL_STORAGE_KEY = 'estiba_wms_api_url';

export function normalizeApiBaseUrl(value: string): string {
  const trimmed = value.trim();
  if (!trimmed) throw new Error('Ingresa la IP o dirección del servidor.');

  const candidate = /^https?:\/\//i.test(trimmed) ? trimmed : `http://${trimmed}`;
  let parsed: URL;

  try {
    parsed = new URL(candidate);
  } catch {
    throw new Error('La dirección no es válida. Ejemplo: 10.16.104.25:8000');
  }

  if (!['http:', 'https:'].includes(parsed.protocol) || !parsed.hostname) {
    throw new Error('La dirección debe utilizar HTTP o HTTPS.');
  }

  if (parsed.username || parsed.password || parsed.search || parsed.hash) {
    throw new Error('La dirección no debe incluir credenciales, parámetros ni fragmentos.');
  }

  const path = parsed.pathname.replace(/\/+$/, '');
  return `${parsed.protocol}//${parsed.host}${path === '/' ? '' : path}`;
}

export async function loadApiBaseUrl(): Promise<string | null> {
  const stored = await AsyncStorage.getItem(API_URL_STORAGE_KEY);
  const fallback = process.env.EXPO_PUBLIC_API_URL?.trim() || null;
  const configured = stored || fallback;

  if (!configured) return null;

  try {
    return normalizeApiBaseUrl(configured);
  } catch {
    return null;
  }
}

export async function saveApiBaseUrl(value: string): Promise<string> {
  const normalized = normalizeApiBaseUrl(value);
  await AsyncStorage.setItem(API_URL_STORAGE_KEY, normalized);
  return normalized;
}
