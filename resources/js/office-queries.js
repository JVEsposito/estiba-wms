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
    reload: byId('reloadButton'),
    producerCount: byId('producerCount'),
    pendingCount: byId('pendingCount'),
    associatedCount: byId('associatedCount'),
    sagTodayCount: byId('sagTodayCount'),
    globalSearch: byId('globalSearchForm'),
    searchResults: byId('searchResults'),
    sagSearch: byId('sagSearchForm'),
    sagResult: byId('sagResult'),
    producerFilters: byId('producerFilters'),
    producerList: byId('producerList'),
    producerDialog: byId('producerDialog'),
    producerDialogTitle: byId('producerDialogTitle'),
    producerDialogBody: byId('producerDialogBody'),
    closeProducerDialog: byId('closeProducerDialog'),
    folioDialog: byId('folioDialog'),
    folioDialogTitle: byId('folioDialogTitle'),
    folioDialogBody: byId('folioDialogBody'),
    closeFolioDialog: byId('closeFolioDialog'),
    loading: byId('officeLoading'),
    loadingText: byId('officeLoadingText'),
    toasts: byId('officeToasts'),
};
const keys = { token: 'estiba_wms_office_token', identity: 'estiba_wms_office_identity' };
const state = {
    token: localStorage.getItem(keys.token),
    identity: readJson(keys.identity),
    catalogs: { clientes: [] },
    producers: [],
};

class ApiError extends Error {
    constructor(message, status, data = {}) {
        super(message);
        this.status = status;
        this.data = data;
    }
}

function readJson(key) {
    try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; }
}

function escapeHtml(value) {
    return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

function label(value) {
    const labels = {
        administrador: 'Administrador',
        supervisor_frio: 'Supervisor de frío',
        digitador_materia_prima: 'Digitador de materia prima',
        pendiente_cliente: 'Pendiente de cliente',
        asociado: 'Asociado',
        folios: 'Folios',
        lotes: 'Lotes',
        productores: 'Productores',
        recepciones: 'Recepciones',
        pallet: 'Pallet',
        saldo: 'Saldo',
        agotado: 'Agotado',
        disponible: 'Disponible',
        pendiente_prefrio: 'Pendiente de prefrío',
        prefrio_aprobado: 'Prefrío aprobado',
        agotado_por_repaletizaje: 'Agotado por repaletizaje',
        aprobado: 'Aprobado',
        observado: 'Observado',
        rechazado: 'Rechazado',
    };
    return labels[value] || String(value || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
}

function formatDate(value, fallback = '—') {
    if (!value) return fallback;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return fallback;
    return new Intl.DateTimeFormat('es-CL', { dateStyle: 'short', timeStyle: 'short' }).format(date);
}

function formatWeight(value) {
    if (value === null || value === undefined) return '—';
    return `${new Intl.NumberFormat('es-CL', { maximumFractionDigits: 3 }).format(Number(value))} kg`;
}

function formatBoxes(value, fallback = '—') {
    if (value === null || value === undefined) return fallback;
    return `${new Intl.NumberFormat('es-CL').format(Number(value))} cajas`;
}

function setBusy(active, message = 'Procesando…') {
    elements.loadingText.textContent = message;
    elements.loading.classList.toggle('is-hidden', !active);
    elements.loading.setAttribute('aria-hidden', String(!active));
}

function toast(message, isError = false) {
    const node = document.createElement('div');
    node.className = `toast${isError ? ' toast--error' : ''}`;
    node.textContent = message;
    elements.toasts.append(node);
    window.setTimeout(() => node.remove(), 5000);
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
    try { response = await fetch(path, { ...options, headers }); } catch {
        throw new ApiError('No fue posible conectar con Laravel.', 0);
    }
    const data = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401 && path !== '/api/acceso-oficina') clearSession();
        throw new ApiError(errorMessage(data, 'No fue posible completar la operación.'), response.status, data);
    }
    return data;
}

function persist(payload) {
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
    if (state.identity?.puede_consultar_oficina_consultas !== true) return false;
    elements.access.classList.add('is-hidden');
    elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Usuario';
    elements.userName.textContent = name;
    elements.userRole.textContent = label(state.identity?.rol);
    elements.initials.textContent = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    return true;
}

