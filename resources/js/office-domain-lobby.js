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
    reload: byId('reloadLobbyButton'),
    grid: byId('domainLobbyGrid'),
    empty: byId('domainLobbyEmpty'),
    summaryStatus: byId('lobbySummaryStatus'),
    loading: byId('officeLoading'),
    loadingText: byId('officeLoadingText'),
    toasts: byId('officeToasts'),
};
const keys = {
    token: 'estiba_wms_office_token',
    identity: 'estiba_wms_office_identity',
};
const moduleAliases = {
    'administracion.maestros-temporada': ['frigorifico.catalogos'],
};
const state = {
    token: localStorage.getItem(keys.token),
    identity: readJson(keys.identity),
    domain: elements.app?.dataset.lobbyDomain || 'materia-prima',
    loading: false,
};

class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.status = status;
    }
}

function readJson(key) {
    try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; }
}

function capabilities(identity) {
    return { ...(identity?.capacidades || {}), ...(identity || {}) };
}

function can(identity, permission) {
    if (!identity || !permission) return false;
    if (identity.rol === 'administrador') return true;
    const values = capabilities(identity);

    if (permission === 'ambito_camaras_productos') {
        return ['productos', 'ambos'].includes(values.ambito_camaras);
    }

    return values[permission] === true;
}

function permissionsFrom(value) {
    return String(value || '').split(',').map((item) => item.trim()).filter(Boolean);
}

function hasModule(identity, module) {
    if (!module) return true;
    const modules = capabilities(identity).modulos_acceso;
    if (!Array.isArray(modules)) return true;

    return modules.includes(module)
        || (moduleAliases[module] || []).some((alias) => modules.includes(alias));
}

function cardIsAccessible(card) {
    return hasModule(state.identity, card.dataset.navigationModule)
        && permissionsFrom(card.dataset.navigationPermissions).some((permission) => can(state.identity, permission));
}

function metric(key, value) {
    const target = document.querySelector(`[data-lobby-metric="${CSS.escape(key)}"]`);
    if (target) target.textContent = value ?? '—';
}

function setBusy(active, message = 'Actualizando resumen…') {
    elements.loadingText.textContent = message;
    elements.loading.classList.toggle('is-hidden', !active);
    elements.loading.setAttribute('aria-hidden', String(!active));
}

