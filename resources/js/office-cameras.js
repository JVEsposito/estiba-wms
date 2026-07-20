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
    accessesNav: byId('officeAccessesNav'),
    loadsNav: byId('officeLoadsNav'),
    materialsNav: byId('officeMaterialsNav'),
    prefrioNav: byId('officePrefrioNav'),
    moduleTabs: byId('configurationModuleTabs'),
    cameraModule: byId('cameraModuleButton'),
    dockModule: byId('dockModuleButton'),
    workspace: byId('officeWorkspace'),
    dockWorkspace: byId('dockWorkspace'),
    catalogEyebrow: byId('cameraCatalogEyebrow'),
    catalogTitle: byId('cameraCatalogTitle'),
    reload: byId('reloadOfficeButton'),
    cameraList: byId('officeCameraList'),
    nextCode: byId('nextCameraCode'),
    createForm: byId('createCameraForm'),
    createError: byId('createCameraError'),
    reset: byId('resetCameraButton'),
    cancelEdit: byId('cancelEditCameraButton'),
    deactivate: byId('deactivateCameraButton'),
    save: byId('saveCameraButton'),
    saveText: byId('saveCameraButtonText'),
    eyebrow: byId('configurationEyebrow'),
    title: byId('configurationTitle'),
    description: byId('configurationDescription'),
    codeLabel: byId('cameraCodeLabel'),
    preview: byId('cameraPreview'),
    levelTabs: byId('previewLevelTabs'),
    capacity: byId('previewCapacity'),
    active: byId('previewActive'),
    disabled: byId('previewDisabled'),
    loading: byId('officeLoading'),
    loadingText: byId('officeLoadingText'),
    toasts: byId('officeToasts'),
    dockList: byId('officeDockList'),
    reloadDocks: byId('reloadDocksButton'),
    dockConfiguration: byId('dockConfiguration'),
    dockForm: byId('dockForm'),
    dockFormError: byId('dockFormError'),
    dockFormEyebrow: byId('dockFormEyebrow'),
    dockFormTitle: byId('dockFormTitle'),
    dockFormDescription: byId('dockFormDescription'),
    nextDockCode: byId('nextDockCode'),
    cancelEditDock: byId('cancelEditDockButton'),
    saveDockText: byId('saveDockButtonText'),
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
    mode: 'create',
    editingCamera: null,
    originalDimensions: null,
    activeModule: 'cameras',
    docks: [],
    dockMode: 'create',
    editingDock: null,
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
    elements.loading.setAttribute('aria-hidden', String(!active));
    elements.app.setAttribute('aria-busy', String(active));
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
    switchConfigurationModule('cameras');
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
    elements.accessesNav.classList.toggle(
        'is-hidden',
        state.identity?.puede_administrar_accesos !== true,
    );
    elements.loadsNav.classList.toggle(
        'is-hidden',
        state.identity?.puede_consultar_cargas !== true,
    );
    elements.materialsNav.classList.toggle(
        'is-hidden',
        state.identity?.puede_consultar_despachos_materiales !== true,
    );
    elements.prefrioNav.classList.toggle(
        'is-hidden',
        state.identity?.puede_consultar_prefrio !== true,
    );
    const canManageDocks = userCanManageDocks();
    elements.moduleTabs.classList.toggle('is-hidden', !canManageDocks);
    if (!canManageDocks && state.activeModule === 'docks') {
        switchConfigurationModule('cameras');
    }
    applyCreationContentScope();
    const readOnly = state.identity?.puede_configurar_camaras !== true;
    elements.workspace.classList.toggle('is-read-only', readOnly);
    elements.catalogEyebrow.textContent = readOnly ? 'CONSULTA OPERACIONAL' : 'CONFIGURACIÓN';
    elements.catalogTitle.textContent = readOnly ? 'Disponibilidad de cámaras' : 'Cámaras creadas';
}

function userCanManageDocks() {
    return state.identity?.puede_gestionar_andenes === true
        || state.identity?.puede_administrar_camaras === true;
}