function producerBadge(producer) {
    const pending = producer.estado_asociacion === 'pendiente_cliente';
    return `<span class="query-badge ${pending ? 'query-badge--pending' : 'query-badge--active'}">${escapeHtml(label(producer.estado_asociacion))}</span>`;
}

function species(producer) {
    if (!producer.especies?.length) return '';
    return `<div class="sag-species">${producer.especies.map((item) => `<span>${escapeHtml(item)}</span>`).join('')}</div>`;
}

function associationForm(producer) {
    if (state.identity?.puede_asociar_productores_csg !== true) return '';
    const selected = new Set((producer.clientes || []).map((client) => client.id));
    const options = state.catalogs.clientes.map((client) => `
        <label>
            <input type="checkbox" name="cliente_ids" value="${escapeHtml(client.id)}" ${selected.has(client.id) ? 'checked' : ''}>
            <span>${escapeHtml(client.codigo)} · ${escapeHtml(client.nombre)}</span>
        </label>`).join('');
    return `<form class="producer-association" data-associate-producer="${escapeHtml(producer.id)}">
        <fieldset><legend>Clientes habilitados</legend>${options}</fieldset>
        <button type="submit">Guardar clientes</button>
    </form>`;
}

function producerCard(producer, includeAssociation = true) {
    const clients = producer.clientes?.length ? producer.clientes.map((client) => client.nombre).join(', ') : 'Sin cliente asociado';
    return `<article class="producer-card">
        <div class="producer-card__head"><div><small>${escapeHtml(producer.tipo_codigo || 'CSG')}</small><h3>${escapeHtml(producer.codigo)}</h3></div>${producerBadge(producer)}</div>
        <p><strong>${escapeHtml(producer.razon_social)}</strong></p>
        <p>${escapeHtml(producer.predio)}${producer.direccion ? ` · ${escapeHtml(producer.direccion)}` : ''}</p>
        <small>SAG: ${escapeHtml(label(producer.estado_sag))} · Verificado ${escapeHtml(formatDate(producer.ultima_verificacion_at))}</small>
        <small>Cliente: ${escapeHtml(clients)}</small>
        ${species(producer)}
        ${includeAssociation ? associationForm(producer) : ''}
        <div class="producer-actions"><span></span><button type="button" data-open-producer="${escapeHtml(producer.id)}">Ver expediente</button></div>
    </article>`;
}

function renderProducers() {
    elements.producerList.innerHTML = state.producers.length
        ? state.producers.map((producer) => producerCard(producer)).join('')
        : '<div class="query-empty">No hay productores para este filtro.</div>';
}

function resultCard(type, item) {
    if (type === 'folios') {
        const location = item.ubicacion ? `${item.ubicacion.camara} · ${item.ubicacion.posicion}` : 'Sin ubicación actual';
        return `<article class="result-card result-card--folio">
            <div class="result-card__head"><strong>${escapeHtml(item.numero)}</strong><span class="query-badge">${escapeHtml(label(item.estado))}</span></div>
            <p>${escapeHtml([item.exportadora, item.marca, item.variedad, item.calibre].filter(Boolean).join(' · '))}</p>
            <div class="folio-card-status"><span>${escapeHtml(label(item.tipo_bulto))}</span><span>${escapeHtml(label(item.condicion_termica))}</span></div>
            <small>${escapeHtml(location)} · Ingreso ${escapeHtml(formatDate(item.fecha_ingreso))}</small>
            <button type="button" class="trace-button" data-open-folio="${escapeHtml(item.id)}">Ver trazabilidad →</button>
        </article>`;
    }
    if (type === 'lotes') {
        return `<article class="result-card"><div class="result-card__head"><strong>${escapeHtml(item.numero)}</strong><span class="query-badge">${escapeHtml(label(item.estado))}</span></div><p>${escapeHtml(item.cliente)} · CSG ${escapeHtml(item.csg)} · ${escapeHtml(item.especie)} ${escapeHtml(item.variedad)}</p><small>${escapeHtml(item.recepcion || 'Sin recepción')} · ${escapeHtml(formatWeight(item.kilos_netos))} · ${escapeHtml(item.camara || 'Sin cámara')}</small></article>`;
    }
    if (type === 'productores') {
        return `<article class="result-card"><div class="result-card__head"><strong>CSG ${escapeHtml(item.codigo)}</strong>${producerBadge(item)}</div><p>${escapeHtml(item.razon_social)} · ${escapeHtml(item.predio)}</p><small>${escapeHtml(item.clientes?.join(', ') || 'Sin cliente asociado')}</small><button type="button" class="secondary-button" data-open-producer="${escapeHtml(item.id)}">Ver expediente</button></article>`;
    }
    return `<article class="result-card"><div class="result-card__head"><strong>${escapeHtml(item.numero || 'Recepción')}</strong><span class="query-badge">${escapeHtml(label(item.estado))}</span></div><p>Guía ${escapeHtml(item.guia)} · ${escapeHtml(item.cliente)} · ${escapeHtml(item.patente_camion)}</p><small>${escapeHtml(item.conductor)} · ${escapeHtml(formatWeight(item.peso_neto))} · ${item.lotes} lote(s)</small></article>`;
}

