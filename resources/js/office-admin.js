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
    activeClients: byId('activeClientsCount'),
    activeSeason: byId('activeSeasonCode'),
    lastDeviceAccess: byId('lastDeviceAccess'),
    seasonsSummary: byId('seasonsSummary'),
    seasonsTableBody: byId('seasonsTableBody'),
    clientsSummary: byId('globalClientsSummary'),
    clientsTableBody: byId('globalClientsTableBody'),
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
    clientForm: byId('globalClientForm'),
    clientError: byId('globalClientError'),
    clientCancel: byId('cancelGlobalClientEdit'),
    migrationForm: byId('seasonMigrationForm'),
    migrationTitle: byId('seasonMigrationTitle'),
    migrationError: byId('seasonMigrationError'),
    migrationCancel: byId('cancelSeasonMigration'),
    resetDialog: byId('operationalResetDialog'),
    resetForm: byId('operationalResetForm'),
    resetDescription: byId('operationalResetDescription'),
    resetPreview: byId('operationalResetPreview'),
    resetPhrase: byId('operationalResetPhrase'),
    resetError: byId('operationalResetError'),
    resetClose: byId('closeOperationalReset'),
    resetCancel: byId('cancelOperationalReset'),
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
    clients: [],
    resetPreview: null,
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

function operationUuid() {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
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
    window.dispatchEvent(new CustomEvent('estiba:office-session', {
        detail: { authenticated: true, identity: payload.usuario },
    }));
}

function clearSession() {
    state.token = null;
    state.identity = null;
    state.users = [];
    state.devices = [];
    state.seasons = [];
    state.clients = [];
    localStorage.removeItem(keys.token);
    localStorage.removeItem(keys.identity);
    elements.app.classList.add('is-hidden');
    elements.access.classList.remove('is-hidden');
    window.dispatchEvent(new CustomEvent('estiba:office-session', {
        detail: { authenticated: false },
    }));
}

