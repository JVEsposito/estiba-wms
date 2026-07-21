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
    reload: byId('reloadAccessesButton'),
    activeUsers: byId('activeUsersCount'),
    activeDevices: byId('activeDevicesCount'),
    activeSeason: byId('activeSeasonCode'),
    lastDeviceAccess: byId('lastDeviceAccess'),
    seasonsSummary: byId('seasonsSummary'),
    seasonsTableBody: byId('seasonsTableBody'),
    usersSummary: byId('usersSummary'),
    devicesSummary: byId('devicesSummary'),
    usersTableBody: byId('usersTableBody'),
    devicesTableBody: byId('devicesTableBody'),
    userForm: byId('createUserForm'),
    userError: byId('createUserError'),
    deviceForm: byId('createDeviceForm'),
    deviceError: byId('createDeviceError'),
    seasonForm: byId('seasonForm'),
    seasonError: byId('seasonError'),
    seasonCancel: byId('cancelSeasonEdit'),
    migrationForm: byId('seasonMigrationForm'),
    migrationTitle: byId('seasonMigrationTitle'),
    migrationError: byId('seasonMigrationError'),
    migrationCancel: byId('cancelSeasonMigration'),
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
    users: [],
    devices: [],
    seasons: [],
};

class ApiError extends Error {
    constructor(message, status, data = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
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

function userValidationMessage(data) {
    const password = String(data.password || '');

    if (password.length < 10) return 'La contraseña debe tener al menos 10 caracteres.';
    if (!/\p{L}/u.test(password) || !/\p{N}/u.test(password)) {
        return 'La contraseña debe contener al menos una letra y un número.';
    }
    if (password !== String(data.password_confirmation || '')) {
        return 'La confirmación de la contraseña no coincide.';
    }

    return null;
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
        throw new ApiError(errorMessage(data, 'No fue posible completar la operación.'), response.status, data);
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
    state.users = [];
    state.devices = [];
    state.seasons = [];
    localStorage.removeItem(keys.token);
    localStorage.removeItem(keys.identity);
    elements.app.classList.add('is-hidden');
    elements.access.classList.remove('is-hidden');
}

function showApp() {
    elements.access.classList.add('is-hidden');
    elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Administrador';
    elements.userName.textContent = name;
    elements.userRole.textContent = statusText(state.identity?.rol || 'administrador');
    elements.initials.textContent = name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function statusText(value) {
    return String(value || '')
        .replaceAll('_', ' ')
        .replace(/^./, (character) => character.toUpperCase());
}

function formatDate(value, fallback = 'Sin accesos') {
    if (!value) return fallback;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return fallback;
    return new Intl.DateTimeFormat('es-CL', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(date);
}

function statusBadge(active) {
    return `<span class="access-status access-status--${active ? 'active' : 'inactive'}">${active ? 'Activo' : 'Inactivo'}</span>`;
}

function renderUsers() {
    elements.activeUsers.textContent = String(state.users.filter((user) => user.activo).length);
    elements.usersSummary.textContent = `${state.users.length} ${state.users.length === 1 ? 'registrado' : 'registrados'}`;

    if (!state.users.length) {
        elements.usersTableBody.innerHTML = '<tr class="admin-empty"><td colspan="3">No existen usuarios registrados.</td></tr>';
        return;
    }

    elements.usersTableBody.innerHTML = state.users.map((user) => `
        <tr>
            <td><strong>${escapeHtml(user.nombre)}</strong><small>${escapeHtml(user.email)}</small></td>
            <td><span class="role-badge">${escapeHtml(statusText(user.rol))}</span></td>
            <td>${statusBadge(user.activo)}</td>
        </tr>
    `).join('');
}

function renderDevices() {
    const active = state.devices.filter((device) => device.activo);
    const accesses = state.devices
        .map((device) => device.ultimo_acceso_at)
        .filter(Boolean)
        .sort()
        .reverse();

    elements.activeDevices.textContent = String(active.length);
    elements.lastDeviceAccess.textContent = formatDate(accesses[0]);
    elements.devicesSummary.textContent = `${state.devices.length} ${state.devices.length === 1 ? 'registrada' : 'registradas'}`;

    if (!state.devices.length) {
        elements.devicesTableBody.innerHTML = '<tr class="admin-empty"><td colspan="3">No existen tablets registradas.</td></tr>';
        return;
    }

    elements.devicesTableBody.innerHTML = state.devices.map((device) => `
        <tr>
            <td><strong>${escapeHtml(device.codigo)}</strong><small>${escapeHtml(device.nombre)} · ${escapeHtml(statusText(device.plataforma))}</small></td>
            <td>${escapeHtml(formatDate(device.ultimo_acceso_at))}</td>
            <td>${statusBadge(device.activo)}</td>
        </tr>
    `).join('');
}

function dateOnly(value, fallback = 'Sin fecha') {
    if (!value) return fallback;
    const [year, month, day] = String(value).slice(0, 10).split('-');
    return year && month && day ? `${day}-${month}-${year}` : fallback;
}

function resetSeasonForm() {
    elements.seasonForm.reset();
    elements.seasonForm.elements.id.value = '';
    elements.seasonError.textContent = '';
    elements.seasonCancel.classList.add('is-hidden');
}

function resetMigrationForm() {
    elements.migrationForm.reset();
    elements.migrationForm.elements.temporada_destino_id.value = '';
    elements.migrationError.textContent = '';
    elements.migrationForm.classList.add('is-hidden');
}

function openMigrationForm(destinationId) {
    const destination = state.seasons.find((season) => season.id === destinationId);
    if (!destination) return;
    const sources = state.seasons.filter((season) => season.id !== destinationId);
    elements.migrationForm.reset();
    elements.migrationForm.elements.temporada_destino_id.value = destinationId;
    elements.migrationTitle.textContent = `Migrar datos hacia ${destination.codigo}`;
    elements.migrationForm.elements.temporada_origen_id.innerHTML = sources.map((season) =>
        `<option value="${season.id}"${season.activa ? ' selected' : ''}>${escapeHtml(season.codigo)} · ${escapeHtml(season.nombre)}${season.activa ? ' (activa)' : ''}</option>`,
    ).join('');
    elements.migrationForm.elements.copiar_catalogo_validacion.checked = true;
    elements.migrationForm.elements.copiar_catalogo_materiales.checked = true;
    elements.migrationForm.elements.activar_destino.checked = false;
    elements.migrationForm.elements.migrar_inventario_materiales.checked = false;
    elements.migrationError.textContent = '';
    elements.migrationForm.classList.remove('is-hidden');
    elements.migrationForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function renderSeasons() {
    const active = state.seasons.find((season) => season.activa);
    elements.activeSeason.textContent = active?.codigo || '—';
    elements.seasonsSummary.textContent = `${state.seasons.length} ${state.seasons.length === 1 ? 'registrada' : 'registradas'}`;

    if (!state.seasons.length) {
        elements.seasonsTableBody.innerHTML = '<tr class="admin-empty"><td colspan="4">No existen temporadas. Crea la primera configuración transversal.</td></tr>';
        return;
    }

    elements.seasonsTableBody.innerHTML = state.seasons.map((season) => `
        <tr>
            <td><strong>${escapeHtml(season.codigo)} · ${escapeHtml(season.nombre)}</strong><small>Versión de catálogo ${Number(season.version_catalogo || 1)} · ${Number(season.migraciones_recibidas || 0)} migraciones recibidas</small></td>
            <td>${escapeHtml(dateOnly(season.fecha_inicio))} → ${escapeHtml(dateOnly(season.fecha_fin))}</td>
            <td>${statusBadge(season.activa)}</td>
            <td><div class="admin-season-actions"><button data-edit-season="${season.id}" type="button">Editar</button>${season.activa ? '' : `<button data-migrate-season="${season.id}" type="button">Migrar datos</button><button data-activate-season="${season.id}" type="button">Activar</button>`}</div></td>
        </tr>
    `).join('');
}

async function loadAccesses() {
    const [response, seasons] = await Promise.all([
        api('/api/administracion/accesos'),
        api('/api/administracion/temporadas'),
    ]);
    state.users = response.usuarios;
    state.devices = response.dispositivos;
    state.seasons = seasons.data || [];
    renderUsers();
    renderDevices();
    renderSeasons();
}

elements.loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.loginError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.loginForm));
    setBusy(true, 'Validando acceso administrativo…');
    try {
        const payload = await api('/api/acceso-oficina', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        state.token = payload.token;
        if (payload.usuario.puede_administrar_accesos !== true) {
            await api('/api/acceso-oficina', { method: 'DELETE' });
            clearSession();
            throw new ApiError('Tu perfil no puede administrar usuarios ni tablets.', 403);
        }
        persistSession(payload);
        showApp();
        await loadAccesses();
    } catch (error) {
        elements.loginError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.userForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.userError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.userForm));
    const validationMessage = userValidationMessage(data);
    if (validationMessage) {
        elements.userError.textContent = validationMessage;
        return;
    }
    setBusy(true, 'Creando usuario…');
    try {
        const response = await api('/api/administracion/usuarios', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        elements.userForm.reset();
        await loadAccesses();
        toast(`${response.usuario.nombre} fue creado correctamente.`);
    } catch (error) {
        elements.userError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.deviceForm.elements.codigo.addEventListener('input', (event) => {
    const cursor = event.target.selectionStart;
    event.target.value = event.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
    event.target.setSelectionRange(cursor, cursor);
});

elements.deviceForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.deviceError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.deviceForm));
    setBusy(true, 'Autorizando tablet…');
    try {
        const response = await api('/api/administracion/dispositivos', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        elements.deviceForm.reset();
        await loadAccesses();
        toast(`${response.dispositivo.codigo} quedó autorizada.`);
    } catch (error) {
        elements.deviceError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.seasonForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.seasonError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.seasonForm));
    const id = data.id;
    delete data.id;
    data.activa = elements.seasonForm.elements.activa.checked;
    setBusy(true, 'Guardando temporada transversal…');
    try {
        await api(id ? `/api/administracion/temporadas/${id}` : '/api/administracion/temporadas', {
            method: id ? 'PUT' : 'POST',
            body: JSON.stringify(data),
        });
        resetSeasonForm();
        await loadAccesses();
        toast('La temporada quedó disponible para todas las oficinas.');
    } catch (error) {
        elements.seasonError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.seasonsTableBody.addEventListener('click', async (event) => {
    const edit = event.target.closest('[data-edit-season]');
    const activate = event.target.closest('[data-activate-season]');
    const migrate = event.target.closest('[data-migrate-season]');
    if (edit) {
        const season = state.seasons.find((candidate) => candidate.id === edit.dataset.editSeason);
        if (!season) return;
        for (const field of ['id', 'codigo', 'nombre', 'fecha_inicio', 'fecha_fin']) {
            elements.seasonForm.elements[field].value = season[field] || '';
        }
        elements.seasonForm.elements.activa.checked = season.activa;
        elements.seasonCancel.classList.remove('is-hidden');
        elements.seasonForm.elements.codigo.focus();
    }
    if (migrate) openMigrationForm(migrate.dataset.migrateSeason);
    if (activate) {
        setBusy(true, 'Activando temporada para todas las oficinas…');
        try {
            await api(`/api/administracion/temporadas/${activate.dataset.activateSeason}/activar`, { method: 'POST' });
            resetSeasonForm();
            await loadAccesses();
            toast('Temporada global activada.');
        } catch (error) {
            toast(error.message, true);
        } finally {
            setBusy(false);
        }
    }
});

elements.migrationForm.elements.migrar_inventario_materiales.addEventListener('change', (event) => {
    if (!event.target.checked) return;
    elements.migrationForm.elements.copiar_catalogo_materiales.checked = true;
    elements.migrationForm.elements.activar_destino.checked = true;
});

elements.migrationForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.migrationError.textContent = '';
    const form = elements.migrationForm.elements;
    const destinationId = form.temporada_destino_id.value;
    const data = {
        temporada_origen_id: form.temporada_origen_id.value,
        copiar_catalogo_validacion: form.copiar_catalogo_validacion.checked,
        copiar_catalogo_materiales: form.copiar_catalogo_materiales.checked,
        migrar_inventario_materiales: form.migrar_inventario_materiales.checked,
        activar_destino: form.activar_destino.checked,
    };
    if (!data.copiar_catalogo_validacion && !data.copiar_catalogo_materiales && !data.migrar_inventario_materiales) {
        elements.migrationError.textContent = 'Selecciona al menos un catálogo o el inventario de bodega.';
        return;
    }
    if (data.migrar_inventario_materiales && !window.confirm('Se trasladará todo el inventario vivo y se activará la temporada de destino para todas las oficinas. ¿Deseas continuar?')) return;

    setBusy(true, 'Migrando datos entre temporadas…');
    try {
        const response = await api(`/api/administracion/temporadas/${destinationId}/migrar`, {
            method: 'POST',
            body: JSON.stringify(data),
        });
        const inventory = response.data.resumen.inventario;
        resetMigrationForm();
        await loadAccesses();
        toast(`Migración completada: ${inventory.folios} folios de bodega trasladados.`);
    } catch (error) {
        elements.migrationError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.migrationCancel.addEventListener('click', resetMigrationForm);

elements.seasonCancel.addEventListener('click', resetSeasonForm);

elements.reload.addEventListener('click', async () => {
    setBusy(true, 'Actualizando accesos…');
    try {
        await loadAccesses();
        toast('Listados actualizados.');
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
    if (!state.token || state.identity?.puede_administrar_accesos !== true) {
        if (state.token) {
            clearSession();
            elements.loginError.textContent = 'Inicia sesión con una cuenta administradora.';
        }
        return;
    }
    showApp();
    setBusy(true, 'Cargando usuarios y tablets…');
    try {
        await loadAccesses();
    } catch (error) {
        if (error.status !== 401) toast(error.message, true);
    } finally {
        setBusy(false);
    }
}

void boot();