function renderSearchResults(payload) {
    const groups = ['folios', 'lotes', 'productores', 'recepciones'];
    const total = groups.reduce((sum, group) => sum + (payload[group]?.length || 0), 0);
    if (!total) {
        elements.searchResults.innerHTML = `<div class="query-empty">No se encontraron registros para “${escapeHtml(payload.termino)}”.</div>`;
        return;
    }
    elements.searchResults.innerHTML = groups.filter((group) => payload[group]?.length).map((group) => `
        <section class="result-group"><h3>${escapeHtml(label(group))}<span>${payload[group].length}</span></h3>
        <div class="result-cards">${payload[group].map((item) => resultCard(group, item)).join('')}</div></section>`).join('');
}

async function loadBase() {
    const filters = new URLSearchParams(new FormData(elements.producerFilters));
    const [summary, catalogs, producers] = await Promise.all([
        api('/api/consultas/resumen'),
        api('/api/consultas/catalogos'),
        api(`/api/consultas/productores?${filters.toString()}`),
    ]);
    state.catalogs = catalogs;
    state.producers = producers.data;
    elements.producerCount.textContent = summary.productores.total;
    elements.pendingCount.textContent = summary.productores.pendientes_cliente;
    elements.associatedCount.textContent = summary.productores.asociados;
    elements.sagTodayCount.textContent = summary.consultas_sag_hoy;
    renderProducers();
}

async function openProducer(id) {
    setBusy(true, 'Cargando expediente CSG…');
    try {
        const payload = await api(`/api/consultas/productores/${id}`);
        const producer = payload.productor;
        elements.producerDialogTitle.textContent = `CSG ${producer.codigo}`;
        const rows = payload.lotes.length ? payload.lotes.map((lot) => `<tr><td>${escapeHtml(lot.numero)}</td><td>${escapeHtml(lot.temporada)}</td><td>${escapeHtml(lot.cliente)}</td><td>${escapeHtml(lot.especie)} · ${escapeHtml(lot.variedad)}</td><td>${escapeHtml(label(lot.estado))}</td><td>${escapeHtml(formatWeight(lot.kilos_netos))}</td><td>${escapeHtml(lot.camara || '—')}</td></tr>`).join('') : '<tr><td colspan="7">Sin lotes registrados para este CSG.</td></tr>';
        elements.producerDialogBody.innerHTML = `
            <div class="dossier-grid"><div><span>Razón social</span><strong>${escapeHtml(producer.razon_social)}</strong></div><div><span>Predio</span><strong>${escapeHtml(producer.predio)}</strong></div><div><span>Estado SAG</span><strong>${escapeHtml(label(producer.estado_sag))}</strong></div><div><span>RUT consultado</span><strong>${escapeHtml(producer.rut || 'No registrado')}</strong></div><div><span>Clientes</span><strong>${escapeHtml(producer.clientes.map((client) => client.nombre).join(', ') || 'Pendiente')}</strong></div><div><span>Trazabilidad</span><strong>${payload.totales.lotes} lotes · ${escapeHtml(formatWeight(payload.totales.kilos_netos))}</strong></div></div>
            ${species(producer)}
            <table class="dossier-lots"><thead><tr><th>Lote</th><th>Temporada</th><th>Cliente</th><th>Producto</th><th>Estado</th><th>Neto</th><th>Cámara</th></tr></thead><tbody>${rows}</tbody></table>`;
        elements.producerDialog.showModal();
    } catch (error) {
        toast(error.message, true);
    } finally {
        setBusy(false);
    }
}

