const byId = (id) => document.getElementById(id);

const elements = {
    access: byId('officeAccess'),
    app: byId('officeApp'),
    loginForm: byId('officeLoginForm'),
    loginError: byId('officeLoginError'),
    userName: byId('officeUserName'),
    userRole: byId('officeUserRole'),
    initials: byId('officeInitials'),
    logout: byId('officeLogoutButton'),
    reload: byId('reloadOfficeButton'),
    cameraList: byId('officeCameraList'),
    nextCode: byId('nextCameraCode'),
    createForm: byId('createCameraForm'),
    createError: byId('createCameraError'),
    reset: byId('resetCameraButton'),
    preview: byId('cameraPreview'),
    levelTabs: byId('previewLevelTabs'),
    capacity: byId('previewCapacity'),
    active: byId('previewActive'),
    disabled: byId('previewDisabled'),
    loading: byId('officeLoading'),
    loadingText: byId('officeLoadingText'),
    toasts: byId('officeToasts'),
};

const keys = {
    token: 'estiba_wms_office_token',
    identity: 'estiba_wms_office_identity',
};

const state = {
    token: localStorage.getItem(keys.token),
    identity: readJson(keys.identity),
    cameras: [],
    selectedLevel: 1,
    disabled: new Set(),
};

class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.status = status;
    }
}

function readJson(key) {
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

function errorMessage(data, fallback) {
    return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback;
}

async function api(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    if (state.token) headers.set('Authorization', `Bearer ${state.token}`);
    if (options.body) headers.set('Content-Type', 'application/json');

    let response;
    try {
        response = await fetch(path, { ...options, headers });
    } catch {
        throw new ApiError('No fue posible conectar con Laravel.', 0);
    }

    const data = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401 && path !== '/api/acceso-oficina') clearSession();
        throw new ApiError(errorMessage(data, 'No fue posible completar la operación.'), response.status);
    }
    return data;
}

function setBusy(active, message = 'Procesando…') {
    elements.loadingText.textContent = message;
    elements.loading.classList.toggle('is-hidden', !active);
}

function toast(message, error = false) {
    const item = document.createElement('div');
    item.className = `toast${error ? ' toast--error' : ''}`;
    item.textContent = message;
    elements.toasts.append(item);
    window.setTimeout(() => item.remove(), 4500);
}

function persistSession(payload) {
    state.token = payload.token;
    state.identity = payload.usuario;
    localStorage.setItem(keys.token, payload.token);
    localStorage.setItem(keys.identity, JSON.stringify(payload.usuario));
}

function clearSession() {
    state.token = null;
    state.identity = null;
    localStorage.removeItem(keys.token);
    localStorage.removeItem(keys.identity);
    elements.app.classList.add('is-hidden');
    elements.access.classList.remove('is-hidden');
}

