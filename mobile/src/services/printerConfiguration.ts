import AsyncStorage from '@react-native-async-storage/async-storage';

export type PrinterConfiguration = {
  name: string;
  host: string;
  port: number;
  profileId: string;
};

const key = (deviceId: string) => `estiba_wms_printer_${deviceId}`;

export async function loadPrinterConfiguration(
  deviceId: string,
): Promise<PrinterConfiguration | null> {
  const raw = await AsyncStorage.getItem(key(deviceId));
  if (!raw) return null;

  try {
    return validatePrinterConfiguration(JSON.parse(raw));
  } catch {
    return null;
  }
}

export async function savePrinterConfiguration(
  deviceId: string,
  configuration: PrinterConfiguration,
): Promise<PrinterConfiguration> {
  const validated = validatePrinterConfiguration(configuration);
  await AsyncStorage.setItem(key(deviceId), JSON.stringify(validated));
  return validated;
}

export function validatePrinterConfiguration(value: unknown): PrinterConfiguration {
  if (!value || typeof value !== 'object') {
    throw new Error('Completa la configuración de la impresora.');
  }
  const input = value as Partial<PrinterConfiguration>;
  const name = String(input.name || '').trim();
  const host = String(input.host || '').trim();
  const port = Number(input.port);
  const profileId = String(input.profileId || '').trim();

  if (name.length < 2 || name.length > 100) {
    throw new Error('El nombre de la impresora debe tener entre 2 y 100 caracteres.');
  }
  if (!isIpv4(host)) {
    throw new Error('Ingresa una IPv4 válida para la impresora.');
  }
  if (!Number.isInteger(port) || port < 1 || port > 65535) {
    throw new Error('El puerto debe estar entre 1 y 65535.');
  }
  if (!profileId) {
    throw new Error('Selecciona el perfil de etiqueta de la impresora.');
  }

  return { name, host, port, profileId };
}

function isIpv4(value: string): boolean {
  const parts = value.split('.');
  return parts.length === 4 && parts.every((part) => {
    if (!/^\d{1,3}$/.test(part)) return false;
    const number = Number(part);
    return number >= 0 && number <= 255 && String(number) === part;
  });
}