function traceMeta(meta = {}) {
    const entries = Object.entries(meta);
    if (!entries.length) return '';
    return `<div class="trace-event__meta">${entries.map(([key, value]) => `<span><small>${escapeHtml(key)}</small><strong>${escapeHtml(value)}</strong></span>`).join('')}</div>`;
}

function traceOrigins(origins = []) {
    if (!origins.length) return '';
    return `<div class="trace-origins"><small>COMPOSICIÓN / ORÍGENES</small>${origins.map((origin) => `<div><strong>${escapeHtml(origin.folio || 'Folio')}</strong><span>${escapeHtml(formatBoxes(origin.aporte))} aportadas · ${escapeHtml(formatBoxes(origin.antes))} → ${escapeHtml(formatBoxes(origin.despues))}</span></div>`).join('')}</div>`;
}

function traceTimeline(events = []) {
    if (!events.length) return '<div class="query-empty">No hay eventos operacionales registrados para este folio.</div>';
    return `<div class="trace-timeline">${events.map((event) => `
        <article class="trace-event trace-event--${escapeHtml(event.tipo)}">
            <div class="trace-event__rail"><span></span></div>
            <div class="trace-event__body">
                <time>${escapeHtml(formatDate(event.fecha))}</time>
                <h4>${escapeHtml(event.titulo)}</h4>
                <p>${escapeHtml(event.descripcion)}</p>
                ${traceMeta(event.meta)}
                ${traceOrigins(event.origenes)}
            </div>
        </article>`).join('')}</div>`;
}

function specificationGrid(specifications = {}) {
    const labels = {
        cliente: 'Cliente', especie: 'Especie', marca: 'Marca', variedad: 'Variedad', calibre: 'Calibre',
        envase: 'Envase', categoria: 'Categoría', csg: 'CSG', predio: 'Predio', cuartel: 'Cuartel',
    };
    return Object.entries(labels).map(([key, title]) => `<div><span>${title}</span><strong>${escapeHtml(specifications[key] || '—')}</strong></div>`).join('');
}

async function openFolio(id) {
    setBusy(true, 'Construyendo trazabilidad del folio…');
    try {
        const payload = await api(`/api/consultas/folios/${id}`);
        const folio = payload.folio;
        const currentLocation = folio.ubicacion ? `${folio.ubicacion.camara} · ${folio.ubicacion.posicion}` : 'Sin ubicación actual';
        const originText = payload.validacion
            ? `Validado ${formatDate(payload.validacion.fecha)} · ${formatBoxes(payload.validacion.cantidad_cajas)}`
            : (payload.repaletizajes?.length ? `Origen trazado por ${payload.repaletizajes[0].codigo}` : 'Sin validación directa registrada');
        const exhaustion = folio.repaletizaje_agotamiento
            ? `<div class="trace-alert"><strong>AGOTADO POR REPALETIZAJE</strong><span>Este folio terminó su existencia física en ${escapeHtml(folio.repaletizaje_agotamiento)}.</span></div>`
            : '';

        elements.folioDialogTitle.textContent = folio.numero;
        elements.folioDialogBody.innerHTML = `
            ${exhaustion}
            <section class="folio-dossier-hero">
                <div><span>ESTADO ACTUAL</span><strong>${escapeHtml(label(folio.estado_explicado))}</strong><small>${escapeHtml(label(folio.condicion_termica))}</small></div>
                <div><span>CANTIDAD ACTUAL</span><strong>${escapeHtml(formatBoxes(folio.cantidad_cajas))}</strong><small>${escapeHtml(label(folio.tipo_bulto))}</small></div>
                <div><span>UBICACIÓN</span><strong>${escapeHtml(currentLocation)}</strong><small>${escapeHtml(folio.temporada || 'Sin temporada')}</small></div>
                <div><span>ORIGEN</span><strong>${escapeHtml(originText)}</strong><small>Ingreso ${escapeHtml(formatDate(folio.fecha_ingreso))}</small></div>
            </section>
            <section class="folio-dossier-section">
                <div class="folio-dossier-heading"><div><p class="eyebrow">ESPECIFICACIONES</p><h3>Identidad del producto</h3></div></div>
                <div class="folio-spec-grid">${specificationGrid(folio.especificaciones)}</div>
            </section>
            <section class="folio-dossier-section">
                <div class="folio-dossier-heading"><div><p class="eyebrow">HISTORIA OPERACIONAL</p><h3>Línea de tiempo</h3></div><div class="trace-counts"><span>${payload.totales.validaciones} validación</span><span>${payload.totales.procesos_prefrio} prefrío</span><span>${payload.totales.movimientos} movimientos</span><span>${payload.totales.repaletizajes} repas</span></div></div>
                ${traceTimeline(payload.timeline)}
            </section>`;
        elements.folioDialog.showModal();
    } catch (error) {
        toast(error.message, true);
    } finally {
        setBusy(false);
    }
}

