const $ = (id) => document.getElementById(id);

const elements = {
    accessScreen: $('accessScreen'),
    accessForm: $('accessForm'),
    accessError: $('accessError'),
    appShell: $('appShell'),
    networkStatus: $('networkStatus'),
    globalSessionStatus: $('globalSessionStatus'),
    operatorButton: $('operatorButton'),
    operatorInitials: $('operatorInitials'),
    operatorName: $('operatorName'),
    deviceName: $('deviceName'),
    logoutButton: $('logoutButton'),
    cameraList: $('cameraList'),
    refreshCamerasButton: $('refreshCamerasButton'),
    planBreadcrumb: $('planBreadcrumb'),
    planTitle: $('planTitle'),
    planSubtitle: $('planSubtitle'),
    planVersion: $('planVersion'),
    occupancyValue: $('occupancyValue'),
    occupancyBar: $('occupancyBar'),
    occupancyDetail: $('occupancyDetail'),
    lockBanner: $('lockBanner'),
    lockTitle: $('lockTitle'),
    lockMessage: $('lockMessage'),
    positionMap: $('positionMap'),
    selectedPositionLabel: $('selectedPositionLabel'),
    selectedPositionState: $('selectedPositionState'),
    folioCard: $('folioCard'),
    selectedFolioType: $('selectedFolioType'),
    selectedFolioNumber: $('selectedFolioNumber'),
    selectedFolioVariety: $('selectedFolioVariety'),
    selectedFolioCaliber: $('selectedFolioCaliber'),
    selectedFolioSag: $('selectedFolioSag'),
    locateButton: $('locateButton'),
    moveButton: $('moveButton'),
    sessionButton: $('sessionButton'),
    sessionButtonTitle: $('sessionButtonTitle'),
    sessionButtonSubtitle: $('sessionButtonSubtitle'),
    refreshPlanButton: $('refreshPlanButton'),
    actionNote: $('actionNote'),
    recentList: $('recentList'),
    lastSyncText: $('lastSyncText'),
    locateDialog: $('locateDialog'),
    locateForm: $('locateForm'),
    locateDestinationText: $('locateDestinationText'),
    locateError: $('locateError'),
    sagSelect: $('sagSelect'),
    moveDialog: $('moveDialog'),
    moveForm: $('moveForm'),
    moveDialogTitle: $('moveDialogTitle'),
    moveOriginText: $('moveOriginText'),
    moveCameraSelect: $('moveCameraSelect'),
    moveDestinationGrid: $('moveDestinationGrid'),
    moveDestinationHint: $('moveDestinationHint'),
    moveError: $('moveError'),
    confirmMoveButton: $('confirmMoveButton'),
    loadingOverlay: $('loadingOverlay'),
    loadingText: $('loadingText'),
    toastRegion: $('toastRegion'),
};

const storageKeys = {
    token: 'estiba_wms_token',
    identity: 'estiba_wms_identity',
    deviceCode: 'estiba_wms_device_code',
};

const state = {
    token: localStorage.getItem(storageKeys.token),
    identity: readStoredJson(storageKeys.identity),
    cameras: [],
    conditions: [],
    selectedCameraId: null,
    plan: null,
    selectedPosition: null,
    destinationPlan: null,
    moveDestination: null,
    polling: false,
};

