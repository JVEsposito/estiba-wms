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
    camerasNav: byId('officeCamerasNav'),
    materialsNav: byId('officeMaterialsNav'),
    accessesNav: byId('officeAccessesNav'),
    reload: byId('reloadLoadsButton'),
    newLoad: byId('newLoadButton'),
    emptyNewLoad: byId('emptyNewLoadButton'),
    search: byId('loadSearch'),
    statusFilter: byId('loadStatusFilter'),
    catalogSummary: byId('loadCatalogSummary'),
    loadPageSize: byId('loadPageSize'),
    loadPagination: byId('loadPagination'),
    list: byId('loadList'),
    empty: byId('loadEmptyState'),
    editor: byId('loadEditorContent'),
    editorEyebrow: byId('loadEditorEyebrow'),
    editorTitle: byId('loadEditorTitle'),
    editorDescription: byId('loadEditorDescription'),
    statusBadge: byId('loadStatusBadge'),
    priorityBadge: byId('loadPriorityBadge'),
    audit: byId('loadAudit'),
    version: byId('loadVersion'),
    updatedAt: byId('loadUpdatedAt'),
    headerForm: byId('loadHeaderForm'),
    headerError: byId('loadHeaderError'),
    targetCamera: byId('targetCameraSelect'),
    discardNew: byId('discardNewLoadButton'),
    save: byId('saveLoadButton'),
    saveText: byId('saveLoadButtonText'),
    operation: byId('loadOperation'),
    totalFolios: byId('loadTotalFolios'),
    totalCameras: byId('loadTotalCameras'),
    updatedBy: byId('loadUpdatedBy'),
    distribution: byId('loadDistribution'),
    folioAddSection: byId('folioAddSection'),
    folioInput: byId('folioInput'),
    folioInputCount: byId('folioInputCount'),
    addFolios: byId('addFoliosButton'),
    folioErrors: byId('folioErrors'),
    availableFolioSearch: byId('availableFolioSearch'),
    availableFolioSummary: byId('availableFolioSummary'),
    availableFolioList: byId('availableFolioList'),
    availableFolioTableBody: byId('availableFolioTableBody'),
    availableFolioSelectPage: byId('availableFolioSelectPage'),
    availableFolioPageSize: byId('availableFolioPageSize'),
    availableFolioPagination: byId('availableFolioPagination'),
    reloadAvailableFolios: byId('reloadAvailableFoliosButton'),
    folioTableBody: byId('folioTableBody'),
    folioTableHint: byId('folioTableHint'),
    commandBar: byId('loadCommandBar'),
    commandTitle: byId('loadCommandTitle'),
    commandDescription: byId('loadCommandDescription'),
    cancel: byId('cancelLoadButton'),
    publish: byId('publishLoadButton'),
    loading: byId('officeLoading'),
    loadingText: byId('officeLoadingText'),
    toasts: byId('officeToasts'),
};

const keys = {
    token: 'estiba_wms_office_token',
    identity: 'estiba_wms_office_identity',
};

const statusLabels = {
    borrador: 'Borrador',
    pendiente: 'Pendiente',
    en_separacion: 'En separación',
    separada: 'Separada',
    separacion_completa: 'Separación completa',
    despachada: 'Despachada',
    cancelada: 'Cancelada',
};

const priorityLabels = {
    normal: 'Normal',
    alta: 'Alta',
    urgente: 'Urgente',
};

const state = {
    token: localStorage.getItem(keys.token),
    identity: readJson(keys.identity),
    loads: [],
    cameras: [],
    availableFolios: [],
    loadPagination: emptyPagination(25),
    availablePagination: emptyPagination(10),
    loadRequestId: 0,
    availableRequestId: 0,
    selected: null,
    mode: 'empty',
};

let loadSearchTimer;
let availableSearchTimer;

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

function emptyPagination(perPage) {
    return {
        currentPage: 1,
        lastPage: 1,
        perPage,
        from: 0,
        to: 0,
        total: 0,
    };
}

