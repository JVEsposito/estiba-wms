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
    lastDeviceAccess: byId('lastDeviceAccess'),
    usersSummary: byId('usersSummary'),
    devicesSummary: byId('devicesSummary'),
    usersTableBody: byId('usersTableBody'),
    devicesTableBody: byId('devicesTableBody'),
    userForm: byId('createUserForm'),
    userError: byId('createUserError'),
    deviceForm: byId('createDeviceForm'),
    deviceError: byId('createDeviceError'),
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

async function loadAccesses() {
    const response = await api('/api/administracion/accesos');
    state.users = response.usuarios;
    state.devices = response.dispositivos;
    renderUsers();
    renderDevices();
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