function switchConfigurationModule(module) {
    if (module === 'docks' && !userCanManageDocks()) return;

    state.activeModule = module;
    const showDocks = module === 'docks';
    elements.workspace.classList.toggle('is-hidden', showDocks);
    elements.dockWorkspace.classList.toggle('is-hidden', !showDocks);
    elements.cameraModule.classList.toggle('is-active', !showDocks);
    elements.dockModule.classList.toggle('is-active', showDocks);
    elements.cameraModule.setAttribute('aria-selected', String(!showDocks));
    elements.dockModule.setAttribute('aria-selected', String(showDocks));
}

function allowedCreationContent() {
    const canCreateProducts = state.identity?.puede_crear_camaras_productos === true;
    const canCreateMaterials = state.identity?.puede_crear_camaras_materiales === true;

    if (canCreateProducts && !canCreateMaterials) return 'productos';
    if (canCreateMaterials && !canCreateProducts) return 'materiales';

    return null;
}

function applyCreationContentScope() {
    const content = elements.createForm.elements.contenido;
    const forced = allowedCreationContent();

    for (const option of content.options) {
        option.hidden = forced !== null && option.value !== forced;
        option.disabled = forced !== null && option.value !== forced;
    }

    if (forced && state.mode === 'create') content.value = forced;
}

function statusText(value) {
    return String(value || '')
        .replaceAll('_', ' ')
        .replace(/^./, (character) => character.toUpperCase());
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

    const canConfigure = state.identity?.puede_configurar_camaras === true;
    if (!canConfigure) {
        elements.cameraList.innerHTML = state.cameras.map((camera) => {
            const occupied = Number(camera.ocupacion?.ocupadas || 0);
            const total = Number(camera.ocupacion?.total || 0);
            const available = Math.max(0, total - occupied);
            const percentage = total > 0 ? Math.min(100, (occupied / total) * 100) : 0;
            const session = camera.acceso?.sesion;
            const accessText = camera.acceso?.bloqueada
                ? `En edición por ${session?.usuario?.nombre || 'otro usuario'}`
                : 'Disponible para operación';

            return `
                <article class="camera-item camera-item--consultation">
                    <div><strong>${escapeHtml(camera.codigo)}</strong><span class="state-dot" title="Activa"></span></div>
                    <h3>${escapeHtml(camera.nombre)}</h3>
                    <p>${escapeHtml(statusText(camera.tipo))} · ${escapeHtml(statusText(camera.contenido))} · ${escapeHtml(accessText)}</p>
                    <div class="camera-availability">
                        <div><strong>${available}</strong><span>libres</span></div>
                        <div><strong>${occupied}</strong><span>ocupadas</span></div>
                        <div><strong>${total}</strong><span>totales</span></div>
                    </div>
                    <div class="camera-occupancy" aria-label="${escapeHtml(`${occupied} de ${total} posiciones ocupadas`)}">
                        <span style="width: ${percentage}%"></span>
                    </div>
                </article>
            `;
        }).join('');
        return;
    }

    const canAdminister = state.identity?.puede_administrar_camaras === true;
    const canSupervise = state.identity?.capacidades?.puede_supervisar === true;
    elements.cameraList.innerHTML = state.cameras.map((camera) => `
        <article class="camera-item${camera.estado === 'inactiva' ? ' is-inactive' : ''}">
            <div><strong>${escapeHtml(camera.codigo)}</strong><span class="state-dot${camera.estado === 'inactiva' ? ' is-inactive' : ''}" title="${camera.estado === 'inactiva' ? 'Inactiva' : 'Activa'}"></span></div>
            <h3>${escapeHtml(camera.nombre)}</h3>
            <p>${escapeHtml(statusText(camera.contenido))} · ${camera.dimensiones.bandas} bandas · ${camera.dimensiones.posiciones_por_banda} posiciones · ${camera.dimensiones.niveles} niveles</p>
            <div class="camera-item__capacity"><span>${camera.capacidad.activas} operativas</span><span>${camera.capacidad.ocupadas} ocupadas</span></div>
            ${(canAdminister || (canSupervise && camera.acceso?.bloqueada)) ? `<div class="camera-item__actions">${canAdminister ? `<button data-edit-camera="${camera.id}" type="button">Editar cámara</button>` : ''}${canSupervise && camera.acceso?.bloqueada ? `<button data-force-close-session="${camera.acceso.sesion?.id || ''}" type="button">Cerrar sesión forzosamente</button>` : ''}</div>` : ''}
        </article>
    `).join('');
}