class ApiError extends Error {
    constructor(message, status, data = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

function readStoredJson(key) {
    try {
        return JSON.parse(localStorage.getItem(key) || 'null');
    } catch {
        return null;
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function compactObject(object) {
    return Object.fromEntries(
        Object.entries(object).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
}

function operationUuid() {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();

    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function validationMessage(data, fallback) {
    const messages = Object.values(data?.errors || {}).flat();
    return messages[0] || data?.message || fallback;
}

async function api(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');

    if (state.token) {
        headers.set('Authorization', `Bearer ${state.token}`);
    }

    if (options.body && !(options.body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
    }

    let response;

    try {
        response = await fetch(path, { ...options, headers });
    } catch {
        throw new ApiError('No fue posible conectar con el servidor. Revisa la conexión de red.', 0);
    }

    const data = response.status === 204
        ? null
        : await response.json().catch(() => ({}));

    if (! response.ok) {
        if (response.status === 401 && path !== '/api/acceso-tablet') {
            clearSession();
        }

        throw new ApiError(
            validationMessage(data, 'La operación no pudo completarse.'),
            response.status,
            data,
        );
    }

    return data;
}

async function apiWithPhysicalWarnings(path, payload) {
    try {
        return await api(path, { method: 'POST', body: JSON.stringify(payload) });
    } catch (error) {
        const warnings = error.data?.codigo === 'confirmacion_requerida'
            && Array.isArray(error.data?.advertencias)
            ? error.data.advertencias
            : [];

        if (warnings.length === 0) throw error;

        const message = warnings
            .map((warning) => `${warning.titulo}\n${warning.mensaje}`)
            .join('\n\n');

        if (! window.confirm(`Confirmar excepción física\n\n${message}`)) {
            throw new ApiError('Operación cancelada: no se confirmaron las advertencias físicas.', 499);
        }

        return api(path, {
            method: 'POST',
            body: JSON.stringify({
                ...payload,
                advertencias_confirmadas: warnings.map((warning) => warning.codigo),
            }),
        });
    }
}

function setBusy(active, message = 'Sincronizando…') {
    elements.loadingText.textContent = message;
    elements.loadingOverlay.classList.toggle('is-hidden', ! active);
}

async function withBusy(message, callback) {
    setBusy(true, message);

    try {
        return await callback();
    } finally {
        setBusy(false);
    }
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast${type === 'error' ? ' is-error' : ''}${type === 'warning' ? ' is-warning' : ''}`;
    toast.textContent = message;
    elements.toastRegion.append(toast);

    window.setTimeout(() => toast.remove(), 4200);
}

function persistSession(token, identity) {
    state.token = token;
    state.identity = identity;
    localStorage.setItem(storageKeys.token, token);
    localStorage.setItem(storageKeys.identity, JSON.stringify(identity));
    localStorage.setItem(storageKeys.deviceCode, identity.dispositivo.codigo);
}

function clearSession() {
    state.token = null;
    state.identity = null;
    state.cameras = [];
    state.plan = null;
    state.selectedCameraId = null;
    state.selectedPosition = null;
    localStorage.removeItem(storageKeys.token);
    localStorage.removeItem(storageKeys.identity);
    elements.appShell.classList.add('is-hidden');
    elements.accessScreen.classList.remove('is-hidden');
    elements.logoutButton.classList.add('is-hidden');
}

function showApplication() {
    elements.accessScreen.classList.add('is-hidden');
    elements.appShell.classList.remove('is-hidden');

    const name = state.identity?.usuario?.nombre || 'Operador';
    const initials = name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

    elements.operatorInitials.textContent = initials || 'OP';
    elements.operatorName.textContent = name;
    elements.deviceName.textContent = state.identity?.dispositivo?.nombre || 'Tablet';
}

function selectedCamera() {
    return state.cameras.find((camera) => camera.id === state.selectedCameraId) || null;
}

function ownSession(plan = state.plan) {
    if (plan?.acceso?.modo !== 'edicion' || ! plan.acceso.sesion?.es_propia) {
        return null;
    }

    return plan.acceso.sesion;
}

function canOperate() {
    return navigator.onLine
        && state.plan?.acceso?.modo === 'edicion'
        && Boolean(ownSession());
}

function positionLabel(position) {
    return position?.etiqueta || `B${String(position?.banda).padStart(2, '0')}-P${String(position?.posicion).padStart(2, '0')}-N${position?.nivel}`;
}

function typeLabel(type) {
    return {
        ubicacion_inicial: 'Ubicación inicial',
        reubicacion: 'Reubicación',
        traslado_entre_camaras: 'Cambio de cámara',
        retiro: 'Retiro',
        reversion: 'Reversión',
    }[type] || type;
}

function updateNetworkStatus() {
    const online = navigator.onLine;
    const dot = elements.networkStatus.querySelector('.status-dot');
    elements.networkStatus.querySelector('span').textContent = online ? 'En línea' : 'Sin conexión';
    dot.className = `status-dot ${online ? 'status-dot--green' : 'status-dot--red'}`;
    updateActions();
}

async function signIn(form) {
    const values = Object.fromEntries(new FormData(form));
    values.email = values.email.trim().toLowerCase();
    values.codigo_dispositivo = values.codigo_dispositivo.trim().toUpperCase();
    elements.accessError.textContent = '';

    const data = await withBusy('Validando acceso…', () => api('/api/acceso-tablet', {
        method: 'POST',
        body: JSON.stringify(values),
    }));

    persistSession(data.token, {
        usuario: data.usuario,
        dispositivo: data.dispositivo,
    });
    form.password.value = '';
    showApplication();
    await loadApplication();
}

async function signOut() {
    try {
        await api('/api/acceso-tablet', { method: 'DELETE' });
    } catch {
        // El token se elimina localmente incluso si el servidor no está disponible.
    }

    clearSession();
}

async function loadApplication() {
    await withBusy('Cargando cámaras…', async () => {
        const [cameraResponse, conditionResponse] = await Promise.all([
            api('/api/camaras'),
            api('/api/condiciones-sag'),
        ]);

        state.cameras = cameraResponse.data || [];
        state.conditions = conditionResponse.data || [];
        renderConditions();
        renderCameras();

        if (state.cameras.length > 0) {
            await selectCamera(state.cameras[0].id, false);
        } else {
            renderEmptyCameras();
        }
    });
}

function renderConditions() {
    const options = state.conditions.map((condition) => (
        `<option value="${escapeHtml(condition.id)}">${escapeHtml(condition.codigo)} · ${escapeHtml(condition.nombre)}</option>`
    ));

    elements.sagSelect.innerHTML = '<option value="">Sin especificar</option>' + options.join('');
}

function renderEmptyCameras() {
    elements.cameraList.innerHTML = '<div class="recent-empty">No existen cámaras activas para mostrar.</div>';
    elements.positionMap.innerHTML = `
        <div class="empty-state">
            <div class="empty-state__icon">▦</div>
            <strong>Sin cámaras configuradas</strong>
            <span>Un administrador debe crear cámaras y posiciones antes de operar.</span>
        </div>`;
    updateActions();
}

function renderCameras() {
    if (state.cameras.length === 0) {
        renderEmptyCameras();
        return;
    }

    elements.cameraList.innerHTML = state.cameras.map((camera) => {
        const selected = camera.id === state.selectedCameraId;
        const access = camera.acceso || {};
        const locked = access.bloqueada;
        const own = access.sesion?.es_propia;
        const label = own ? 'Edición propia' : locked ? 'En uso' : 'Disponible';
        const dot = own ? 'status-dot--cyan' : locked ? 'status-dot--amber' : 'status-dot--green';
        const percentage = Number(camera.ocupacion?.porcentaje || 0);

        return `
            <button class="camera-card${selected ? ' is-active' : ''}" type="button" data-camera-id="${escapeHtml(camera.id)}">
                <span class="camera-card__top">
                    <strong>${escapeHtml(camera.codigo)}</strong>
                    <span class="camera-card__state"><i class="status-dot ${dot}"></i>${label}</span>
                </span>
                <span class="camera-card__meta">
                    <span>${escapeHtml(camera.nombre)}</span>
                    <span>${percentage}%</span>
                </span>
                <span class="mini-progress"><i style="width:${Math.min(100, percentage)}%"></i></span>
            </button>`;
    }).join('');

    elements.cameraList.querySelectorAll('[data-camera-id]').forEach((button) => {
        button.addEventListener('click', () => selectCamera(button.dataset.cameraId));
    });
}

async function reloadCameraList() {
    const response = await api('/api/camaras');
    state.cameras = response.data || [];
    renderCameras();
}

async function selectCamera(cameraId, showLoading = true) {
    state.selectedCameraId = cameraId;
    state.selectedPosition = null;
    renderCameras();

    const load = async () => {
        const [planResponse, recentResponse] = await Promise.all([
            api(`/api/camaras/${cameraId}/plano`),
            api(`/api/movimientos/recientes?camara_id=${encodeURIComponent(cameraId)}&limite=8`),
        ]);

        state.plan = planResponse.data;
        renderPlan();
        renderRecent(recentResponse.data || []);
        updateLastSync();
    };

    if (showLoading) {
        await withBusy('Cargando plano…', load);
    } else {
        await load();
    }
}

async function refreshCurrent({ quiet = false } = {}) {
    if (! state.selectedCameraId) return;

    const refresh = async () => {
        await Promise.all([
            reloadCameraList(),
            selectCamera(state.selectedCameraId, false),
        ]);
    };

    if (quiet) {
        await refresh();
    } else {
        await withBusy('Actualizando plano…', refresh);
        showToast('Plano actualizado.');
    }
}

function renderPlan() {
    const plan = state.plan;

    if (! plan) return;

    elements.planBreadcrumb.textContent = `PLANO DE ESTIBA · ${plan.tipo.toUpperCase()}`;
    elements.planTitle.textContent = `${plan.codigo} · ${plan.nombre}`;
    elements.planSubtitle.textContent = `${plan.posiciones.length} posiciones configuradas`;
    elements.planVersion.textContent = `v${plan.version_plano}`;
    elements.occupancyValue.textContent = `${plan.ocupacion.porcentaje}%`;
    elements.occupancyBar.style.width = `${Math.min(100, plan.ocupacion.porcentaje)}%`;
    elements.occupancyDetail.textContent = `${plan.ocupacion.ocupadas} de ${plan.ocupacion.total} posiciones`;

    renderLockState();
    renderPositionMap();
    renderSelection();
    updateActions();
}

function renderLockState() {
    const access = state.plan?.acceso;
    const sessionChipDot = elements.globalSessionStatus.querySelector('.status-dot');

    if (access?.modo === 'solo_lectura') {
        const session = access.sesion;
        elements.lockBanner.classList.remove('is-hidden');
        elements.lockTitle.textContent = 'Cámara en modificación';
        elements.lockMessage.textContent = `${session?.usuario?.nombre || 'Otro operador'} trabaja desde ${session?.dispositivo?.nombre || 'otra tablet'}. El plano está en modo consulta.`;
    } else {
        elements.lockBanner.classList.add('is-hidden');
    }

    if (access?.modo === 'edicion') {
        elements.globalSessionStatus.classList.add('is-editing');
        sessionChipDot.className = 'status-dot status-dot--cyan';
        elements.globalSessionStatus.querySelector('span').textContent = `Editando ${state.plan.codigo}`;
    } else {
        elements.globalSessionStatus.classList.remove('is-editing');
        sessionChipDot.className = 'status-dot';
        elements.globalSessionStatus.querySelector('span').textContent = 'Solo consulta';
    }
}

function renderPositionMap() {
    const positions = state.plan?.posiciones || [];

    if (positions.length === 0) {
        elements.positionMap.innerHTML = `
            <div class="empty-state">
                <div class="empty-state__icon">▦</div>
                <strong>Cámara sin posiciones</strong>
                <span>Configura el plano de esta cámara antes de comenzar una estiba.</span>
            </div>`;
        return;
    }

    const levels = [...new Set(positions.map((position) => Number(position.nivel)))].sort((a, b) => a - b);
    const bands = [...new Set(positions.map((position) => Number(position.banda)))].sort((a, b) => a - b);
    const maxPosition = Math.max(...positions.map((position) => Number(position.posicion)));
    const lookup = new Map(positions.map((position) => [
        `${position.nivel}|${position.banda}|${position.posicion}`,
        position,
    ]));

    elements.positionMap.innerHTML = levels.map((level) => {
        const bandColumns = bands.map((band) => {
            const cells = [];
            for (let positionNumber = 1; positionNumber <= maxPosition; positionNumber += 1) {
                const position = lookup.get(`${level}|${band}|${positionNumber}`);
                cells.push(position ? renderPositionCell(position) : '<span class="position-cell--gap"></span>');
            }

            return `
                <div class="band-column">
                    <strong class="band-heading">BANDA ${String(band).padStart(2, '0')}</strong>
                    ${cells.join('')}
                </div>`;
        }).join('');

        return `
            <section class="level-group">
                <div class="level-heading">NIVEL ${level}</div>
                <div class="map-orientation"><strong>↑ FONDO</strong><span>P01 se ocupa primero</span></div>
                <div class="band-layout">
                    ${bandColumns}
                </div>
                <div class="map-orientation map-orientation--entrance"><strong>↓ ENTRADA</strong></div>
            </section>`;
    }).join('');

    elements.positionMap.querySelectorAll('[data-position-id]').forEach((button) => {
        button.addEventListener('click', () => selectPosition(button.dataset.positionId));
    });
}

function renderPositionCell(position) {
    const selected = state.selectedPosition?.id === position.id;
    const blocked = position.estado !== 'activa';
    const occupied = position.ocupada;
    const saldo = position.folio?.tipo_bulto === 'saldo';
    const classes = [
        'position-cell',
        selected ? 'is-selected' : '',
        occupied ? 'is-occupied' : '',
        saldo ? 'is-saldo' : '',
        blocked ? 'is-blocked' : '',
    ].filter(Boolean).join(' ');
    const folio = position.folio?.numero_folio || (blocked ? 'NO DISPONIBLE' : 'LIBRE');
    const detail = occupied
        ? (position.folio?.variedad || position.folio?.tipo_bulto || 'Bulto')
        : (blocked ? position.estado.replaceAll('_', ' ') : 'Disponible');

    return `
        <button class="${classes}" type="button" data-position-id="${escapeHtml(position.id)}">
            <span class="position-cell__location">${escapeHtml(position.etiqueta || `B${position.banda} · P${position.posicion}`)}</span>
            <strong class="position-cell__folio">${escapeHtml(folio)}</strong>
            <span class="position-cell__meta"><span>${escapeHtml(detail)}</span><span>${occupied ? (saldo ? 'S' : 'P') : '○'}</span></span>
        </button>`;
}

function selectPosition(positionId) {
    state.selectedPosition = state.plan?.posiciones.find((position) => position.id === positionId) || null;
    renderPositionMap();
    renderSelection();
    updateActions();
}

function renderSelection() {
    const position = state.selectedPosition;

    if (! position) {
        elements.selectedPositionLabel.textContent = 'Ninguna';
        elements.selectedPositionState.textContent = 'Toca una posición del plano';
        elements.folioCard.classList.add('is-hidden');
        return;
    }

    elements.selectedPositionLabel.textContent = positionLabel(position);
    elements.selectedPositionState.textContent = position.estado !== 'activa'
        ? 'Posición no disponible'
        : position.ocupada ? 'Ocupada por un folio' : 'Libre para ubicación';

    if (! position.folio) {
        elements.folioCard.classList.add('is-hidden');
        return;
    }

    const folio = position.folio;
    elements.folioCard.classList.remove('is-hidden');
    elements.selectedFolioType.textContent = folio.tipo_bulto.toUpperCase();
    elements.selectedFolioNumber.textContent = folio.numero_folio;
    elements.selectedFolioVariety.textContent = folio.variedad || '—';
    elements.selectedFolioCaliber.textContent = folio.calibre || '—';
    elements.selectedFolioSag.textContent = folio.condicion_sag?.codigo || '—';
}

function updateActions() {
    const plan = state.plan;
    const position = state.selectedPosition;
    const operating = canOperate();
    const isConsultation = state.identity?.usuario?.rol === 'consulta';

    elements.refreshPlanButton.disabled = ! plan || ! navigator.onLine;
    elements.locateButton.disabled = ! operating
        || ! position
        || position.ocupada
        || position.estado !== 'activa';
    elements.moveButton.disabled = ! operating
        || ! position?.ocupada
        || position.estado !== 'activa';

    if (! plan) {
        elements.sessionButton.disabled = true;
        elements.actionNote.textContent = 'Selecciona una cámara para comenzar.';
        return;
    }

    if (plan.acceso.modo === 'edicion') {
        elements.sessionButton.disabled = ! navigator.onLine;
        elements.sessionButtonTitle.textContent = 'Cerrar estiba';
        elements.sessionButtonSubtitle.textContent = 'Liberar la cámara';
        elements.actionNote.textContent = position
            ? 'La cámara está en edición. Confirma cada movimiento antes de continuar.'
            : 'Sesión activa: selecciona una posición para operar.';
    } else if (plan.acceso.modo === 'disponible') {
        elements.sessionButton.disabled = isConsultation || ! navigator.onLine;
        elements.sessionButtonTitle.textContent = 'Abrir estiba';
        elements.sessionButtonSubtitle.textContent = isConsultation ? 'Tu perfil es solo consulta' : 'Iniciar sesión de edición';
        elements.actionNote.textContent = isConsultation
            ? 'Tu perfil permite revisar el plano, pero no modificarlo.'
            : 'Abre la estiba para habilitar movimientos en esta cámara.';
    } else {
        elements.sessionButton.disabled = true;
        elements.sessionButtonTitle.textContent = 'Cámara en uso';
        elements.sessionButtonSubtitle.textContent = 'Edición bloqueada';
        elements.actionNote.textContent = 'La edición se habilitará cuando el otro operador cierre su sesión.';
    }
}

async function toggleSession() {
    if (! state.plan) return;

    if (state.plan.acceso.modo === 'edicion') {
        const confirmed = window.confirm(`¿Cerrar la estiba de ${state.plan.codigo} y liberar la cámara?`);
        if (! confirmed) return;

        await withBusy('Cerrando estiba…', () => api(`/api/sesiones/${ownSession().id}/cerrar`, {
            method: 'POST',
            body: JSON.stringify({ motivo: 'Cierre desde interfaz tablet' }),
        }));
        showToast('Estiba cerrada y cámara liberada.');
    } else {
        await withBusy('Abriendo estiba…', () => api(`/api/camaras/${state.plan.id}/sesiones`, {
            method: 'POST',
        }));
        showToast(`Sesión de edición abierta en ${state.plan.codigo}.`);
    }

    await refreshCurrent({ quiet: true });
}

function openLocateDialog() {
    const position = state.selectedPosition;
    if (! position || position.ocupada || ! canOperate()) return;

    elements.locateForm.reset();
    elements.locateError.textContent = '';
    elements.locateDestinationText.textContent = `Destino: ${state.plan.codigo} · ${positionLabel(position)}`;
    renderConditions();
    elements.locateDialog.showModal();
    window.setTimeout(() => elements.locateForm.numero_folio.focus(), 50);
}

async function locateFolio(form) {
    const position = state.selectedPosition;
    if (! position || ! ownSession()) return;

    const values = Object.fromEntries(new FormData(form));
    const descriptiveData = compactObject({
        condicion_sag_id: values.condicion_sag_id,
        variedad: values.variedad?.trim(),
        calibre: values.calibre?.trim(),
        marca: values.marca?.trim(),
        exportadora: values.exportadora?.trim(),
    });
    const payload = {
        operacion_id: operationUuid(),
        numero_folio: values.numero_folio.trim().toUpperCase(),
        tipo_bulto: values.tipo_bulto,
        posicion_destino_id: position.id,
        sesion_destino_id: ownSession().id,
        version_destino_conocida: state.plan.version_plano,
        generado_dispositivo_at: new Date().toISOString(),
        ...(Object.keys(descriptiveData).length > 0 ? { datos_folio: descriptiveData } : {}),
    };

    elements.locateError.textContent = '';

    try {
        await withBusy(
            'Registrando ubicación…',
            () => apiWithPhysicalWarnings('/api/movimientos/ubicar', payload),
        );
        elements.locateDialog.close();
        showToast(`Folio ${payload.numero_folio} ubicado correctamente.`);
        await refreshCurrent({ quiet: true });
    } catch (error) {
        elements.locateError.textContent = error.message;

        if (error.status === 409) {
            await refreshCurrent({ quiet: true });
        }
    }
}

async function openMoveDialog() {
    const position = state.selectedPosition;
    if (! position?.folio || ! canOperate()) return;

    elements.moveForm.reset();
    elements.moveError.textContent = '';
    elements.moveDialogTitle.textContent = `Mover ${position.folio.numero_folio}`;
    elements.moveOriginText.textContent = `Origen: ${state.plan.codigo} · ${positionLabel(position)}`;
    elements.moveCameraSelect.innerHTML = state.cameras
        .filter((camera) => camera.estado === 'activa')
        .map((camera) => `<option value="${escapeHtml(camera.id)}">${escapeHtml(camera.codigo)} · ${escapeHtml(camera.nombre)}</option>`)
        .join('');
    elements.moveCameraSelect.value = state.plan.id;
    state.moveDestination = null;
    elements.confirmMoveButton.disabled = true;
    elements.moveDialog.showModal();
    await loadDestinationPlan(state.plan.id);
}

async function loadDestinationPlan(cameraId) {
    elements.moveDestinationGrid.innerHTML = '<div class="recent-empty">Cargando posiciones…</div>';
    elements.moveDestinationHint.textContent = 'Consultando plano';
    state.moveDestination = null;
    elements.confirmMoveButton.disabled = true;

    try {
        state.destinationPlan = cameraId === state.plan.id
            ? state.plan
            : (await api(`/api/camaras/${cameraId}/plano`)).data;
        renderDestinations();
    } catch (error) {
        elements.moveDestinationGrid.innerHTML = '<div class="recent-empty">No fue posible cargar el destino.</div>';
        elements.moveError.textContent = error.message;
    }
}

function renderDestinations() {
    const plan = state.destinationPlan;
    const free = (plan?.posiciones || []).filter((position) => (
        position.estado === 'activa'
        && ! position.ocupada
        && position.id !== state.selectedPosition?.id
    ));

    if (plan?.acceso?.modo === 'solo_lectura') {
        elements.moveDestinationGrid.innerHTML = '<div class="recent-empty">La cámara de destino está siendo modificada por otro operador.</div>';
        elements.moveDestinationHint.textContent = 'Destino bloqueado';
        return;
    }

    if (free.length === 0) {
        elements.moveDestinationGrid.innerHTML = '<div class="recent-empty">No hay posiciones libres en esta cámara.</div>';
        elements.moveDestinationHint.textContent = 'Sin disponibilidad';
        return;
    }

    elements.moveDestinationHint.textContent = `${free.length} posiciones disponibles`;
    elements.moveDestinationGrid.innerHTML = free.map((position) => `
        <button class="destination-button" type="button" data-destination-id="${escapeHtml(position.id)}">
            <strong>${escapeHtml(positionLabel(position))}</strong>
            <small>Banda ${position.banda} · Posición ${position.posicion} · Nivel ${position.nivel}</small>
        </button>`).join('');

    elements.moveDestinationGrid.querySelectorAll('[data-destination-id]').forEach((button) => {
        button.addEventListener('click', () => {
            state.moveDestination = free.find((position) => position.id === button.dataset.destinationId);
            elements.moveDestinationGrid.querySelectorAll('.destination-button').forEach((candidate) => {
                candidate.classList.toggle('is-selected', candidate === button);
            });
            elements.confirmMoveButton.disabled = false;
        });
    });
}

async function ensureDestinationSession() {
    if (state.destinationPlan.id === state.plan.id) {
        return ownSession().id;
    }

    if (state.destinationPlan.acceso.modo === 'edicion'
        && state.destinationPlan.acceso.sesion?.es_propia) {
        return state.destinationPlan.acceso.sesion.id;
    }

    if (state.destinationPlan.acceso.modo !== 'disponible') {
        throw new ApiError('La cámara de destino no está disponible para edición.', 409);
    }

    const response = await api(`/api/camaras/${state.destinationPlan.id}/sesiones`, {
        method: 'POST',
    });
    state.destinationPlan.acceso = {
        modo: 'edicion',
        bloqueada: true,
        sesion: { ...response.data, es_propia: true },
    };
    showToast(`Se abrió una sesión en ${state.destinationPlan.codigo} para completar el traslado.`);

    return response.data.id;
}

async function moveFolio() {
    const origin = state.selectedPosition;
    const destination = state.moveDestination;
    const originSession = ownSession();

    if (! origin?.folio || ! destination || ! originSession) return;

    elements.moveError.textContent = '';

    try {
        await withBusy('Confirmando movimiento…', async () => {
            const destinationSessionId = await ensureDestinationSession();
            await apiWithPhysicalWarnings('/api/movimientos/mover', {
                    operacion_id: operationUuid(),
                    folio_id: origin.folio.id,
                    posicion_destino_id: destination.id,
                    sesion_origen_id: originSession.id,
                    sesion_destino_id: destinationSessionId,
                    version_origen_conocida: state.plan.version_plano,
                    version_destino_conocida: state.destinationPlan.version_plano,
                    generado_dispositivo_at: new Date().toISOString(),
            });
        });

        const changedCamera = state.destinationPlan.id !== state.plan.id;
        elements.moveDialog.close();
        showToast(changedCamera
            ? `Folio trasladado a ${state.destinationPlan.codigo}.`
            : 'Folio reubicado dentro de la cámara.');
        await refreshCurrent({ quiet: true });
    } catch (error) {
        elements.moveError.textContent = error.message;

        if (error.status === 409) {
            await refreshCurrent({ quiet: true });
        }
    }
}

function renderRecent(movements) {
    if (movements.length === 0) {
        elements.recentList.innerHTML = '<div class="recent-empty">Aún no hay movimientos registrados en esta cámara.</div>';
        return;
    }

    elements.recentList.innerHTML = movements.map((movement) => {
        const origin = movement.origen
            ? `${movement.origen.camara.codigo} · ${movement.origen.posicion.etiqueta || `B${movement.origen.posicion.banda}`}`
            : 'Ingreso';
        const destination = movement.destino
            ? `${movement.destino.camara.codigo} · ${movement.destino.posicion.etiqueta || `B${movement.destino.posicion.banda}`}`
            : 'Salida';
        const date = new Date(movement.created_at || movement.recibido_servidor_at);

        return `
            <article class="recent-item">
                <span class="recent-item__icon">${movement.tipo_movimiento === 'ubicacion_inicial' ? '＋' : '⇄'}</span>
                <span class="recent-item__copy">
                    <strong>${escapeHtml(movement.folio.numero_folio)} · ${escapeHtml(typeLabel(movement.tipo_movimiento))}</strong>
                    <span>${escapeHtml(origin)} → ${escapeHtml(destination)}</span>
                </span>
                <time datetime="${escapeHtml(date.toISOString())}">${date.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })}</time>
            </article>`;
    }).join('');
}

function updateLastSync() {
    elements.lastSyncText.textContent = `Actualizado ${new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })}`;
}

function closeDialog(id) {
    const dialog = $(id);
    if (dialog?.open) dialog.close();
}

async function poll() {
    if (state.polling
        || ! state.token
        || ! navigator.onLine
        || document.hidden
        || elements.locateDialog.open
        || elements.moveDialog.open) {
        return;
    }

    state.polling = true;

    try {
        await refreshCurrent({ quiet: true });
    } catch (error) {
        if (error.status !== 401) {
            console.warn('No fue posible actualizar el plano automáticamente.', error);
        }
    } finally {
        state.polling = false;
    }
}

elements.accessForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    try {
        await signIn(event.currentTarget);
    } catch (error) {
        elements.accessError.textContent = error.message;
    }
});

elements.operatorButton?.addEventListener('click', () => {
    const opening = elements.logoutButton.classList.contains('is-hidden');
    elements.logoutButton.classList.toggle('is-hidden', ! opening);
    elements.operatorButton.setAttribute('aria-expanded', String(opening));
});
elements.logoutButton?.addEventListener('click', signOut);
elements.refreshCamerasButton?.addEventListener('click', () => refreshCurrent());
elements.refreshPlanButton?.addEventListener('click', () => refreshCurrent());
elements.sessionButton?.addEventListener('click', async () => {
    try {
        await toggleSession();
    } catch (error) {
        showToast(error.message, error.status === 409 ? 'warning' : 'error');
        await refreshCurrent({ quiet: true }).catch(() => {});
    }
});
elements.locateButton?.addEventListener('click', openLocateDialog);
elements.moveButton?.addEventListener('click', openMoveDialog);
elements.locateForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    locateFolio(event.currentTarget);
});
elements.moveForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    moveFolio();
});
elements.moveCameraSelect?.addEventListener('change', (event) => {
    elements.moveError.textContent = '';
    loadDestinationPlan(event.currentTarget.value);
});
document.querySelectorAll('[data-close-dialog]').forEach((button) => {
    button.addEventListener('click', () => closeDialog(button.dataset.closeDialog));
});
window.addEventListener('online', async () => {
    updateNetworkStatus();
    showToast('Conexión recuperada. Actualizando datos.');
    await poll();
});
window.addEventListener('offline', () => {
    updateNetworkStatus();
    showToast('Sin conexión. Las operaciones quedan temporalmente bloqueadas.', 'warning');
});

const rememberedDeviceCode = localStorage.getItem(storageKeys.deviceCode);
if (rememberedDeviceCode && elements.accessForm) {
    elements.accessForm.codigo_dispositivo.value = rememberedDeviceCode;
}

updateNetworkStatus();

if (state.token && state.identity) {
    showApplication();
    loadApplication().catch((error) => {
        if (error.status !== 401) {
            showToast(error.message, 'error');
        }
    });
}

window.setInterval(poll, 30000);