function paginationFrom(response, fallbackPerPage) {
    const meta = response?.meta || {};
    return {
        currentPage: Number(meta.current_page || 1),
        lastPage: Math.max(1, Number(meta.last_page || 1)),
        perPage: Number(meta.per_page || fallbackPerPage),
        from: Number(meta.from || 0),
        to: Number(meta.to || 0),
        total: Number(meta.total || 0),
    };
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function firstValidationError(errors) {
    if (!errors || Array.isArray(errors)) return null;
    return Object.values(errors).flat().find(Boolean) || null;
}

function errorMessage(data, fallback) {
    return firstValidationError(data?.errors) || data?.message || fallback;
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
        throw new ApiError(
            errorMessage(data, 'No fue posible completar la operación.'),
            response.status,
            data,
        );
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
    window.setTimeout(() => item.remove(), 5000);
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
    state.loads = [];
    state.cameras = [];
    state.availableFolios = [];
    state.loadPagination = emptyPagination(Number(elements.loadPageSize.value));
    state.availablePagination = emptyPagination(Number(elements.availableFolioPageSize.value));
    state.selected = null;
    state.mode = 'empty';
    localStorage.removeItem(keys.token);
    localStorage.removeItem(keys.identity);
    elements.app.classList.add('is-hidden');
    elements.access.classList.remove('is-hidden');
}

function showApp() {
    elements.access.classList.add('is-hidden');
    elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Despachador';
    elements.userName.textContent = name;
    elements.userRole.textContent = statusText(state.identity?.rol || 'oficina');
    elements.initials.textContent = name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
    elements.camerasNav.classList.remove('is-hidden');
    elements.accessesNav.classList.toggle(
        'is-hidden',
        state.identity?.puede_administrar_accesos !== true,
    );
    elements.materialsNav.classList.toggle(
        'is-hidden',
        state.identity?.puede_consultar_despachos_materiales !== true,
    );
    const canManage = state.identity?.puede_gestionar_cargas === true;
    elements.newLoad.classList.toggle('is-hidden', !canManage);
    elements.emptyNewLoad.classList.toggle('is-hidden', !canManage);
}

function statusText(value) {
    return String(value || '')
        .replaceAll('_', ' ')
        .replace(/^./, (character) => character.toUpperCase());
}

function formatDate(value, fallback = '—') {
    if (!value) return fallback;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return fallback;
    return new Intl.DateTimeFormat('es-CL', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(date);
}

function canEdit(load) {
    return state.identity?.puede_gestionar_cargas === true
        && ['borrador', 'pendiente'].includes(load?.estado);
}

function canPublish(load) {
    return state.identity?.puede_gestionar_cargas === true
        && load?.estado === 'borrador'
        && load.total_folios >= 1;
}

function canCancel(load) {
    return state.identity?.puede_gestionar_cargas === true
        && ['borrador', 'pendiente'].includes(load?.estado);
}

function parseFolios(value = elements.folioInput.value) {
    const seen = new Set();

    return String(value)
        .split(/[\s,;]+/)
        .map((folio) => folio.trim())
        .filter((folio) => {
            if (!folio || seen.has(folio)) return false;
            seen.add(folio);
            return true;
        });
}

function remainingFolioSlots(load = state.selected) {
    return Math.max(0, 26 - Number(load?.total_folios || 0));
}

function statusClass(status) {
    return String(status || 'draft').replaceAll('_', '-');
}

function priorityClass(priority) {
    return String(priority || 'normal').replaceAll('_', '-');
}

function distributionText(load) {
    if (!load.distribucion?.length) return 'Sin distribución disponible';
    return load.distribucion
        .map((item) => `${item.camara.codigo}: ${item.cantidad}`)
        .join(' · ');
}

function renderPagination(element, pagination, dataAttribute) {
    if (pagination.total === 0) {
        element.innerHTML = '';
        return;
    }

    element.innerHTML = `
        <button data-${dataAttribute}="${pagination.currentPage - 1}" type="button"${pagination.currentPage <= 1 ? ' disabled' : ''}>Anterior</button>
        <span>Página <strong>${pagination.currentPage}</strong> de ${pagination.lastPage}</span>
        <button data-${dataAttribute}="${pagination.currentPage + 1}" type="button"${pagination.currentPage >= pagination.lastPage ? ' disabled' : ''}>Siguiente</button>
    `;
}

function renderCatalog() {
    const loads = state.loads;
    const pagination = state.loadPagination;
    elements.catalogSummary.textContent = pagination.total
        ? `${pagination.from}–${pagination.to} de ${pagination.total} órdenes`
        : '0 órdenes';
    renderPagination(elements.loadPagination, pagination, 'load-page');

    if (!loads.length) {
        elements.list.innerHTML = `
            <div class="empty-state">
                No hay órdenes que coincidan con los filtros actuales.
            </div>
        `;
        return;
    }

    elements.list.innerHTML = loads.map((load) => {
        const selected = state.selected?.id === load.id;
        return `
            <button class="load-card${selected ? ' is-selected' : ''}" data-load-id="${escapeHtml(load.id)}" type="button">
                <div class="load-card__line load-card__line--main">
                    <strong class="load-card__code">${escapeHtml(load.codigo)}</strong>
                    <span class="load-card__external">${escapeHtml(load.numero_orden_externa || 'Sin orden externa')}</span>
                    <span class="status-badge status-badge--${statusClass(load.estado)}">${escapeHtml(statusLabels[load.estado] || statusText(load.estado))}</span>
                </div>
                <div class="load-card__line load-card__line--detail">
                    <span class="load-card__distribution">${escapeHtml(distributionText(load))}</span>
                    <span class="priority-dot priority-dot--${priorityClass(load.prioridad)}">${escapeHtml(priorityLabels[load.prioridad] || statusText(load.prioridad))}</span>
                    <span class="load-card__count">${load.total_folios} / 26</span>
                </div>
            </button>
        `;
    }).join('');
}

function populateCameraOptions(selectedId = '') {
    elements.targetCamera.innerHTML = [
        '<option value="">Sin cámara objetivo</option>',
        ...state.cameras.map((camera) => {
            const total = Number(camera.ocupacion?.total || 0);
            const occupied = Number(camera.ocupacion?.ocupadas || 0);
            const available = Math.max(0, total - occupied);

            return `
                <option value="${escapeHtml(camera.id)}"${camera.id === selectedId ? ' selected' : ''}>
                    ${escapeHtml(camera.codigo)} · ${escapeHtml(camera.nombre)} · ${available} libres de ${total}
                </option>
            `;
        }),
    ].join('');
}

function setHeaderDisabled(disabled) {
    for (const field of elements.headerForm.elements) {
        if (field.type !== 'submit' && field.type !== 'button') field.disabled = disabled;
    }
    elements.save.disabled = disabled;
}

function clearFolioErrors() {
    elements.folioErrors.innerHTML = '';
    elements.folioErrors.classList.add('is-hidden');
}

function showFolioErrors(error) {
    const detailed = Array.isArray(error?.data?.errores) ? error.data.errores : [];
    if (!detailed.length) {
        elements.folioErrors.innerHTML = `<strong>No se incorporó ningún folio.</strong><span>${escapeHtml(error.message)}</span>`;
    } else {
        elements.folioErrors.innerHTML = `
            <strong>No se incorporó ningún folio. Corrige el lote y vuelve a intentarlo.</strong>
            <ul>
                ${detailed.map((item) => `
                    <li><b>${escapeHtml(item.folio || 'Folio')}</b><span>${escapeHtml(item.mensaje || item.codigo || 'No asignable')}</span></li>
                `).join('')}
            </ul>
        `;
    }
    elements.folioErrors.classList.remove('is-hidden');
}

function renderAvailableFolios() {
    const selected = new Set(parseFolios());
    const folios = state.availableFolios;
    const pagination = state.availablePagination;

    elements.availableFolioSummary.textContent = pagination.total
        ? `Mostrando ${pagination.from}–${pagination.to} de ${pagination.total} folios disponibles · ${selected.size} seleccionados`
        : '0 folios disponibles';
    renderPagination(elements.availableFolioPagination, pagination, 'available-page');
    elements.availableFolioSelectPage.disabled = folios.length === 0;
    elements.availableFolioSelectPage.checked = folios.length > 0
        && folios.every((folio) => selected.has(folio.numero_folio));
    elements.availableFolioSelectPage.indeterminate = !elements.availableFolioSelectPage.checked
        && folios.some((folio) => selected.has(folio.numero_folio));

    if (!folios.length) {
        elements.availableFolioTableBody.innerHTML = `
            <tr class="available-folios__empty">
                <td colspan="9">${elements.availableFolioSearch.value.trim() ? 'No hay folios que coincidan con la búsqueda.' : 'No existen folios ubicados y disponibles sin una carga asignada.'}</td>
            </tr>
        `;
        return;
    }

    elements.availableFolioTableBody.innerHTML = folios.map((folio) => {
        const isSelected = selected.has(folio.numero_folio);
        const sag = folio.condicion_sag
            ? `${folio.condicion_sag.codigo} · ${folio.condicion_sag.nombre}`
            : '—';
        return `
            <tr class="${isSelected ? 'is-selected' : ''}">
                <td class="available-folios__check"><input data-available-folio="${escapeHtml(folio.numero_folio)}" type="checkbox" aria-label="Seleccionar ${escapeHtml(folio.numero_folio)}"${isSelected ? ' checked' : ''}></td>
                <td><strong>${escapeHtml(folio.numero_folio)}</strong></td>
                <td><span class="folio-type">${escapeHtml(statusText(folio.tipo_bulto))}</span></td>
                <td><strong>${escapeHtml(folio.variedad || '—')}</strong><small>${escapeHtml(folio.calibre || 'Sin calibre')}</small></td>
                <td><strong>${escapeHtml(folio.marca || '—')}</strong><small>${escapeHtml(folio.exportadora || 'Sin exportadora')}</small></td>
                <td>${escapeHtml(sag)}</td>
                <td><strong class="location-label">${escapeHtml(folio.ubicacion.camara.codigo)}</strong><small>${escapeHtml(folio.ubicacion.camara.nombre)}</small></td>
                <td><span class="location-label">${escapeHtml(folio.ubicacion.posicion.etiqueta)}</span></td>
                <td>${escapeHtml(formatDate(folio.fecha_ingreso))}</td>
            </tr>
        `;
    }).join('');
}

function showEmpty() {
    state.mode = 'empty';
    state.selected = null;
    elements.empty.classList.remove('is-hidden');
    elements.editor.classList.add('is-hidden');
    renderCatalog();
}

function startNew() {
    state.mode = 'new';
    state.selected = null;
    elements.empty.classList.add('is-hidden');
    elements.editor.classList.remove('is-hidden');
    elements.operation.classList.add('is-hidden');
    elements.audit.classList.add('is-hidden');
    elements.discardNew.classList.remove('is-hidden');
    elements.headerForm.reset();
    populateCameraOptions();
    elements.headerError.textContent = '';
    elements.editorEyebrow.textContent = 'NUEVA ORDEN';
    elements.editorTitle.textContent = 'Nuevo borrador';
    elements.editorDescription.textContent = 'Crea el encabezado y luego incorpora los folios.';
    elements.statusBadge.className = 'status-badge status-badge--draft';
    elements.statusBadge.textContent = 'Sin guardar';
    elements.priorityBadge.classList.add('is-hidden');
    elements.saveText.textContent = 'Crear borrador';
    setHeaderDisabled(false);
    clearFolioErrors();
    renderCatalog();
    elements.headerForm.elements.numero_orden_externa.focus();
}

function renderDistribution(load) {
    if (!load.distribucion?.length) {
        elements.distribution.innerHTML = '<span class="distribution-empty">Sin folios asignados</span>';
        return;
    }

    elements.distribution.innerHTML = load.distribucion.map((item) => `
        <span class="distribution-chip">
            <b>${escapeHtml(item.camara.codigo)}</b>
            <span>${escapeHtml(item.camara.nombre)}</span>
            <strong>${item.cantidad}</strong>
        </span>
    `).join('');
}

function renderFolios(load) {
    if (!load.folios?.length) {
        elements.folioTableBody.innerHTML = `
            <tr class="folio-empty-row">
                <td colspan="5">Todavía no hay folios asignados a esta orden.</td>
            </tr>
        `;
        return;
    }

    const editable = canEdit(load);
    elements.folioTableBody.innerHTML = load.folios.map((folio) => {
        const location = folio.ubicacion
            ? `${folio.ubicacion.camara.codigo} · ${folio.ubicacion.posicion.etiqueta}`
            : 'Sin ubicación';
        return `
            <tr>
                <td><strong>${escapeHtml(folio.numero_folio)}</strong><small>${escapeHtml(folio.estado_operacional || '—')}</small></td>
                <td>${escapeHtml(statusText(folio.tipo_bulto || '—'))}</td>
                <td><span class="location-label">${escapeHtml(location)}</span></td>
                <td>${escapeHtml(formatDate(folio.asignado_at))}</td>
                <td class="folio-action-cell">
                    ${editable ? `<button class="folio-remove" data-remove-folio="${escapeHtml(folio.id)}" data-folio-number="${escapeHtml(folio.numero_folio)}" type="button">Quitar</button>` : ''}
                </td>
            </tr>
        `;
    }).join('');
}

function renderCommands(load) {
    const editable = canEdit(load);
    const draft = load.estado === 'borrador';
    const pending = load.estado === 'pendiente';

    elements.folioAddSection.classList.toggle('is-hidden', !editable);
    elements.cancel.classList.toggle('is-hidden', !canCancel(load));
    elements.publish.classList.toggle('is-hidden', !draft);
    elements.publish.disabled = !canPublish(load);

    if (draft) {
        elements.commandTitle.textContent = load.total_folios
            ? 'Borrador listo para revisión'
            : 'Borrador sin folios';
        elements.commandDescription.textContent = load.total_folios
            ? 'Al publicar, la orden aparecerá en la operación y en el plano de estiba.'
            : 'Agrega al menos un folio para habilitar la publicación.';
        elements.folioTableHint.textContent = 'El borrador todavía no es visible para el camarero.';
    } else if (pending) {
        elements.commandTitle.textContent = 'Orden publicada y todavía editable';
        elements.commandDescription.textContent = 'Podrás modificarla hasta que la operación inicie la separación.';
        elements.folioTableHint.textContent = 'Esta orden ya está disponible para la operación.';
    } else {
        elements.commandTitle.textContent = statusLabels[load.estado] || statusText(load.estado);
        elements.commandDescription.textContent = 'El encabezado y los folios quedan en modo de consulta.';
        elements.folioTableHint.textContent = 'La orden no admite modificaciones en su estado actual.';
    }
}

function renderSelected(load) {
    state.mode = 'detail';
    state.selected = load;
    elements.empty.classList.add('is-hidden');
    elements.editor.classList.remove('is-hidden');
    elements.operation.classList.remove('is-hidden');
    elements.audit.classList.remove('is-hidden');
    elements.discardNew.classList.add('is-hidden');
    elements.headerError.textContent = '';
    clearFolioErrors();

    const editable = canEdit(load);
    elements.editorEyebrow.textContent = load.estado === 'borrador' ? 'ORDEN EN PREPARACIÓN' : 'ORDEN DE CARGA';
    elements.editorTitle.textContent = load.codigo;
    elements.editorDescription.textContent = load.numero_orden_externa
        ? `Orden externa ${load.numero_orden_externa}`
        : 'Sin número de orden externa asociado.';
    elements.statusBadge.className = `status-badge status-badge--${statusClass(load.estado)}`;
    elements.statusBadge.textContent = statusLabels[load.estado] || statusText(load.estado);
    elements.priorityBadge.className = `priority-badge priority-badge--${priorityClass(load.prioridad)}`;
    elements.priorityBadge.textContent = priorityLabels[load.prioridad] || statusText(load.prioridad);
    elements.version.textContent = `#${load.version}`;
    elements.updatedAt.textContent = formatDate(load.updated_at);

    elements.headerForm.elements.numero_orden_externa.value = load.numero_orden_externa || '';
    elements.headerForm.elements.prioridad.value = load.prioridad || 'normal';
    elements.headerForm.elements.observacion.value = load.observacion || '';
    populateCameraOptions(load.camara_objetivo?.id || '');
    elements.saveText.textContent = editable ? 'Guardar encabezado' : 'Solo lectura';
    setHeaderDisabled(!editable);

    elements.totalFolios.textContent = `${load.total_folios} / 26`;
    elements.totalCameras.textContent = String(load.distribucion?.length || 0);
    elements.updatedBy.textContent = load.actualizada_por?.nombre || load.creada_por?.nombre || '—';
    renderDistribution(load);
    renderFolios(load);
    renderCommands(load);
    elements.folioInput.value = '';
    updateFolioInputCount();
    renderAvailableFolios();
    renderCatalog();
}

function syncLoad(load) {
    const index = state.loads.findIndex((item) => item.id === load.id);
    if (index === -1) state.loads.unshift(load);
    else state.loads[index] = load;
    renderSelected(load);
}

async function loadCatalog(page = state.loadPagination.currentPage) {
    const requestId = ++state.loadRequestId;
    const params = new URLSearchParams({
        page: String(page),
        per_page: elements.loadPageSize.value,
    });
    const query = elements.search.value.trim();
    const status = elements.statusFilter.value;
    if (query) params.set('q', query);
    if (status) params.set('estado', status);

    const response = await api(`/api/cargas?${params}`);
    if (requestId !== state.loadRequestId) return;

    const pagination = paginationFrom(response, Number(elements.loadPageSize.value));
    if (pagination.currentPage > pagination.lastPage) {
        await loadCatalog(pagination.lastPage);
        return;
    }

    state.loads = response.data;
    state.loadPagination = pagination;
    renderCatalog();
}

async function loadCameras() {
    const response = await api('/api/camaras');
    state.cameras = response.data;
    populateCameraOptions(state.selected?.camara_objetivo?.id || '');
}

async function loadAvailableFolios(page = state.availablePagination.currentPage) {
    const requestId = ++state.availableRequestId;
    const params = new URLSearchParams({
        page: String(page),
        per_page: elements.availableFolioPageSize.value,
    });
    const query = elements.availableFolioSearch.value.trim();
    if (query) params.set('q', query);

    const response = await api(`/api/cargas/folios-disponibles?${params}`);
    if (requestId !== state.availableRequestId) return;

    const pagination = paginationFrom(response, Number(elements.availableFolioPageSize.value));
    if (pagination.currentPage > pagination.lastPage) {
        await loadAvailableFolios(pagination.lastPage);
        return;
    }

    state.availableFolios = response.data;
    state.availablePagination = pagination;
    renderAvailableFolios();
}

async function loadInitialData() {
    const requests = [loadCatalog(1), loadCameras()];
    if (state.identity?.puede_gestionar_cargas === true) {
        requests.push(loadAvailableFolios(1));
    }
    await Promise.all(requests);
}

async function selectLoad(id, { busy = true } = {}) {
    if (busy) setBusy(true, 'Cargando orden…');
    try {
        const response = await api(`/api/cargas/${id}`);
        syncLoad(response.data);
    } catch (error) {
        toast(error.message, true);
    } finally {
        if (busy) setBusy(false);
    }
}

async function recoverConflict(error) {
    if (error.status !== 409 || !state.selected?.id) return false;
    const id = state.selected.id;
    toast('La orden cambió en otra sesión. Recargamos la versión más reciente.', true);
    await selectLoad(id, { busy: false });
    await loadAvailableFolios(state.availablePagination.currentPage);
    elements.headerError.textContent = 'La acción no se guardó porque otra persona había modificado la orden. Revisa los datos actualizados y vuelve a intentarlo.';
    return true;
}

function loadPayload(includeVersion = false) {
    const form = new FormData(elements.headerForm);
    const payload = {
        numero_orden_externa: String(form.get('numero_orden_externa') || '').trim() || null,
        prioridad: form.get('prioridad') || 'normal',
        camara_objetivo_id: form.get('camara_objetivo_id') || null,
        observacion: String(form.get('observacion') || '').trim() || null,
    };
    if (includeVersion) payload.version_esperada = state.selected.version;
    return payload;
}

function updateFolioInputCount() {
    const count = parseFolios().length;
    const remaining = remainingFolioSlots();
    const exceedsCapacity = state.mode === 'detail' && count > remaining;
    const countLabel = `${count} ${count === 1 ? 'folio único detectado' : 'folios únicos detectados'}`;

    elements.folioInputCount.textContent = exceedsCapacity
        ? `${countLabel} · solo ${remaining} ${remaining === 1 ? 'cupo disponible' : 'cupos disponibles'}`
        : countLabel;
    elements.folioInputCount.classList.toggle('is-error', exceedsCapacity);
    elements.addFolios.disabled = count === 0 || exceedsCapacity;
}

elements.loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.loginError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.loginForm));
    setBusy(true, 'Validando acceso…');
    try {
        const payload = await api('/api/acceso-oficina', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        if (!payload.usuario.puede_consultar_cargas) {
            throw new ApiError('Tu perfil no puede consultar órdenes de carga.', 403);
        }
        persistSession(payload);
        showApp();
        await loadInitialData();
        showEmpty();
    } catch (error) {
        elements.loginError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

[elements.newLoad, elements.emptyNewLoad].forEach((button) => {
    button.addEventListener('click', startNew);
});

elements.discardNew.addEventListener('click', showEmpty);

elements.search.addEventListener('input', () => {
    window.clearTimeout(loadSearchTimer);
    elements.catalogSummary.textContent = 'Buscando órdenes…';
    loadSearchTimer = window.setTimeout(() => {
        void loadCatalog(1).catch((error) => toast(error.message, true));
    }, 300);
});

elements.statusFilter.addEventListener('change', () => {
    void loadCatalog(1).catch((error) => toast(error.message, true));
});

elements.loadPageSize.addEventListener('change', () => {
    void loadCatalog(1).catch((error) => toast(error.message, true));
});

elements.loadPagination.addEventListener('click', (event) => {
    const button = event.target.closest('[data-load-page]');
    if (!button || button.disabled) return;
    void loadCatalog(Number(button.dataset.loadPage)).catch((error) => toast(error.message, true));
});

elements.list.addEventListener('click', (event) => {
    const button = event.target.closest('[data-load-id]');
    if (!button) return;
    void selectLoad(button.dataset.loadId);
});

elements.headerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.headerError.textContent = '';
    const creating = state.mode === 'new';
    if (!creating && !canEdit(state.selected)) return;

    setBusy(true, creating ? 'Creando borrador…' : 'Guardando encabezado…');
    try {
        const response = await api(
            creating ? '/api/cargas' : `/api/cargas/${state.selected.id}`,
            {
                method: creating ? 'POST' : 'PUT',
                body: JSON.stringify(loadPayload(!creating)),
            },
        );
        syncLoad(response.data);
        await loadCatalog(creating ? 1 : state.loadPagination.currentPage);
        toast(`${response.data.codigo} fue ${creating ? 'creada' : 'actualizada'} correctamente.`);
    } catch (error) {
        if (!(await recoverConflict(error))) elements.headerError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.folioInput.addEventListener('input', () => {
    clearFolioErrors();
    updateFolioInputCount();
    renderAvailableFolios();
});

elements.availableFolioSearch.addEventListener('input', () => {
    window.clearTimeout(availableSearchTimer);
    elements.availableFolioSummary.textContent = 'Buscando folios disponibles…';
    availableSearchTimer = window.setTimeout(() => {
        void loadAvailableFolios(1).catch((error) => toast(error.message, true));
    }, 300);
});

elements.availableFolioPageSize.addEventListener('change', () => {
    void loadAvailableFolios(1).catch((error) => toast(error.message, true));
});

elements.availableFolioPagination.addEventListener('click', (event) => {
    const button = event.target.closest('[data-available-page]');
    if (!button || button.disabled) return;
    void loadAvailableFolios(Number(button.dataset.availablePage))
        .catch((error) => toast(error.message, true));
});

elements.availableFolioList.addEventListener('change', (event) => {
    const checkbox = event.target.closest('[data-available-folio]');
    if (!checkbox) return;

    const folios = new Set(parseFolios());
    if (checkbox.checked) {
        if (!folios.has(checkbox.dataset.availableFolio) && folios.size >= remainingFolioSlots()) {
            checkbox.checked = false;
            toast('La orden alcanzó el máximo de 26 folios.', true);
            renderAvailableFolios();
            return;
        }
        folios.add(checkbox.dataset.availableFolio);
    } else {
        folios.delete(checkbox.dataset.availableFolio);
    }
    elements.folioInput.value = [...folios].join('\n');
    clearFolioErrors();
    updateFolioInputCount();
    renderAvailableFolios();
});

elements.availableFolioSelectPage.addEventListener('change', () => {
    const folios = new Set(parseFolios());

    if (!elements.availableFolioSelectPage.checked) {
        state.availableFolios.forEach((folio) => folios.delete(folio.numero_folio));
    } else {
        let remaining = Math.max(0, remainingFolioSlots() - folios.size);
        let omitted = 0;
        state.availableFolios.forEach((folio) => {
            if (folios.has(folio.numero_folio)) return;
            if (remaining <= 0) {
                omitted += 1;
                return;
            }
            folios.add(folio.numero_folio);
            remaining -= 1;
        });
        if (omitted > 0) toast(`Se alcanzó el máximo de 26 folios; ${omitted} quedaron sin seleccionar.`, true);
    }

    elements.folioInput.value = [...folios].join('\n');
    clearFolioErrors();
    updateFolioInputCount();
    renderAvailableFolios();
});

elements.reloadAvailableFolios.addEventListener('click', async () => {
    setBusy(true, 'Actualizando folios disponibles…');
    try {
        await loadAvailableFolios(state.availablePagination.currentPage);
        toast('Existencia de folios actualizada.');
    } catch (error) {
        toast(error.message, true);
    } finally {
        setBusy(false);
    }
});

elements.addFolios.addEventListener('click', async () => {
    const folios = parseFolios();
    if (!folios.length || !canEdit(state.selected)) return;
    clearFolioErrors();
    setBusy(true, `Validando ${folios.length} ${folios.length === 1 ? 'folio' : 'folios'}…`);
    try {
        const response = await api(`/api/cargas/${state.selected.id}/folios`, {
            method: 'POST',
            body: JSON.stringify({
                folios,
                version_esperada: state.selected.version,
            }),
        });
        syncLoad(response.data);
        await Promise.all([
            loadCatalog(state.loadPagination.currentPage),
            loadAvailableFolios(state.availablePagination.currentPage),
        ]);
        toast(`${folios.length} ${folios.length === 1 ? 'folio incorporado' : 'folios incorporados'} a ${response.data.codigo}.`);
    } catch (error) {
        if (!(await recoverConflict(error))) showFolioErrors(error);
    } finally {
        setBusy(false);
    }
});

elements.folioTableBody.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-remove-folio]');
    if (!button || !canEdit(state.selected)) return;
    const folioNumber = button.dataset.folioNumber;
    if (!window.confirm(`¿Quitar ${folioNumber} de ${state.selected.codigo}? El evento quedará registrado.`)) return;

    setBusy(true, `Quitando ${folioNumber}…`);
    try {
        const response = await api(
            `/api/cargas/${state.selected.id}/folios/${button.dataset.removeFolio}`,
            {
                method: 'DELETE',
                body: JSON.stringify({
                    version_esperada: state.selected.version,
                    motivo: 'Desasignación desde oficina de despacho',
                }),
            },
        );
        syncLoad(response.data);
        await Promise.all([
            loadCatalog(state.loadPagination.currentPage),
            loadAvailableFolios(state.availablePagination.currentPage),
        ]);
        toast(`${folioNumber} fue retirado de la orden.`);
    } catch (error) {
        if (!(await recoverConflict(error))) toast(error.message, true);
    } finally {
        setBusy(false);
    }
});

