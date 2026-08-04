const byId = (id) => document.getElementById(id);

const elements = {
    access: byId('officeAccess'),
    app: byId('officeApp'),
    login: byId('officeLoginForm'),
    loginError: byId('officeLoginError'),
    userName: byId('officeUserName'),
    userRole: byId('officeUserRole'),
    initials: byId('officeInitials'),
    logout: byId('officeLogoutButton'),
    reload: byId('reloadInventoryButton'),
    cards: byId('inventoryCards'),
    connectionRows: byId('inventoryConnectionRows'),
    connectionCount: byId('inventoryConnectionCount'),
    loading: byId('officeLoading'),
    loadingText: byId('officeLoadingText'),
    toasts: byId('officeToasts'),
    managementNav: byId('officeManagementNav'),
    romanaNav: byId('officeRomanaNav'),
    rawMaterialNav: byId('officeRawMaterialNav'),
    camerasNav: byId('officeCamerasNav'),
    loadsNav: byId('officeLoadsNav'),
    materialsNav: byId('officeMaterialsNav'),
    prefrioNav: byId('officePrefrioNav'),
    accessesNav: byId('officeAccessesNav'),
};

const keys = {
    token: 'estiba_wms_office_token',
    identity: 'estiba_wms_office_identity',
};

const state = {
    token: localStorage.getItem(keys.token),
    identity: readJson(keys.identity),
    inventoryType: elements.app?.dataset.inventoryType || '',
    types: [],
    connections: [],
    busy: false,
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

function humanize(value) {
    return String(value || '')
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/^./, (character) => character.toUpperCase());
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
        throw new ApiError('No fue posible conectar con el servidor.', 0);
    }

    const data = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401 && path !== '/api/acceso-oficina') clearSession();
        throw new ApiError(errorMessage(data, 'No fue posible completar la consulta.'), response.status);
    }

    return data;
}