function showApp() {
    elements.access.classList.add('is-hidden');
    elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Supervisor';
    elements.userName.textContent = name;
    elements.userRole.textContent = state.identity?.rol || 'oficina';
    elements.initials.textContent = name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

function dimensions() {
    const data = new FormData(elements.createForm);
    return {
        bandas: Math.max(1, Math.min(40, Number(data.get('bandas')) || 1)),
        posiciones: Math.max(1, Math.min(40, Number(data.get('posiciones_por_banda')) || 1)),
        niveles: Math.max(1, Math.min(10, Number(data.get('niveles')) || 1)),
    };
}

function keyOf(band, position, level) {
    return `${band}:${position}:${level}`;
}

function labelOf(band, position, level) {
    return `B${String(band).padStart(2, '0')}-P${String(position).padStart(2, '0')}-N${level}`;
}

function normalizeDisabled() {
    const { bandas, posiciones, niveles } = dimensions();
    state.disabled = new Set([...state.disabled].filter((key) => {
        const [band, position, level] = key.split(':').map(Number);
        return band <= bandas && position <= posiciones && level <= niveles;
    }));
    if (state.selectedLevel > niveles) state.selectedLevel = niveles;
}

function renderPreview() {
    normalizeDisabled();
    const { bandas, posiciones, niveles } = dimensions();
    const total = bandas * posiciones * niveles;
    elements.capacity.textContent = String(total);
    elements.disabled.textContent = String(state.disabled.size);
    elements.active.textContent = String(total - state.disabled.size);

    elements.levelTabs.innerHTML = Array.from({ length: niveles }, (_, index) => index + 1).map((level) => `
        <button class="level-tab${state.selectedLevel === level ? ' is-active' : ''}" data-level="${level}" type="button">Nivel ${level}</button>
    `).join('');

    elements.preview.innerHTML = Array.from({ length: bandas }, (_, index) => index + 1).map((band) => {
        const cells = Array.from({ length: posiciones }, (_, positionIndex) => positionIndex + 1).map((position) => {
            const key = keyOf(band, position, state.selectedLevel);
            const disabled = state.disabled.has(key);
            return `
                <button class="preview-position${disabled ? ' is-disabled' : ''}" data-coordinate="${key}" type="button">
                    <span>P${String(position).padStart(2, '0')}</span>
                    <strong>${labelOf(band, position, state.selectedLevel)}</strong>
                    <small>${disabled ? 'Fuera de servicio' : 'Operativa'}</small>
                </button>
            `;
        }).join('');
        return `<section class="preview-band"><h3>BANDA ${String(band).padStart(2, '0')}</h3>${cells}</section>`;
    }).join('');
}

function renderCameras() {
    if (!state.cameras.length) {
        elements.cameraList.innerHTML = '<div class="empty-state">Aún no existen cámaras configuradas.</div>';
        return;
    }

    elements.cameraList.innerHTML = state.cameras.map((camera) => `
        <article class="camera-item">
            <div><strong>${escapeHtml(camera.codigo)}</strong><span class="state-dot"></span></div>
            <h3>${escapeHtml(camera.nombre)}</h3>
            <p>${camera.dimensiones.bandas} bandas · ${camera.dimensiones.posiciones_por_banda} posiciones · ${camera.dimensiones.niveles} niveles</p>
            <div class="camera-item__capacity"><span>${camera.capacidad.activas} operativas</span><span>${camera.capacidad.fuera_servicio} fuera de servicio</span></div>
        </article>
    `).join('');
}

async function loadConfiguration() {
    const [cameras, next] = await Promise.all([
        api('/api/configuracion/camaras'),
        api('/api/configuracion/camaras/siguiente-codigo'),
    ]);
    state.cameras = cameras.data;
    elements.nextCode.textContent = next.data.codigo;
    renderCameras();
}

elements.loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.loginError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.loginForm));
    setBusy(true, 'Validando acceso…');
    try {
        const payload = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify(data) });
        if (!payload.usuario.puede_configurar_camaras) throw new ApiError('Tu perfil no puede configurar cámaras.', 403);
        persistSession(payload);
        showApp();
        await loadConfiguration();
    } catch (error) {
        elements.loginError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.createForm.addEventListener('input', (event) => {
    if (['bandas', 'posiciones_por_banda', 'niveles'].includes(event.target.name)) renderPreview();
});

elements.levelTabs.addEventListener('click', (event) => {
    const button = event.target.closest('[data-level]');
    if (!button) return;
    state.selectedLevel = Number(button.dataset.level);
    renderPreview();
});

elements.preview.addEventListener('click', (event) => {
    const button = event.target.closest('[data-coordinate]');
    if (!button) return;
    const key = button.dataset.coordinate;
    state.disabled.has(key) ? state.disabled.delete(key) : state.disabled.add(key);
    renderPreview();
});

elements.reset.addEventListener('click', () => {
    state.disabled.clear();
    state.selectedLevel = 1;
    renderPreview();
});

elements.createForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.createError.textContent = '';
    const form = new FormData(elements.createForm);
    const payload = {
        nombre: String(form.get('nombre') || '').trim(),
        tipo: form.get('tipo'),
        bandas: Number(form.get('bandas')),
        posiciones_por_banda: Number(form.get('posiciones_por_banda')),
        niveles: Number(form.get('niveles')),
        posiciones_fuera_servicio: [...state.disabled].map((key) => {
            const [banda, posicion, nivel] = key.split(':').map(Number);
            return { banda, posicion, nivel };
        }),
    };

    setBusy(true, 'Creando cámara y posiciones…');
    try {
        const response = await api('/api/configuracion/camaras', { method: 'POST', body: JSON.stringify(payload) });
        toast(`${response.data.codigo} fue creada correctamente.`);
        elements.createForm.reset();
        elements.createForm.elements.bandas.value = 3;
        elements.createForm.elements.posiciones_por_banda.value = 4;
        elements.createForm.elements.niveles.value = 2;
        state.disabled.clear();
        state.selectedLevel = 1;
        renderPreview();
        await loadConfiguration();
    } catch (error) {
        elements.createError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.reload.addEventListener('click', async () => {
    setBusy(true, 'Actualizando cámaras…');
    try {
        await loadConfiguration();
        toast('Configuración actualizada.');
    } catch (error) {
        toast(error.message, true);
    } finally {
        setBusy(false);
    }
});

elements.logout.addEventListener('click', async () => {
    try {
        await api('/api/acceso-oficina', { method: 'DELETE' });
    } finally {
        clearSession();
    }
});

async function boot() {
    renderPreview();
    if (!state.token || !state.identity?.puede_configurar_camaras) return;
    showApp();
    setBusy(true, 'Cargando configuración…');
    try {
        await loadConfiguration();
    } catch (error) {
        if (error.status !== 401) toast(error.message, true);
    } finally {
        setBusy(false);
    }
}

void boot();