elements.login.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.loginError.textContent = '';
    setBusy(true, 'Verificando acceso…');
    try {
        const payload = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(elements.login))) });
        if (payload.usuario?.puede_consultar_oficina_consultas !== true) throw new ApiError('El usuario no posee acceso a Consultas.', 403);
        persist(payload);
        showApp();
        await loadBase();
    } catch (error) { elements.loginError.textContent = error.message; } finally { setBusy(false); }
});

elements.logout.addEventListener('click', async () => {
    try { await api('/api/acceso-oficina', { method: 'DELETE' }); } finally { clearSession(); }
});

elements.reload.addEventListener('click', async () => {
    setBusy(true, 'Actualizando consultas…');
    try { await loadBase(); toast('Información actualizada.'); } catch (error) { toast(error.message, true); } finally { setBusy(false); }
});

elements.globalSearch.addEventListener('submit', async (event) => {
    event.preventDefault();
    setBusy(true, 'Buscando trazabilidad…');
    try {
        const params = new URLSearchParams(new FormData(elements.globalSearch));
        renderSearchResults(await api(`/api/consultas/buscar?${params.toString()}`));
    } catch (error) { toast(error.message, true); } finally { setBusy(false); }
});

elements.sagSearch.addEventListener('submit', async (event) => {
    event.preventDefault();
    setBusy(true, 'Consultando registro público SAG…');
    try {
        const payload = await api('/api/consultas/sag', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(elements.sagSearch))) });
        elements.sagResult.innerHTML = payload.data.length ? payload.data.map((producer) => `<div class="sag-producer">${producerCard(producer)}</div>`).join('') : `<div class="query-empty">${escapeHtml(payload.message)}</div>`;
        await loadBase();
        toast(payload.message);
    } catch (error) {
        elements.sagResult.innerHTML = `<div class="query-empty">${escapeHtml(error.message)}</div>`;
        toast(error.message, true);
    } finally { setBusy(false); }
});

elements.producerFilters.addEventListener('submit', async (event) => {
    event.preventDefault();
    setBusy(true, 'Filtrando productores…');
    try { await loadBase(); } catch (error) { toast(error.message, true); } finally { setBusy(false); }
});

document.addEventListener('click', (event) => {
    const producerButton = event.target.closest('[data-open-producer]');
    if (producerButton) {
        void openProducer(producerButton.dataset.openProducer);
        return;
    }
    const folioButton = event.target.closest('[data-open-folio]');
    if (folioButton) void openFolio(folioButton.dataset.openFolio);
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-associate-producer]');
    if (!form) return;
    event.preventDefault();
    const clientIds = [...form.querySelectorAll('input[name="cliente_ids"]:checked')]
        .map((input) => input.value);
    if (!clientIds.length) { toast('Selecciona al menos un cliente.', true); return; }
    setBusy(true, 'Guardando clientes del CSG…');
    try {
        await api(`/api/consultas/productores/${form.dataset.associateProducer}/clientes`, { method: 'POST', body: JSON.stringify({ cliente_ids: clientIds }) });
        await loadBase();
        toast(`CSG habilitado para ${clientIds.length} cliente(s).`);
    } catch (error) { toast(error.message, true); } finally { setBusy(false); }
});

elements.closeProducerDialog.addEventListener('click', () => elements.producerDialog.close());
elements.closeFolioDialog.addEventListener('click', () => elements.folioDialog.close());

async function boot() {
    if (!state.token || state.identity?.puede_consultar_oficina_consultas !== true) {
        if (state.token) clearSession();
        return;
    }
    showApp();
    setBusy(true, 'Cargando oficina de consultas…');
    try { await loadBase(); } catch (error) {
        if (error.status !== 401) toast(error.message, true);
    } finally { setBusy(false); }
}

void boot();
