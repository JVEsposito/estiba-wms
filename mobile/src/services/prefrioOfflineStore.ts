import AsyncStorage from '@react-native-async-storage/async-storage';

import {
  PrefrioMobileCache,
  PrefrioQueuedCommand,
} from '../domain/prefrio';

function cacheKey(userId: string, deviceId: string) {
  return `estiba_prefrio_cache:${userId}:${deviceId}`;
}

function outboxKey(userId: string, deviceId: string) {
  return `estiba_prefrio_outbox:${userId}:${deviceId}`;
}

export async function loadPrefrioCache(
  userId: string,
  deviceId: string,
): Promise<PrefrioMobileCache | null> {
  const raw = await AsyncStorage.getItem(cacheKey(userId, deviceId));
  if (!raw) return null;

  try {
    return JSON.parse(raw) as PrefrioMobileCache;
  } catch {
    await AsyncStorage.removeItem(cacheKey(userId, deviceId));
    return null;
  }
}

export async function savePrefrioCache(
  userId: string,
  deviceId: string,
  cache: PrefrioMobileCache,
) {
  await AsyncStorage.setItem(cacheKey(userId, deviceId), JSON.stringify(cache));
}

export async function loadPrefrioOutbox(
  userId: string,
  deviceId: string,
): Promise<PrefrioQueuedCommand[]> {
  const raw = await AsyncStorage.getItem(outboxKey(userId, deviceId));
  if (!raw) return [];

  try {
    const parsed = JSON.parse(raw) as PrefrioQueuedCommand[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    await AsyncStorage.removeItem(outboxKey(userId, deviceId));
    return [];
  }
}

async function saveOutbox(
  userId: string,
  deviceId: string,
  items: PrefrioQueuedCommand[],
) {
  await AsyncStorage.setItem(outboxKey(userId, deviceId), JSON.stringify(items));
}

export async function enqueuePrefrioCommand(
  userId: string,
  deviceId: string,
  command: PrefrioQueuedCommand,
): Promise<PrefrioQueuedCommand[]> {
  const items = await loadPrefrioOutbox(userId, deviceId);
  if (items.some((item) => item.id === command.id)) return items;

  const updated = [...items, command].sort((left, right) => (
    left.created_at.localeCompare(right.created_at)
  ));
  await saveOutbox(userId, deviceId, updated);
  return updated;
}

export async function removePrefrioCommand(
  userId: string,
  deviceId: string,
  operationId: string,
): Promise<PrefrioQueuedCommand[]> {
  const items = (await loadPrefrioOutbox(userId, deviceId))
    .filter((item) => item.id !== operationId);
  await saveOutbox(userId, deviceId, items);
  return items;
}

export async function markPrefrioCommand(
  userId: string,
  deviceId: string,
  operationId: string,
  status: PrefrioQueuedCommand['status'],
  message: string | null,
): Promise<PrefrioQueuedCommand[]> {
  const items = await loadPrefrioOutbox(userId, deviceId);
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

export async function retryPrefrioCommand(
  userId: string,
  deviceId: string,
  operationId: string,
): Promise<PrefrioQueuedCommand[]> {
  const items = await loadPrefrioOutbox(userId, deviceId);
  const updated = items.map((item) => item.id === operationId
    ? { ...item, status: 'pendiente' as const, message: null }
    : item);
  await saveOutbox(userId, deviceId, updated);
  return updated;
}