function resetForm() {
    state.mode = 'create';
    state.editingCamera = null;
    state.originalDimensions = null;
    state.disabled.clear();
    state.selectedLevel = 1;
    elements.createForm.reset();
    elements.createForm.elements.bandas.value = 3;
    elements.createForm.elements.posiciones_por_banda.value = 4;
    elements.createForm.elements.niveles.value = 2;
    elements.createForm.elements.contenido.value = allowedCreationContent() || 'productos';
    applyCreationContentScope();
    elements.eyebrow.textContent = 'NUEVO PLANO';
    elements.title.textContent = 'Crear cámara';
    elements.description.textContent = 'Define la estructura y revisa cada banda antes de confirmar.';
    elements.codeLabel.textContent = 'PRÓXIMO CÓDIGO';
    elements.saveText.textContent = 'Crear cámara y posiciones';
    elements.cancelEdit.classList.add('is-hidden');
    elements.deactivate.classList.add('is-hidden');
    elements.createError.textContent = '';
    renderPreview();
}

function editForm(camera) {
    state.mode = 'edit';
    state.editingCamera = camera;
    state.originalDimensions = { ...camera.dimensiones };
    state.selectedLevel = 1;
    state.disabled = new Set((camera.posiciones_fuera_servicio || []).map((coordinate) => keyOf(
        coordinate.banda,
        coordinate.posicion,
        coordinate.nivel,
    )));
    elements.createForm.elements.nombre.value = camera.nombre;
    elements.createForm.elements.tipo.value = camera.tipo;
    elements.createForm.elements.contenido.value = camera.contenido;
    elements.createForm.elements.bandas.value = camera.dimensiones.bandas;
    elements.createForm.elements.posiciones_por_banda.value = camera.dimensiones.posiciones_por_banda;
    elements.createForm.elements.niveles.value = camera.dimensiones.niveles;
    elements.eyebrow.textContent = 'ADMINISTRAR PLANO';
    elements.title.textContent = `Editar ${camera.codigo}`;
    elements.description.textContent = 'Los cambios de tamaño conservan el historial de todas las posiciones retiradas.';
    elements.codeLabel.textContent = 'CÓDIGO INMUTABLE';
    elements.nextCode.textContent = camera.codigo;
    elements.saveText.textContent = 'Guardar cambios';
    elements.cancelEdit.classList.remove('is-hidden');
    elements.deactivate.classList.remove('is-hidden');
    elements.deactivate.textContent = camera.estado === 'activa' ? 'Desactivar cámara' : 'Reactivar cámara';
    elements.createError.textContent = '';
    renderPreview();
    document.querySelector('.configuration')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function loadCameraForEdit(id) {
    setBusy(true, 'Cargando plano de la cámara…');
    try {
        const response = await api(`/api/configuracion/camaras/${id}`);
        editForm(response.data);
    } catch (error) {
        toast(error.message, true);
    } finally {
        setBusy(false);
    }
}

async function loadConfiguration() {
    if (state.identity?.puede_configurar_camaras !== true) {
        const cameras = await api('/api/camaras');
        state.cameras = cameras.data;
        renderCameras();
        return;
    }

    const [cameras, next, operationalCameras] = await Promise.all([
        api('/api/configuracion/camaras'),
        api('/api/configuracion/camaras/siguiente-codigo'),
        api('/api/camaras'),
    ]);
    const operationalById = new Map(
        operationalCameras.data.map((camera) => [camera.id, camera]),
    );
    state.cameras = cameras.data.map((camera) => ({
        ...camera,
        acceso: operationalById.get(camera.id)?.acceso || null,
    }));
    elements.nextCode.textContent = next.data.codigo;
    renderCameras();
}

function suggestedDockCode() {
    const lastNumber = state.docks.reduce((highest, dock) => {
        const match = String(dock.codigo || '').match(/^AND-(\d+)$/i);
        return match ? Math.max(highest, Number(match[1])) : highest;
    }, 0);

    return `AND-${String(lastNumber + 1).padStart(2, '0')}`;
}

function renderDocks() {
    if (!state.docks.length) {
        elements.dockList.innerHTML = '<div class="empty-state">Aún no existen andenes. Crea el primero desde el formulario.</div>';
        return;
    }

    elements.dockList.innerHTML = state.docks.map((dock) => `
        <article class="camera-item dock-item${dock.activo ? '' : ' is-inactive'}">
            <div><strong>${escapeHtml(dock.codigo)}</strong><span class="state-dot${dock.activo ? '' : ' is-inactive'}" title="${dock.activo ? 'Activo' : 'Inactivo'}"></span></div>
            <h3>${escapeHtml(dock.nombre)}</h3>
            <p>${dock.codigo_externo ? `Código externo: ${escapeHtml(dock.codigo_externo)}` : 'Sin código externo'}</p>
            <div class="dock-item__status"><span>${dock.activo ? 'Disponible para cargas' : 'Fuera de operación'}</span></div>
            <div class="camera-item__actions"><button data-edit-dock="${dock.id}" type="button">Editar andén</button></div>
        </article>
    `).join('');
}

function resetDockForm() {
    state.dockMode = 'create';
    state.editingDock = null;
    elements.dockForm.reset();
    elements.dockForm.elements.codigo.value = suggestedDockCode();
    elements.dockForm.elements.activo.checked = true;
    elements.dockFormEyebrow.textContent = 'NUEVO ANDÉN';
    elements.dockFormTitle.textContent = 'Crear andén';
    elements.dockFormDescription.textContent = 'Registra los puntos físicos donde se concentran y despachan las cargas.';
    elements.nextDockCode.parentElement.querySelector('span').textContent = 'CÓDIGO SUGERIDO';
    elements.nextDockCode.textContent = suggestedDockCode();
    elements.saveDockText.textContent = 'Crear andén';
    elements.cancelEditDock.classList.add('is-hidden');
    elements.dockFormError.textContent = '';
}

function editDockForm(dock) {
    state.dockMode = 'edit';
    state.editingDock = dock;
    elements.dockForm.elements.codigo.value = dock.codigo;
    elements.dockForm.elements.nombre.value = dock.nombre;
    elements.dockForm.elements.codigo_externo.value = dock.codigo_externo || '';
    elements.dockForm.elements.activo.checked = dock.activo;
    elements.dockFormEyebrow.textContent = 'ADMINISTRAR ANDÉN';
    elements.dockFormTitle.textContent = `Editar ${dock.codigo}`;
    elements.dockFormDescription.textContent = 'Actualiza su identificación o cambia su disponibilidad operacional.';
    elements.nextDockCode.parentElement.querySelector('span').textContent = 'ESTADO ACTUAL';
    elements.nextDockCode.textContent = dock.activo ? 'ACTIVO' : 'INACTIVO';
    elements.saveDockText.textContent = 'Guardar cambios';
    elements.cancelEditDock.classList.remove('is-hidden');
    elements.dockFormError.textContent = '';
    elements.dockConfiguration.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function loadDocks() {
    const response = await api('/api/andenes?incluir_inactivos=1');
    state.docks = response.data;
    renderDocks();

    if (state.dockMode === 'edit' && state.editingDock) {
        const updated = state.docks.find((dock) => dock.id === state.editingDock.id);
        updated ? editDockForm(updated) : resetDockForm();
        return;
    }

    resetDockForm();
}

async function openConfigurationModule(module) {
    switchConfigurationModule(module);
    if (module !== 'docks') return;

    setBusy(true, 'Cargando andenes…');
    try {
        await loadDocks();
    } catch (error) {
        toast(error.message, true);
    } finally {
        setBusy(false);
    }
}

elements.loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.loginError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.loginForm));
    setBusy(true, 'Validando acceso…');
    try {
        const payload = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify(data) });
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
    if (state.mode === 'edit' && state.editingCamera) {
        editForm(state.editingCamera);
        return;
    }
    state.disabled.clear();
    state.selectedLevel = 1;
    renderPreview();
});

