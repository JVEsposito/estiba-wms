export function cameraDisplayName(camera) {
    const name = String(camera?.nombre || '').trim();

    return name || 'Cámara sin nombre';
}
