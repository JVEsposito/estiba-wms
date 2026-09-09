function hasReading(value) {
    return value !== null
        && value !== undefined
        && (typeof value !== 'string' || value.trim() !== '');
}

function statusText(value) {
    return String(value || '')
        .replaceAll('_', ' ')
        .replace(/^./, (character) => character.toUpperCase());
}

export function formatOperationalTemperature(value) {
    if (!hasReading(value)) return 'SIN REGISTRO';

    const temperature = Number(value);

    return Number.isFinite(temperature)
        ? `${temperature.toLocaleString('es-CL', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} °C`
        : 'SIN REGISTRO';
}

export function operationalTemperatureAverage(temperatures) {
    const values = [
        temperatures?.inicio_c,
        temperatures?.medio_c,
        temperatures?.fondo_c,
    ];

    if (!values.every(hasReading)) return null;

    const numericValues = values.map(Number);
    if (!numericValues.every(Number.isFinite)) return null;

    return numericValues.reduce((sum, value) => sum + value, 0) / numericValues.length;
}

export function operationalPositionTone(position) {
    if (position?.estado !== 'activa') return 'disabled';
    if (position?.reservada) return 'reserved';
    if (position?.ocupada) return 'occupied';
    return 'available';
}

export function operationalPositionDescription(position) {
    const folios = Array.isArray(position?.folios) ? position.folios : [];
    if (position?.reservada) {
        return position.reserva_operacional?.responsable?.nombre
            ? `Reserva · ${position.reserva_operacional.responsable.nombre}`
            : 'Reserva operacional';
    }
    if (folios.length > 1) return `${folios.length} folios ubicados`;
    if (folios[0]?.numero_folio) return `Folio ${folios[0].numero_folio}`;
    if (position?.estado !== 'activa') return statusText(position?.estado || 'fuera de servicio');
    return 'Disponible';
}

export function beginOperationalCameraSnapshot(state, cameraId) {
    state.selectedOperationalCameraId = cameraId;
    state.selectedOperationalPlan = null;
    state.operationalMovements = [];
    state.operationalEnvironment = null;
}