elements.cancelEdit.addEventListener('click', async () => {
    resetForm();
    await loadConfiguration();
});

elements.cameraList.addEventListener('click', (event) => {
    const forceButton = event.target.closest('[data-force-close-session]');
    if (forceButton) {
        const sessionId = forceButton.dataset.forceCloseSession;
        const reason = window.prompt('Indica el motivo del cierre forzoso:');
        if (reason === null) return;
        const normalizedReason = reason.trim();
        if (normalizedReason.length < 3) {
            toast('El motivo debe contener al menos 3 caracteres.', true);
            return;
        }
        void (async () => {
            setBusy(true, 'Cerrando sesión…');
            try {
                await api(`/api/sesiones/${sessionId}/cerrar-forzosamente`, {
                    method: 'POST',
                    body: JSON.stringify({ motivo: normalizedReason }),
                });
                await loadConfiguration();
                toast('Sesión cerrada y cámara liberada.');
            } catch (error) {
                toast(error.message, true);
            } finally {
                setBusy(false);
            }
        })();
        return;
    }

    const button = event.target.closest('[data-edit-camera]');
    if (!button) return;
    void loadCameraForEdit(button.dataset.editCamera);
});

elements.createForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.createError.textContent = '';
    const form = new FormData(elements.createForm);
    const payload = {
        nombre: String(form.get('nombre') || '').trim(),
        tipo: form.get('tipo'),
        contenido: form.get('contenido'),
        bandas: Number(form.get('bandas')),
        posiciones_por_banda: Number(form.get('posiciones_por_banda')),
        niveles: Number(form.get('niveles')),
        posiciones_fuera_servicio: [...state.disabled].map((key) => {
            const [banda, posicion, nivel] = key.split(':').map(Number);
            return { banda, posicion, nivel };
        }),
    };

    if (state.mode === 'edit' && state.originalDimensions) {
        const shrinks = payload.bandas < state.originalDimensions.bandas
            || payload.posiciones_por_banda < state.originalDimensions.posiciones_por_banda
            || payload.niveles < state.originalDimensions.niveles;
        if (shrinks && !window.confirm('El nuevo plano es menor. Las posiciones retiradas conservarán su historial y una posición ocupada impedirá el cambio. ¿Deseas continuar?')) {
            return;
        }
    }

    setBusy(true, state.mode === 'edit' ? 'Guardando cambios…' : 'Creando cámara y posiciones…');
    try {
        const editing = state.mode === 'edit';
        const path = editing
            ? `/api/configuracion/camaras/${state.editingCamera.id}`
            : '/api/configuracion/camaras';
        const response = await api(path, {
            method: editing ? 'PUT' : 'POST',
            body: JSON.stringify(payload),
        });
        toast(`${response.data.codigo} fue ${editing ? 'actualizada' : 'creada'} correctamente.`);
        resetForm();
        await loadConfiguration();
    } catch (error) {
        elements.createError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.deactivate.addEventListener('click', async () => {
    if (!state.editingCamera) return;
    const activate = state.editingCamera.estado === 'inactiva';
    const message = activate
        ? `¿Reactivar ${state.editingCamera.codigo} para la operación en tablets?`
        : `¿Desactivar ${state.editingCamera.codigo}? Dejará de aparecer en tablets y no puede contener folios.`;
    if (!window.confirm(message)) return;

    const form = new FormData(elements.createForm);
    setBusy(true, activate ? 'Reactivando cámara…' : 'Desactivando cámara…');
    try {
        let response;
        if (activate) {
            response = await api(`/api/configuracion/camaras/${state.editingCamera.id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    nombre: String(form.get('nombre') || '').trim(),
                    tipo: form.get('tipo'),
                    contenido: form.get('contenido'),
                    bandas: Number(form.get('bandas')),
                    posiciones_por_banda: Number(form.get('posiciones_por_banda')),
                    niveles: Number(form.get('niveles')),
                    estado: 'activa',
                    posiciones_fuera_servicio: [...state.disabled].map((key) => {
                        const [banda, posicion, nivel] = key.split(':').map(Number);
                        return { banda, posicion, nivel };
                    }),
                }),
            });
        } else {
            response = await api(`/api/configuracion/camaras/${state.editingCamera.id}`, { method: 'DELETE' });
        }
        toast(`${response.data.codigo} fue ${activate ? 'reactivada' : 'desactivada'} correctamente.`);
        resetForm();
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
        toast(state.identity?.puede_configurar_camaras === true
            ? 'Configuración actualizada.'
            : 'Disponibilidad de cámaras actualizada.');
    } catch (error) {
        toast(error.message, true);
    } finally {
        setBusy(false);
    }
});

elements.cameraModule.addEventListener('click', () => {
    void openConfigurationModule('cameras');
});

elements.dockModule.addEventListener('click', () => {
    void openConfigurationModule('docks');
});

elements.reloadDocks.addEventListener('click', async () => {
    setBusy(true, 'Actualizando andenes…');
    try {
        await loadDocks();
        toast('Listado de andenes actualizado.');
    } catch (error) {
        toast(error.message, true);
    } finally {
        setBusy(false);
    }
});

elements.dockList.addEventListener('click', (event) => {
    const button = event.target.closest('[data-edit-dock]');
    if (!button) return;

    const dock = state.docks.find((item) => item.id === button.dataset.editDock);
    if (dock) editDockForm(dock);
});

elements.cancelEditDock.addEventListener('click', () => {
    resetDockForm();
});

elements.dockForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.dockFormError.textContent = '';

    const form = new FormData(elements.dockForm);
    const payload = {
        codigo: String(form.get('codigo') || '').trim().toUpperCase(),
        nombre: String(form.get('nombre') || '').trim(),
        codigo_externo: String(form.get('codigo_externo') || '').trim() || null,
        activo: elements.dockForm.elements.activo.checked,
    };
    const editing = state.dockMode === 'edit' && state.editingDock;

    if (editing && state.editingDock.activo && !payload.activo
        && !window.confirm(`${state.editingDock.codigo} dejará de estar disponible para nuevas operaciones. ¿Deseas continuar?`)) {
        return;
    }

    setBusy(true, editing ? 'Guardando andén…' : 'Creando andén…');
    try {
        const response = await api(
            editing
                ? `/api/administracion/andenes/${state.editingDock.id}`
                : '/api/administracion/andenes',
            {
                method: editing ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            },
        );
        toast(`${response.data.codigo} fue ${editing ? 'actualizado' : 'creado'} correctamente.`);
        state.dockMode = 'create';
        state.editingDock = null;
        await loadDocks();
    } catch (error) {
        elements.dockFormError.textContent = error.message;
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
    resetDockForm();
    if (!state.token) return;
    showApp();
    setBusy(
        true,
        state.identity?.puede_configurar_camaras === true
            ? 'Cargando configuración…'
            : 'Cargando disponibilidad de cámaras…',
    );
    try {
        await loadConfiguration();
    } catch (error) {
        if (error.status !== 401) toast(error.message, true);
    } finally {
        setBusy(false);
    }
}

void boot();