function toast(message, error = false) {
    const node = document.createElement('div');
    node.className = `toast${error ? ' toast--error' : ''}`;
    node.textContent = message;
    elements.toasts.append(node);
    window.setTimeout(() => node.remove(), 4500);
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

function persist(payload) {
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
    localStorage.removeItem(keys.token);
    localStorage.removeItem(keys.identity);
    elements.app.classList.add('is-hidden');
    elements.access.classList.remove('is-hidden');
    window.dispatchEvent(new CustomEvent('estiba:office-session', {
        detail: { authenticated: false },
    }));
}

function filterCards() {
    const cards = [...elements.grid.querySelectorAll('.domain-lobby-card')];
    let available = 0;

    cards.forEach((card) => {
        const visible = cardIsAccessible(card);
        card.classList.toggle('is-hidden', !visible);
        card.setAttribute('aria-hidden', String(!visible));
        card.tabIndex = visible ? 0 : -1;
        if (visible) available += 1;
    });

    metric('modules', String(available));
    elements.empty.classList.toggle('is-hidden', available > 0);

    return available;
}

function showApp() {
    elements.access.classList.add('is-hidden');
    elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Usuario';
    const profile = state.identity?.perfil_acceso?.nombre
        || state.identity?.capacidades?.perfil_acceso?.nombre
        || String(state.identity?.rol || 'Oficina').replaceAll('_', ' ');
    elements.userName.textContent = name;
    elements.userRole.textContent = profile;
    elements.initials.textContent = name.split(/\s+/).filter(Boolean).slice(0, 2)
        .map((part) => part[0]).join('').toUpperCase();
    filterCards();
    window.EstibaOfficeNavigation?.refresh?.();
}

async function loadRawMaterialSummary() {
    if (!can(state.identity, 'puede_consultar_materia_prima')) return false;
    const data = await api('/api/materia-prima/resumen');
    metric('season', data.temporada?.codigo || 'Sin temporada');
    metric('segments', String(data.segmentos_pendientes ?? 0));
    metric('hydrocooler', String(data.lotes?.pendientes_hidrocooler ?? 0));
    metric('process', String(data.lotes?.disponibles_proceso ?? 0));
    metric('cameras', String(data.lotes?.asignados_camara ?? 0));
    return true;
}

async function loadRefrigeratedSummary() {
    const requests = [];

    if (can(state.identity, 'puede_consultar_prefrio')) {
        requests.push(api('/api/prefrio/resumen').then((data) => {
            const active = Number(data.en_proceso || 0)
                + Number(data.pendiente_verificacion || 0)
                + Number(data.requiere_reproceso || 0);
            metric('prefrio', String(active));
            metric('prefrio_folios', String(data.folios_activos ?? 0));
        }));
    }

    if (can(state.identity, 'puede_consultar_inspeccion_sag')) {
        requests.push(api('/api/inspeccion-sag/resumen').then((data) => {
            metric('sag', String(data.lotes_activos ?? 0));
            metric('sag_folios', String(data.pallets_en_inspeccion ?? 0));
        }));
    }

    if (can(state.identity, 'ambito_camaras_productos')) {
        requests.push(api('/api/camaras').then((payload) => {
            const cameras = (payload.data || []).filter((camera) => camera.contenido === 'productos');
            metric('cameras', String(cameras.length));
        }));
    }

    if (!requests.length) return false;
    const results = await Promise.allSettled(requests);
    const rejected = results.filter((result) => result.status === 'rejected');
    if (rejected.length === results.length) throw rejected[0].reason;

    return true;
}

async function loadAdministrationSummary() {
    if (!can(state.identity, 'puede_consultar_panel_gerencial')) return false;
    const payload = await api('/api/gerencia/resumen');
    const data = payload.data || {};
    metric('alerts', String(data.alertas?.length ?? 0));
    metric('folios', String(data.productos?.total_activos ?? 0));
    metric('loads', String(data.cargas?.activas ?? 0));
    metric('occupancy', `${Number(data.camaras?.resumen?.ocupacion_porcentaje || 0).toLocaleString('es-CL')}%`);
    metric('prefrio_pending', String(data.prefrio?.folios_pendientes ?? 0));
    return true;
}

async function loadQueriesSummary() {
    if (!can(state.identity, 'puede_consultar_oficina_consultas')) return false;
    const data = await api('/api/consultas/resumen');
    metric('producers', String(data.productores?.total ?? 0));
    metric('pending', String(data.productores?.pendientes_cliente ?? 0));
    metric('associated', String(data.productores?.asociados ?? 0));
    metric('lots', String(data.lotes ?? 0));
    metric('sag_today', String(data.consultas_sag_hoy ?? 0));
    return true;
}

const summaryLoaders = {
    'materia-prima': loadRawMaterialSummary,
    frigorifico: loadRefrigeratedSummary,
    administracion: loadAdministrationSummary,
    consultas: loadQueriesSummary,
};

async function loadSummary({ notify = false } = {}) {
    if (state.loading) return;
    state.loading = true;
    elements.reload.disabled = true;
    if (notify) setBusy(true);

    try {
        const loaded = await summaryLoaders[state.domain]?.();
        elements.summaryStatus.textContent = loaded
            ? 'Indicadores actualizados según los permisos de tu perfil.'
            : 'Los indicadores consolidados no forman parte de este perfil; los accesos disponibles siguen operativos.';
        if (notify) toast('Resumen actualizado.');
    } catch (error) {
        elements.summaryStatus.textContent = 'No fue posible actualizar todos los indicadores; los accesos permanecen disponibles.';
        if (notify) toast(error.message, true);
    } finally {
        state.loading = false;
        elements.reload.disabled = false;
        setBusy(false);
    }
}

elements.login.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.loginError.textContent = '';
    setBusy(true, 'Validando acceso…');

    try {
        const payload = await api('/api/acceso-oficina', {
            method: 'POST',
            body: JSON.stringify(Object.fromEntries(new FormData(elements.login))),
        });
        persist(payload);
        showApp();
        await loadSummary();
    } catch (error) {
        elements.loginError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.reload.addEventListener('click', () => loadSummary({ notify: true }));
elements.logout.addEventListener('click', async () => {
    try { await api('/api/acceso-oficina', { method: 'DELETE' }); } catch { /* limpia localmente */ }
    clearSession();
});

if (state.token && state.identity) {
    showApp();
    void loadSummary();
}