async function download(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', '*/*');
    if (state.token) headers.set('Authorization', `Bearer ${state.token}`);

    let response;
    try {
        response = await fetch(path, { ...options, headers });
    } catch {
        throw new ApiError('No fue posible conectar con el servidor.', 0);
    }

    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        if (response.status === 401) clearSession();
        throw new ApiError(errorMessage(data, 'No fue posible generar el archivo.'), response.status);
    }

    const blob = await response.blob();
    const disposition = response.headers.get('Content-Disposition') || '';
    const filenameMatch = disposition.match(/filename="?([^";]+)"?/i);
    const filename = filenameMatch?.[1] || 'Existencia.xlsx';
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);

    return filename;
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
    state.types = [];
    state.connections = [];
    localStorage.removeItem(keys.token);
    localStorage.removeItem(keys.identity);
    elements.app.classList.add('is-hidden');
    elements.access.classList.remove('is-hidden');
}

function showApp() {
    const name = state.identity?.nombre || 'Usuario';
    elements.access.classList.add('is-hidden');
    elements.app.classList.remove('is-hidden');
    elements.userName.textContent = name;
    elements.userRole.textContent = humanize(state.identity?.rol || 'oficina');
    elements.initials.textContent = name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

    elements.managementNav.classList.toggle('is-hidden', state.identity?.puede_consultar_panel_gerencial !== true);
    elements.romanaNav.classList.toggle('is-hidden', state.identity?.puede_consultar_romana !== true);
    elements.rawMaterialNav.classList.toggle('is-hidden', state.identity?.puede_consultar_materia_prima !== true);
    elements.camerasNav.classList.toggle('is-hidden', state.identity?.ambito_camaras === 'ninguno');
    elements.loadsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_cargas !== true);
    elements.materialsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_despachos_materiales !== true);
    elements.prefrioNav.classList.toggle('is-hidden', state.identity?.puede_consultar_prefrio !== true);
    elements.accessesNav.classList.toggle('is-hidden', state.identity?.puede_administrar_accesos !== true);
}

function setBusy(active, message = 'Preparando existencia…') {
    state.busy = active;
    elements.loadingText.textContent = message;
    elements.loading.classList.toggle('is-hidden', !active);
    elements.loading.setAttribute('aria-hidden', String(!active));
    elements.reload.disabled = active;
    elements.cards.querySelectorAll('button').forEach((button) => {
        button.disabled = active;
    });
}

function toast(message, error = false) {
    const item = document.createElement('div');
    item.className = `toast${error ? ' toast--error' : ''}`;
    item.textContent = message;
    elements.toasts.append(item);
    window.setTimeout(() => item.remove(), 4500);
}

function formatDate(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat('es-CL', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function typeAppearance(type) {
    if (type === 'materiales') return { icon: '▦', modifier: 'inventory-card--materials' };
    if (type === 'materia-prima') return { icon: '⌁', modifier: 'inventory-card--raw' };
    return { icon: '◇', modifier: 'inventory-card--product' };
}

function renderCards() {
    if (!state.types.length) {
        elements.cards.innerHTML = '<div class="inventory-empty"><strong>Tu perfil no posee acceso a esta existencia.</strong><p>Solicita al administrador que revise el área y los permisos asignados.</p></div>';
        return;
    }

    elements.cards.innerHTML = state.types.map((type) => {
        const appearance = typeAppearance(type.tipo);
        const count = Number(type.conexiones_activas || 0);
        return `
            <article class="inventory-card ${appearance.modifier}">
                <div class="inventory-card__top">
                    <span class="inventory-card__icon" aria-hidden="true">${appearance.icon}</span>
                    <span class="inventory-card__connection-count">${count} conexión${count === 1 ? '' : 'es'} activa${count === 1 ? '' : 's'}</span>
                </div>
                <h2>${escapeHtml(type.titulo)}</h2>
                <p>${escapeHtml(type.descripcion)}</p>
                <div class="inventory-card__actions">
                    <button class="primary-button" data-action="static" data-type="${escapeHtml(type.tipo)}" type="button">Descargar corte XLSX</button>
                    <button class="secondary-button inventory-card__connected" data-action="connected" data-type="${escapeHtml(type.tipo)}" type="button">Crear Excel autoactualizable</button>
                </div>
            </article>
        `;
    }).join('');

    elements.cards.querySelectorAll('[data-action]').forEach((button) => {
        button.addEventListener('click', () => void handleDownload(button.dataset.type, button.dataset.action));
    });
}

function renderConnections() {
    elements.connectionCount.textContent = String(state.connections.filter((item) => item.vigente).length);
    if (!state.connections.length) {
        elements.connectionRows.innerHTML = '<tr><td colspan="6">Todavía no se han emitido conexiones de Excel.</td></tr>';
        return;
    }

    elements.connectionRows.innerHTML = state.connections.map((connection) => `
        <tr>
            <td>${escapeHtml(typeLabel(connection.tipo))}</td>
            <td>${escapeHtml(formatDate(connection.created_at))}</td>
            <td>${escapeHtml(formatDate(connection.ultimo_uso_at))}</td>
            <td>${escapeHtml(formatDate(connection.expira_at))}</td>
            <td><span class="inventory-status ${connection.vigente ? 'inventory-status--active' : 'inventory-status--inactive'}">${connection.vigente ? 'Vigente' : 'Revocada / vencida'}</span></td>
            <td>${connection.vigente ? `<button class="inventory-revoke" data-revoke="${escapeHtml(connection.id)}" type="button">Revocar</button>` : ''}</td>
        </tr>
    `).join('');

    elements.connectionRows.querySelectorAll('[data-revoke]').forEach((button) => {
        button.addEventListener('click', () => void revokeConnection(button.dataset.revoke));
    });
}

function typeLabel(type) {
    return state.types.find((item) => item.tipo === type)?.titulo || humanize(type);
}

async function handleDownload(type, action) {
    if (state.busy) return;
    const connected = action === 'connected';
    setBusy(true, connected ? 'Creando conexión segura para Excel…' : 'Generando corte XLSX…');
    try {
        const filename = connected
            ? await download(`/api/existencias/${encodeURIComponent(type)}/conexion-excel`, { method: 'POST' })
            : await download(`/api/existencias/${encodeURIComponent(type)}/corte`);
        toast(connected
            ? `${filename} creado. Ábrelo en Excel y guarda el libro como XLSX.`
            : `${filename} descargado correctamente.`);
        await loadInventory(false);
    } catch (reason) {
        toast(reason.message || 'No fue posible generar el archivo.', true);
    } finally {
        setBusy(false);
    }
}

async function revokeConnection(id) {
    if (state.busy || !id) return;
    if (!window.confirm('La próxima actualización de este archivo Excel dejará de funcionar. ¿Revocar conexión?')) return;

    setBusy(true, 'Revocando conexión…');
    try {
        await api(`/api/existencias/conexiones/${encodeURIComponent(id)}/revocar`, { method: 'POST' });
        toast('Conexión revocada.');
        await loadInventory(false);
    } catch (reason) {
        toast(reason.message || 'No fue posible revocar la conexión.', true);
    } finally {
        setBusy(false);
    }
}

async function loadInventory(showLoading = true) {
    if (showLoading) setBusy(true, 'Consultando existencias autorizadas…');
    try {
        const query = new URLSearchParams({ tipo: state.inventoryType });
        const payload = await api(`/api/existencias?${query.toString()}`);
        state.types = payload.data || [];
        state.connections = payload.conexiones || [];
        showApp();
        renderCards();
        renderConnections();
    } catch (reason) {
        if (reason.status !== 401) toast(reason.message || 'No fue posible cargar existencias.', true);
    } finally {
        if (showLoading) setBusy(false);
    }
}

elements.login.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.loginError.textContent = '';
    const form = new FormData(elements.login);
    setBusy(true, 'Validando acceso…');
    try {
        const payload = await api('/api/acceso-oficina', {
            method: 'POST',
            body: JSON.stringify({
                email: form.get('email'),
                password: form.get('password'),
            }),
        });
        persistSession(payload);
        await loadInventory(false);
    } catch (reason) {
        elements.loginError.textContent = reason.message || 'No fue posible iniciar sesión.';
    } finally {
        setBusy(false);
    }
});

elements.logout.addEventListener('click', async () => {
    try {
        await api('/api/acceso-oficina', { method: 'DELETE' });
    } catch {
        // La sesión local siempre se cierra aunque el servidor no responda.
    }
    clearSession();
});

elements.reload.addEventListener('click', () => void loadInventory());

if (state.token && state.identity) {
    void loadInventory();
} else {
    clearSession();
}
