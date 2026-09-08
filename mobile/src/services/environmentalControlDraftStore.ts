import AsyncStorage from '@react-native-async-storage/async-storage';

import { EnvironmentalControlDraft } from '../domain/environmentalControl';

const prefix = 'estiba:control-ambiental:borrador';
const key = (userId: string, deviceId: string) => `${prefix}:${userId}:${deviceId}`;

export async function loadEnvironmentalDraft(userId: string, deviceId: string) {
  const raw = await AsyncStorage.getItem(key(userId, deviceId));
  if (!raw) return null;
  try {
    const value = JSON.parse(raw) as Partial<EnvironmentalControlDraft>;
    if (typeof value.operationId !== 'string') return null;
    return {
      cameraId: typeof value.cameraId === 'string' ? value.cameraId : null,
      cameraCode: typeof value.cameraCode === 'string' ? value.cameraCode : null,
      start: typeof value.start === 'string' ? value.start : '',
      middle: typeof value.middle === 'string' ? value.middle : '',
      end: typeof value.end === 'string' ? value.end : '',
      operationId: value.operationId,
      capturedAt: typeof value.capturedAt === 'string' ? value.capturedAt : null,
    } satisfies EnvironmentalControlDraft;
  } catch {
    await AsyncStorage.removeItem(key(userId, deviceId));
    return null;
  }
}

export function saveEnvironmentalDraft(userId: string, deviceId: string, draft: EnvironmentalControlDraft) {
  return AsyncStorage.setItem(key(userId, deviceId), JSON.stringify(draft));
}

export function clearEnvironmentalDraft(userId: string, deviceId: string) {
  return AsyncStorage.removeItem(key(userId, deviceId));
}
