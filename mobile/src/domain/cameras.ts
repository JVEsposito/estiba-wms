type CameraDisplayIdentity = {
  nombre?: string | null;
};

export function cameraDisplayName(camera?: CameraDisplayIdentity | null): string {
  const name = camera?.nombre?.trim();

  return name || 'Cámara sin nombre';
}
