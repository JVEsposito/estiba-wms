import * as Updates from 'expo-updates';

export async function applyAvailableUpdate(): Promise<void> {
  if (__DEV__ || !Updates.isEnabled) return;

  try {
    const update = await Updates.checkForUpdateAsync();
    if (!update.isAvailable) return;

    await Updates.fetchUpdateAsync();
    await Updates.reloadAsync();
  } catch {
    // La operación continúa con la última versión válida almacenada en la tablet.
  }
}
