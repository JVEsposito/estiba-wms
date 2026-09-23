export const LEFT_TO_RIGHT = 'izquierda_a_derecha';
export const RIGHT_TO_LEFT = 'derecha_a_izquierda';

export function orderBandsForCamera(camera, bands, bandNumber = (band) => band) {
    const ordered = [...bands].sort(
        (left, right) => Number(bandNumber(left)) - Number(bandNumber(right)),
    );

    return camera?.sentido_numeracion_bandas === RIGHT_TO_LEFT
        ? ordered.reverse()
        : ordered;
}

export function bandNumberingLabel(camera) {
    return camera?.sentido_numeracion_bandas === RIGHT_TO_LEFT
        ? 'B01 a la derecha'
        : 'B01 a la izquierda';
}
