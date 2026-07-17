import AsyncStorage from '@react-native-async-storage/async-storage';

import {
  RegisterValidationPayload,
  ValidationCatalog,
  ValidationOutboxItem,
  ValidationOutboxStatus,
} from '../domain/validation';

function catalogKey(userId: string, deviceId: string) {
  return `estiba_validation_catalog:${userId}:${deviceId}`;
}

function outboxKey(userId: string, deviceId: string) {
  return `estiba_validation_outbox:${userId}:${deviceId}`;
}

export async function loadCachedValidationCatalog(
  userId: string,
  deviceId: string,
): Promise<ValidationCatalog | null> {
  const raw = await AsyncStorage.getItem(catalogKey(userId, deviceId));
  if (!raw) return null;

  try {
    return JSON.parse(raw) as ValidationCatalog;
  } catch {
    await AsyncStorage.removeItem(catalogKey(userId, deviceId));
    return null;
  }
}

export async function saveValidationCatalog(
  userId: string,
  deviceId: string,
  catalog: ValidationCatalog,
) {
  await AsyncStorage.setItem(catalogKey(userId, deviceId), JSON.stringify(catalog));
}

export async function loadValidationOutbox(
  userId: string,
  deviceId: string,
): Promise<ValidationOutboxItem[]> {
  const raw = await AsyncStorage.getItem(outboxKey(userId, deviceId));
  if (!raw) return [];

  try {
    const parsed = JSON.parse(raw) as ValidationOutboxItem[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    await AsyncStorage.removeItem(outboxKey(userId, deviceId));
    return [];
  }
}

async function saveOutbox(
  userId: string,
  deviceId: string,
  items: ValidationOutboxItem[],
) {
  await AsyncStorage.setItem(outboxKey(userId, deviceId), JSON.stringify(items));
}

export async function enqueueValidation(
  userId: string,
  deviceId: string,
  payload: RegisterValidationPayload,
): Promise<ValidationOutboxItem[]> {
  const items = await loadValidationOutbox(userId, deviceId);
  if (items.some((item) => item.id === payload.operacion_id)) return items;

  items.push({
    id: payload.operacion_id,
    payload,
    status: 'pendiente',
    attempts: 0,
    created_at: new Date().toISOString(),
    last_attempt_at: null,
    message: null,
  });
  await saveOutbox(userId, deviceId, items);
  return items;
}

export async function removeValidationFromOutbox(
  userId: string,
  deviceId: string,
  operationId: string,
): Promise<ValidationOutboxItem[]> {
  const items = (await loadValidationOutbox(userId, deviceId))
    .filter((item) => item.id !== operationId);
  await saveOutbox(userId, deviceId, items);
  return items;
}

export async function markValidationOutboxItem(
  userId: string,
  deviceId: string,
  operationId: string,
  status: ValidationOutboxStatus,
  message: string | null,
): Promise<ValidationOutboxItem[]> {
  const items = await loadValidationOutbox(userId, deviceId);
  const updated = items.map((item) => item.id === operationId
    ? {
      ...item,
      status,
      attempts: item.attempts + 1,
      last_attempt_at: new Date().toISOString(),
      message,
    }
    : item);
  await saveOutbox(userId, deviceId, updated);
  return updated;
}

export async function retryValidationOutboxItem(
  userId: string,
  deviceId: string,
  operationId: string,
): Promise<ValidationOutboxItem[]> {
  const items = await loadValidationOutbox(userId, deviceId);
  const updated = items.map((item) => item.id === operationId
    ? { ...item, status: 'pendiente' as const, message: null }
    : item);
  await saveOutbox(userId, deviceId, updated);
  return updated;
}