function showApp() {
    elements.access.classList.add('is-hidden');
    elements.app.classList.remove('is-hidden');
    elements.app.classList.toggle('is-read-only', state.identity?.solo_consulta === true);
    const name = state.identity?.nombre || 'Usuario';
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
            <td><span class="role-badge">${escapeHtml(user.perfil?.nombre || statusText(user.rol))}</span><small>${escapeHtml(statusText(user.rol))}</small></td>
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
            <td><div class="admin-season-actions"><button data-edit-season="${season.id}" type="button">Editar</button>${season.activa ? `<button class="admin-season-reset" data-reset-season="${season.id}" type="button">Reiniciar PT + MP</button>` : `<button data-migrate-season="${season.id}" type="button">Migrar datos</button><button data-activate-season="${season.id}" type="button">Activar</button>`}</div></td>
        </tr>
    `).join('');
}

function renderResetPreview(preview) {
    const scopes = preview.resumen || {};
    const cards = Object.entries(scopes).map(([scope, counts]) => {
        const total = Object.values(counts).reduce((sum, value) => sum + Number(value || 0), 0);
        const details = Object.entries(counts)
            .filter(([, value]) => Number(value) > 0)
            .map(([key, value]) => `${statusText(key)}: ${Number(value)}`)
            .join(' · ');
        return `<article><span>${escapeHtml(statusText(scope))}</span><strong>${total}</strong><small>${escapeHtml(details || 'Sin registros')}</small></article>`;
    }).join('');
    elements.resetPreview.innerHTML = cards || '<p>No hay registros operacionales para eliminar.</p>';
}

async function openOperationalReset(seasonId) {
    const season = state.seasons.find((candidate) => candidate.id === seasonId && candidate.activa);
    if (!season) return;
    state.resetPreview = null;
    elements.resetForm.reset();
    elements.resetError.textContent = '';
    elements.resetPreview.innerHTML = '<p>Calculando registros de la temporada activa…</p>';
    elements.resetDescription.textContent = `Temporada ${season.codigo} · ${season.nombre}. La temporada activa se mantiene; solo se elimina su operación de Frigorífico y Materia Prima.`;
    elements.resetDialog.showModal();
    setBusy(true, 'Preparando vista previa del reinicio…');
    try {
        const response = await api(`/api/administracion/temporadas/${season.id}/reinicio-operacional`);
        state.resetPreview = response.data;
        elements.resetPhrase.textContent = response.data.frase_confirmacion;
        renderResetPreview(response.data);
    } catch (error) {
        elements.resetError.textContent = error.message;
    } finally {
        setBusy(false);
    }
}

function closeOperationalReset() {
    state.resetPreview = null;
    elements.resetDialog.close();
}

function resetClientForm() {
    elements.clientForm.reset();
    elements.clientForm.elements.id.value = '';
    elements.clientForm.elements.activo.checked = true;
    elements.clientError.textContent = '';
    elements.clientCancel.classList.add('is-hidden');
}

function renderClients() {
    elements.activeClients.textContent = String(state.clients.filter((client) => client.activo).length);
    elements.clientsSummary.textContent = `${state.clients.length} ${state.clients.length === 1 ? 'registrado' : 'registrados'}`;

    if (!state.clients.length) {
        elements.clientsTableBody.innerHTML = '<tr class="admin-empty"><td colspan="6">No existen clientes globales registrados.</td></tr>';
        return;
    }

    elements.clientsTableBody.innerHTML = state.clients.map((client) => {
        const aliases = (client.aliases || [])
            .filter((alias) => alias.codigo && alias.codigo !== client.codigo)
            .map((alias) => alias.codigo);
        const aliasDetail = [...new Set(aliases)].length
            ? ` · alias: ${escapeHtml([...new Set(aliases)].join(', '))}`
            : '';
        const presence = `${Number(client.presencias?.materiales || 0)} temporadas de Materiales · ${Number(client.presencias?.validacion || 0)} temporadas de Validación`;

        return `
            <tr>
                <td><strong>${escapeHtml(client.codigo)} · ${escapeHtml(client.nombre)}</strong><small>Maestro transversal${aliasDetail}</small></td>
                <td>${client.codigo_folio_materiales ? `F${escapeHtml(client.codigo_folio_materiales)}0000001` : 'Pendiente'}</td>
                <td>${escapeHtml(client.codigo_externo || '—')}</td>
                <td>${escapeHtml(presence)}</td>
                <td>${statusBadge(client.activo)}</td>
                <td><div class="admin-season-actions"><button data-edit-client="${client.id}" type="button">Editar</button></div></td>
            </tr>
        `;
    }).join('');
}

async function loadAccesses() {
    const [response, seasons, clients] = await Promise.all([
        api('/api/administracion/accesos'),
        api('/api/administracion/temporadas'),
        api('/api/administracion/clientes'),
    ]);
    state.users = response.usuarios;
    state.devices = response.dispositivos;
    state.seasons = seasons.data || [];
    state.clients = clients.data || [];
    renderUsers();
    renderDevices();
    renderSeasons();
    renderClients();
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
        if (payload.usuario.puede_consultar_accesos !== true) {
            await api('/api/acceso-oficina', { method: 'DELETE' });
            clearSession();
            throw new ApiError('Tu perfil no puede consultar Accesos y Temporadas.', 403);
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

elements.clientForm.elements.codigo.addEventListener('input', (event) => {
    event.target.value = event.target.value.toUpperCase().replace(/[^A-Z0-9._-]/g, '');
});
elements.clientForm.elements.codigo_folio_materiales.addEventListener('input', (event) => {
    event.target.value = event.target.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2);
});

elements.clientForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.clientError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.clientForm));
    const id = data.id;
    delete data.id;
    data.activo = elements.clientForm.elements.activo.checked;
    setBusy(true, 'Guardando cliente transversal…');
    try {
        await api(id ? `/api/administracion/clientes/${id}` : '/api/administracion/clientes', {
            method: id ? 'PUT' : 'POST',
            body: JSON.stringify(data),
        });
        resetClientForm();
        await loadAccesses();
        toast('Cliente actualizado para todas las oficinas.');
    } catch (error) {
        elements.clientError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.clientsTableBody.addEventListener('click', (event) => {
    const button = event.target.closest('[data-edit-client]');
    if (!button) return;
    const client = state.clients.find((candidate) => candidate.id === button.dataset.editClient);
    if (!client) return;
    for (const field of ['id', 'codigo', 'nombre', 'codigo_externo', 'codigo_folio_materiales']) {
        elements.clientForm.elements[field].value = client[field] || '';
    }
    elements.clientForm.elements.activo.checked = client.activo;
    elements.clientCancel.classList.remove('is-hidden');
    elements.clientForm.elements.codigo.focus();
});

elements.seasonsTableBody.addEventListener('click', async (event) => {
    const edit = event.target.closest('[data-edit-season]');
    const activate = event.target.closest('[data-activate-season]');
    const migrate = event.target.closest('[data-migrate-season]');
    const reset = event.target.closest('[data-reset-season]');
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
    if (reset) await openOperationalReset(reset.dataset.resetSeason);
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

elements.resetForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.resetError.textContent = '';
    const preview = state.resetPreview;
    if (!preview) {
        elements.resetError.textContent = 'Vuelve a abrir el reinicio para cargar una vista previa vigente.';
        return;
    }
    const form = elements.resetForm.elements;
    const data = {
        operacion_id: operationUuid(),
        motivo: form.motivo.value,
        password: form.password.value,
        confirmacion: form.confirmacion.value,
        confirmar_exclusion_bodega: form.confirmar_exclusion_bodega.checked,
        confirmar_preservar_configuracion: form.confirmar_preservar_configuracion.checked,
    };
    if (data.confirmacion !== preview.frase_confirmacion) {
        elements.resetError.textContent = `Escribe exactamente: ${preview.frase_confirmacion}`;
        return;
    }
    if (!data.confirmar_exclusion_bodega || !data.confirmar_preservar_configuracion) {
        elements.resetError.textContent = 'Confirma ambas condiciones de protección antes de continuar.';
        return;
    }

    setBusy(true, 'Reiniciando Frigorífico y Materia Prima…');
    try {
        await api(`/api/administracion/temporadas/${preview.temporada.id}/reinicio-operacional`, {
            method: 'POST',
            body: JSON.stringify(data),
        });
        closeOperationalReset();
        await loadAccesses();
        toast(`Temporada ${preview.temporada.codigo}: Frigorífico y Materia Prima quedaron en cero.`);
    } catch (error) {
        elements.resetError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.resetClose.addEventListener('click', closeOperationalReset);
elements.resetCancel.addEventListener('click', closeOperationalReset);

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
elements.clientCancel.addEventListener('click', resetClientForm);

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

window.addEventListener('estiba:access-profile-saved', () => {
    void loadAccesses();
});

elements.logout.addEventListener('click', async () => {
    try {
        await api('/api/acceso-oficina', { method: 'DELETE' });
    } finally {
        clearSession();
    }
});

async function boot() {
    if (!state.token || state.identity?.puede_consultar_accesos !== true) {
        if (state.token) {
            clearSession();
            elements.loginError.textContent = 'Inicia sesión con un perfil autorizado para consultar Accesos y Temporadas.';
        }
        return;
    }
    showApp();
    setBusy(true, 'Cargando configuración transversal…');
    try {
        await loadAccesses();
    } catch (error) {
        if (error.status !== 401) toast(error.message, true);
    } finally {
        setBusy(false);
    }
}

void boot();