elements.publish.addEventListener('click', async () => {
    if (!canPublish(state.selected)) return;
    if (!window.confirm(`¿Publicar ${state.selected.codigo} con ${state.selected.total_folios} folios? Quedará visible para los camareros y en el plano.`)) return;

    setBusy(true, 'Publicando orden para la operación…');
    try {
        const response = await api(`/api/cargas/${state.selected.id}/publicar`, {
            method: 'POST',
            body: JSON.stringify({ version_esperada: state.selected.version }),
        });
        syncLoad(response.data);
        await loadCatalog(state.loadPagination.currentPage);
        toast(`${response.data.codigo} fue publicada para la operación.`);
    } catch (error) {
        if (!(await recoverConflict(error))) toast(error.message, true);
    } finally {
        setBusy(false);
    }
});

elements.cancel.addEventListener('click', async () => {
    if (!canCancel(state.selected)) return;
    const reason = window.prompt(
        `Indica el motivo de cancelación de ${state.selected.codigo}.`,
        'Cancelación desde oficina de despacho',
    );
    if (reason === null) return;

    setBusy(true, 'Cancelando orden y liberando folios…');
    try {
        const response = await api(`/api/cargas/${state.selected.id}/cancelar`, {
            method: 'POST',
            body: JSON.stringify({
                version_esperada: state.selected.version,
                motivo: reason.trim() || null,
            }),
        });
        syncLoad(response.data);
        await Promise.all([
            loadCatalog(state.loadPagination.currentPage),
            loadAvailableFolios(state.availablePagination.currentPage),
        ]);
        toast(`${response.data.codigo} fue cancelada. Sus folios quedaron liberados.`);
    } catch (error) {
        if (!(await recoverConflict(error))) toast(error.message, true);
    } finally {
        setBusy(false);
    }
});

elements.reload.addEventListener('click', async () => {
    const selectedId = state.selected?.id;
    setBusy(true, 'Actualizando órdenes…');
    try {
        await loadInitialData();
        if (selectedId) await selectLoad(selectedId, { busy: false });
        else renderCatalog();
        toast('Órdenes actualizadas.');
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
    updateFolioInputCount();
    if (!state.token || !state.identity?.puede_consultar_cargas) return;
    showApp();
    setBusy(true, 'Cargando órdenes de carga…');
    try {
        await loadInitialData();
        showEmpty();
    } catch (error) {
        if (error.status !== 401) toast(error.message, true);
    } finally {
        setBusy(false);
    }
}

void boot();
