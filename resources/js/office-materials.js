const byId = (id) => document.getElementById(id);
const elements = {
    access: byId('officeAccess'), app: byId('officeApp'), login: byId('officeLoginForm'),
    loginError: byId('officeLoginError'), userName: byId('officeUserName'), userRole: byId('officeUserRole'),
    initials: byId('officeInitials'), logout: byId('officeLogoutButton'), camerasNav: byId('officeCamerasNav'),
    loadsNav: byId('officeLoadsNav'), prefrioNav: byId('officePrefrioNav'), accessesNav: byId('officeAccessesNav'), managementNav: byId('officeManagementNav'), romanaNav: byId('officeRomanaNav'),
    reload: byId('reloadMaterialsButton'), admin: byId('materialsAdminCatalogs'), itemForm: byId('itemMaterialForm'),
    itemError: byId('itemMaterialError'), itemCancel: byId('cancelItemEdit'), itemList: byId('itemsMaterialList'),
    seasonForm: byId('seasonMaterialForm'), seasonError: byId('seasonMaterialError'),
    seasonCancel: byId('cancelSeasonEdit'), seasonList: byId('seasonsMaterialList'),
    seasonSelector: byId('materialSeasonSelector'), seasonActive: byId('materialsSeasonActive'),
    clientForm: byId('clientMaterialForm'), clientError: byId('clientMaterialError'),
    clientCancel: byId('cancelClientEdit'), clientList: byId('clientsMaterialList'),
    destinationForm: byId('destinationMaterialForm'), destinationError: byId('destinationMaterialError'),
    destinationCancel: byId('cancelDestinationEdit'), destinationList: byId('destinationsMaterialList'),
    dispatchForm: byId('dispatchMaterialForm'), dispatchError: byId('dispatchMaterialError'),
    dispatchDestination: byId('dispatchDestination'), dispatchLines: byId('dispatchMaterialLines'),
    stockSync: byId('materialsStockSync'),
    addDispatchLine: byId('addDispatchLine'), dispatchList: byId('dispatchMaterialList'),
    inventorySearch: byId('materialsInventorySearch'), inventoryBody: byId('materialsInventoryBody'),
    clientCount: byId('materialsClientCount'), itemCount: byId('materialsItemCount'), folioCount: byId('materialsFolioCount'),
    dispatchCount: byId('materialsDispatchCount'), destinationCount: byId('materialsDestinationCount'),
    seasonsSummary: byId('seasonsSummary'), clientsSummary: byId('clientsSummary'), itemsSummary: byId('itemsSummary'), destinationsSummary: byId('destinationsSummary'),
    importOpen: byId('openMaterialImport'), importDialog: byId('materialImportDialog'),
    importClose: byId('closeMaterialImport'), importForm: byId('materialImportForm'),
    importTemplate: byId('downloadMaterialTemplate'), importError: byId('materialImportError'),
    importPreview: byId('materialImportPreview'), importMetrics: byId('materialImportMetrics'),
    importErrors: byId('materialImportErrors'), importRows: byId('materialImportRows'),
    importConfirm: byId('confirmMaterialImport'), importConfirmationHelp: byId('materialImportConfirmationHelp'),
    importHistory: byId('materialImportHistory'),
    loading: byId('officeLoading'), loadingText: byId('officeLoadingText'), toasts: byId('officeToasts'),
};
const keys = { token: 'estiba_wms_office_token', identity: 'estiba_wms_office_identity' };
const state = {
    token: localStorage.getItem(keys.token), identity: readJson(keys.identity),
    seasons: [], selectedSeasonId: null, clients: [], items: [], destinations: [], dispatches: [], inventory: [], imports: [], importPreview: null, dispatchOperationId: null,
    cancellationOperations: new Map(), operationalRefreshInFlight: false, inventorySyncedAt: null,
};

class ApiError extends Error { constructor(message, status) { super(message); this.status = status; } }
function readJson(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; } }
function escapeHtml(value) { return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }
function errorMessage(data, fallback) { return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback; }
function statusText(value) { return String(value || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()); }
function quantity(value) { return new Intl.NumberFormat('es-CL', { maximumFractionDigits: 3 }).format(Number(value || 0)); }
function dateTime(value) { return value ? new Intl.DateTimeFormat('es-CL', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : 'Pendiente'; }
function operationUuid() {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40; bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

async function api(path, options = {}) {
    const headers = new Headers(options.headers || {}); headers.set('Accept', 'application/json');
    if (state.token) headers.set('Authorization', `Bearer ${state.token}`);
    if (options.body && ! (options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
    let response;
    try { response = await fetch(path, { ...options, headers }); } catch { throw new ApiError('No fue posible conectar con Laravel.', 0); }
    const data = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) { if (response.status === 401) clearSession(); throw new ApiError(errorMessage(data, 'No fue posible completar la operación.'), response.status); }
    return data;
}
function setBusy(active, message = 'Procesando…') { elements.loadingText.textContent = message; elements.loading.classList.toggle('is-hidden', !active); elements.loading.setAttribute('aria-hidden', String(!active)); }
function toast(message, error = false) { const node = document.createElement('div'); node.className = `toast${error ? ' toast--error' : ''}`; node.textContent = message; elements.toasts.append(node); window.setTimeout(() => node.remove(), 4500); }
function persist(payload) { state.token = payload.token; state.identity = payload.usuario; localStorage.setItem(keys.token, payload.token); localStorage.setItem(keys.identity, JSON.stringify(payload.usuario)); }
function clearSession() { state.token = null; state.identity = null; localStorage.removeItem(keys.token); localStorage.removeItem(keys.identity); elements.app.classList.add('is-hidden'); elements.access.classList.remove('is-hidden'); }
function showApp() {
    elements.access.classList.add('is-hidden'); elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Usuario'; elements.userName.textContent = name; elements.userRole.textContent = statusText(state.identity?.rol);
    elements.initials.textContent = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    elements.accessesNav.classList.toggle('is-hidden', state.identity?.puede_administrar_accesos !== true);
    elements.managementNav.classList.toggle('is-hidden', state.identity?.puede_consultar_panel_gerencial !== true);
    elements.romanaNav.classList.toggle('is-hidden', state.identity?.puede_consultar_romana !== true);
    elements.loadsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_cargas !== true);
    elements.prefrioNav.classList.toggle('is-hidden', state.identity?.puede_consultar_prefrio !== true);
    elements.admin.classList.toggle('is-hidden', state.identity?.puede_administrar_catalogos_materiales !== true);
    elements.dispatchForm.classList.toggle(
        'is-hidden',
        state.identity?.puede_gestionar_despachos_materiales !== true,
    );
}

function selectedSeason() { return state.seasons.find((season) => season.id === state.selectedSeasonId) || null; }
function seasonClients() { return state.clients.filter((client) => client.temporada?.id === state.selectedSeasonId); }
function seasonItems() { return state.items.filter((item) => item.cliente?.temporada?.id === state.selectedSeasonId); }
function activeItems() { return state.items.filter((item) => item.activo && item.cliente?.activo !== false && item.cliente?.temporada?.activa !== false); }
function activeDestinations() { return state.destinations.filter((destination) => destination.activo); }
function normalizedSearch(value) { return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
function itemLabel(item) { return `${item.cliente?.temporada?.codigo || ''} · ${item.cliente?.codigo || ''} · ${item.codigo} · ${item.nombre}`; }
function itemSearchText(item) { return normalizedSearch(`${itemLabel(item)} ${item.cliente?.nombre || ''} ${item.categoria || ''}`); }
function availableStock(itemId) {
    return Math.round(state.inventory
        .filter((folio) => folio.item.id === itemId)
        .reduce((total, folio) => total + Number(folio.cantidad_disponible || 0), 0) * 1000) / 1000;
}
function closeItemResults(except = null) {
    elements.dispatchLines.querySelectorAll('.material-item-results').forEach((results) => {
        if (results === except) return;
        results.classList.add('is-hidden');
        results.closest('.material-item-picker')?.querySelector('.material-item-search')?.setAttribute('aria-expanded', 'false');
    });
}
function matchingItems(query) {
    const normalized = normalizedSearch(query);
    return activeItems()
        .filter((item) => !normalized || itemSearchText(item).includes(normalized))
        .sort((left, right) => {
            const stockDifference = Number(availableStock(right.id) > 0) - Number(availableStock(left.id) > 0);
            return stockDifference || itemLabel(left).localeCompare(itemLabel(right), 'es');
        })
        .slice(0, 12);
}
function renderItemResults(row) {
    const input = row.querySelector('.material-item-search');
    const results = row.querySelector('.material-item-results');
    const matches = matchingItems(input.value);
    results.innerHTML = matches.map((item) => {
        const stock = availableStock(item.id);
        return `<button class="material-item-option" data-select-item="${item.id}" type="button" role="option"${stock <= 0 ? ' disabled' : ''}><span>${escapeHtml(itemLabel(item))}</span><strong>${stock > 0 ? `${quantity(stock)} ${escapeHtml(item.unidad_medida)} disponibles` : 'Sin stock disponible'}</strong></button>`;
    }).join('') || '<p class="material-item-empty">No hay ítems que coincidan con la búsqueda.</p>';
    results.classList.remove('is-hidden');
    input.setAttribute('aria-expanded', 'true');
}
function updateDispatchLine(row) {
    const itemId = row.querySelector('[name="item_material_id"]').value;
    const input = row.querySelector('.material-item-search');
    const amount = row.querySelector('[name="cantidad"]');
    const stockHint = row.querySelector('.material-stock');
    const item = activeItems().find((candidate) => candidate.id === itemId);
    if (!item) {
        stockHint.textContent = 'Escribe para buscar un ítem con existencia.';
        stockHint.classList.remove('material-stock--empty');
        amount.disabled = true;
        amount.removeAttribute('max');
        row.classList.remove('dispatch-line--unavailable');
        return;
    }
    const stock = availableStock(item.id);
    if (document.activeElement !== input) input.value = itemLabel(item);
    amount.disabled = stock <= 0;
    amount.max = String(stock);
    amount.setAttribute('aria-describedby', stockHint.id);
    stockHint.textContent = stock > 0
        ? `Stock disponible: ${quantity(stock)} ${item.unidad_medida}`
        : `Sin stock disponible en cámaras de materiales`;
    stockHint.classList.toggle('material-stock--empty', stock <= 0);
    row.classList.toggle('dispatch-line--unavailable', stock <= 0 || Number(amount.value || 0) > stock);
}
function refreshDispatchLines() {
    elements.dispatchLines.querySelectorAll('.dispatch-line').forEach((row) => {
        updateDispatchLine(row);
        if (!row.querySelector('.material-item-results').classList.contains('is-hidden')) renderItemResults(row);
    });
    elements.stockSync.textContent = state.inventorySyncedAt
        ? `Stock actualizado ${state.inventorySyncedAt}`
        : 'Consultando stock disponible…';
}
function renderMetrics() {
    elements.seasonActive.textContent = state.seasons.find((season) => season.activa)?.codigo || '—'; elements.clientCount.textContent = String(seasonClients().filter((client) => client.activo).length); elements.itemCount.textContent = String(seasonItems().filter((item) => item.activo && item.cliente?.activo !== false).length); elements.folioCount.textContent = String(state.inventory.length);
    elements.dispatchCount.textContent = String(state.dispatches.filter((dispatch) => ['pendiente', 'parcial'].includes(dispatch.estado)).length);
    elements.destinationCount.textContent = String(activeDestinations().length);
}
function renderSeasons() {
    const canAdminister = state.identity?.puede_administrar_catalogos_materiales === true;
    elements.seasonsSummary.textContent = `${state.seasons.length} registradas`;
    elements.seasonSelector.innerHTML = state.seasons.map((season) => `<option value="${season.id}">${escapeHtml(season.codigo)} · ${escapeHtml(season.nombre)}${season.activa ? ' (activa)' : ''}</option>`).join('') || '<option value="">Sin temporadas</option>';
    elements.seasonSelector.value = state.selectedSeasonId || '';
    elements.seasonList.innerHTML = state.seasons.map((season) => `<article class="material-row${season.activa ? '' : ' is-inactive'}"><div><strong>${escapeHtml(season.codigo)} · ${escapeHtml(season.nombre)}</strong><small>${escapeHtml(season.fecha_inicio || 'Sin inicio')} → ${escapeHtml(season.fecha_fin || 'Sin término')} · ${season.clientes_activos} clientes · ${season.items_activos} ítems</small></div>${canAdminister ? `<div><button data-edit-season="${season.id}" type="button">Editar</button>${season.activa ? '' : `<button data-activate-season="${season.id}" type="button">Activar</button>`}</div>` : ''}</article>`).join('') || '<p class="empty-state">No existen temporadas.</p>';
}
function renderClients() {
    const canAdminister = state.identity?.puede_administrar_catalogos_materiales === true;
    const clients = seasonClients();
    elements.clientsSummary.textContent = `${clients.length} registrados`;
    elements.clientList.innerHTML = clients.map((client) => `<article class="material-row${client.activo ? '' : ' is-inactive'}"><div><strong>${escapeHtml(client.codigo)} · ${escapeHtml(client.nombre)}</strong><small>${client.items_activos} ítems activos${client.codigo_externo ? ` · ${escapeHtml(client.codigo_externo)}` : ''}</small></div>${canAdminister ? `<button data-edit-client="${client.id}" type="button">Editar</button>` : ''}</article>`).join('') || '<p class="empty-state">No existen clientes en esta temporada.</p>';
    elements.clientForm.elements.temporada_material_id.value = state.selectedSeasonId || '';
    const current = elements.itemForm.elements.cliente_material_id.value;
    elements.itemForm.elements.cliente_material_id.innerHTML = '<option value="">Selecciona un cliente</option>' + clients.filter((client) => client.activo).map((client) => `<option value="${client.id}">${escapeHtml(client.codigo)} · ${escapeHtml(client.nombre)}</option>`).join('');
    if ([...elements.itemForm.elements.cliente_material_id.options].some((option) => option.value === current)) elements.itemForm.elements.cliente_material_id.value = current;
}
function renderItems() {
    const canAdminister = state.identity?.puede_administrar_catalogos_materiales === true;
    const items = seasonItems();
    elements.itemsSummary.textContent = `${items.length} registrados`;
    elements.itemList.innerHTML = items.map((item) => `<article class="material-row${item.activo ? '' : ' is-inactive'}"><div><strong>${escapeHtml(item.cliente?.codigo || 'SIN CLIENTE')} · ${escapeHtml(item.codigo)} · ${escapeHtml(item.nombre)}</strong><small>${escapeHtml(item.categoria || 'Sin categoría')} · ${escapeHtml(item.unidad_medida)} · ${item.folios_activos} folios activos</small></div>${canAdminister ? `<button data-edit-item="${item.id}" type="button">Editar</button>` : ''}</article>`).join('') || '<p class="empty-state">No existen ítems en esta temporada.</p>';
    refreshDispatchLines();
}
function renderDestinations() {
    const canAdminister = state.identity?.puede_administrar_catalogos_materiales === true;
    elements.destinationsSummary.textContent = `${state.destinations.length} registrados`;
    elements.destinationList.innerHTML = state.destinations.map((destination) => `<article class="material-row${destination.activo ? '' : ' is-inactive'}"><div><strong>${escapeHtml(destination.nombre)}</strong><small>${escapeHtml(destination.centro_costo)}${destination.descripcion ? ` · ${escapeHtml(destination.descripcion)}` : ''}</small></div>${canAdminister ? `<button data-edit-destination="${destination.id}" type="button">Editar</button>` : ''}</article>`).join('') || '<p class="empty-state">No existen destinos.</p>';
    elements.dispatchDestination.innerHTML = '<option value="">Selecciona un destino</option>' + activeDestinations().map((destination) => `<option value="${destination.id}">${escapeHtml(destination.nombre)} · ${escapeHtml(destination.centro_costo)}</option>`).join('');
}
function renderDispatches() {
    elements.dispatchList.innerHTML = state.dispatches.map((dispatch) => {
        const detail = dispatch.items.map((item) => `${item.item.cliente?.temporada?.codigo || ''}/${item.item.cliente?.codigo || ''}/${item.item.codigo}: ${quantity(item.cantidad_despachada)}/${quantity(item.cantidad_solicitada)} ${item.unidad_medida}`).join(' · ');
        const shortage = dispatch.items.some((item) => Number(item.cantidad_reservada) + Number(item.cantidad_despachada) < Number(item.cantidad_solicitada));
        const canCancel = state.identity?.puede_cancelar_despachos_materiales === true;
        return `<article class="dispatch-row"><div><strong>${escapeHtml(dispatch.codigo)} · ${escapeHtml(dispatch.destino.nombre)}</strong><small>${escapeHtml(dispatch.destino.centro_costo)} · ${escapeHtml(detail)}${shortage ? ' · Falta existencia por reservar' : ''}</small></div><div class="dispatch-row__state"><span>${escapeHtml(statusText(dispatch.estado))}</span>${canCancel && ['pendiente', 'parcial'].includes(dispatch.estado) ? `<button data-cancel-dispatch="${dispatch.id}" type="button">Cancelar</button>` : ''}</div></article>`;
    }).join('') || '<p class="empty-state">No existen despachos de materiales.</p>';
}
function renderInventory() {
    const query = elements.inventorySearch.value.trim().toLowerCase();
    const rows = state.inventory.filter((folio) => `${folio.numero_folio} ${folio.item.cliente?.temporada?.codigo || ''} ${folio.item.cliente?.codigo || ''} ${folio.item.cliente?.nombre || ''} ${folio.item.codigo} ${folio.item.nombre} ${folio.camara?.codigo || ''} ${folio.posicion?.etiqueta || ''}`.toLowerCase().includes(query));
    elements.inventoryBody.innerHTML = rows.map((folio) => `<tr><td><strong>${escapeHtml(folio.numero_folio)}</strong><small>${escapeHtml(folio.lote || 'Sin lote')}</small></td><td><strong>${escapeHtml(folio.item.cliente?.temporada?.codigo || '—')} · ${escapeHtml(folio.item.cliente?.codigo || '—')} · ${escapeHtml(folio.item.codigo)}</strong><small>${escapeHtml(folio.item.nombre)}</small></td><td>${quantity(folio.cantidad_actual)} ${escapeHtml(folio.unidad_medida)}</td><td>${quantity(folio.cantidad_reservada)}</td><td>${quantity(folio.cantidad_disponible)}</td><td><strong>${escapeHtml(folio.camara?.codigo || 'Sin cámara')}</strong><small>${escapeHtml(folio.posicion?.etiqueta || 'Sin posición')}</small></td></tr>`).join('') || '<tr><td colspan="6">No existen folios coincidentes.</td></tr>';
}
function renderImportHistory() {
    elements.importHistory.innerHTML = state.imports.map((entry) => `<article class="material-row"><div><strong>${escapeHtml(entry.nombre_archivo)}</strong><small>${escapeHtml(entry.creado_por?.nombre || 'Usuario')} · ${escapeHtml(dateTime(entry.created_at))}</small></div><span class="material-import-action">${escapeHtml(statusText(entry.estado))}</span></article>`).join('') || '<p class="empty-state">Aún no existen importaciones.</p>';
}
function renderImportPreview() {
    const preview = state.importPreview;
    elements.importPreview.classList.toggle('is-hidden', !preview);
    if (!preview) return;
    const summary = preview.resumen || {};
    const metrics = [
        ['FILAS LEÍDAS', summary.filas_leidas],
        ['VÁLIDAS', summary.filas_validas],
        ['CON ERROR', summary.filas_con_error],
        ['NUEVOS', preview.estado === 'confirmada' ? summary.creados : summary.nuevos_estimados],
        ['ACTUALIZACIONES', preview.estado === 'confirmada' ? summary.actualizados : summary.actualizaciones_estimadas],
    ];
    elements.importMetrics.innerHTML = metrics.map(([label, value]) => `<article><span>${label}</span><strong>${Number(value || 0)}</strong></article>`).join('');
    const errors = preview.errores || [];
    elements.importErrors.classList.toggle('is-hidden', errors.length === 0);
    elements.importErrors.innerHTML = errors.map((error) => `<p><strong>Fila ${Number(error.fila || 0)}${error.codigo ? ` · ${escapeHtml(error.codigo)}` : ''}:</strong> ${escapeHtml(error.mensaje)}</p>`).join('');
    elements.importRows.innerHTML = (preview.filas || []).slice(0, 100).map((row) => `<tr><td>${Number(row.fila)}</td><td><strong>${escapeHtml(row.temporada_codigo)}</strong><small>${escapeHtml(row.temporada_nombre || '')}</small></td><td><strong>${escapeHtml(row.cliente_codigo)}</strong><small>${escapeHtml(row.cliente_nombre || '')}</small></td><td><strong>${escapeHtml(row.codigo)}</strong></td><td>${escapeHtml(row.nombre)}</td><td>${escapeHtml(row.unidad_medida)}</td><td><span class="material-import-action">${escapeHtml(statusText(row.accion))}</span></td></tr>`).join('') || '<tr><td colspan="7">No existen filas válidas para mostrar.</td></tr>';
    const confirmed = preview.estado === 'confirmada';
    elements.importConfirm.disabled = errors.length > 0 || confirmed;
    elements.importConfirmationHelp.textContent = confirmed
        ? `Importación confirmada: ${Number(summary.creados || 0)} creados, ${Number(summary.actualizados || 0)} actualizados y ${Number(summary.sin_cambios || 0)} sin cambios.`
        : errors.length > 0
            ? 'Corrige la planilla y vuelve a previsualizarla. No se aplicó ningún cambio.'
            : 'La confirmación actualizará solo el catálogo. Los ítems ausentes permanecerán sin cambios.';
}
function renderAll() { renderSeasons(); renderClients(); renderItems(); renderDestinations(); renderDispatches(); renderInventory(); renderMetrics(); renderImportHistory(); }

async function loadAll() {
    const catalogAdmin = state.identity?.puede_administrar_catalogos_materiales === true;
    const [catalog, dispatches, inventory] = await Promise.all([
        catalogAdmin ? Promise.all([api('/api/administracion/materiales/temporadas'), api('/api/administracion/materiales/clientes'), api('/api/administracion/materiales/items'), api('/api/administracion/materiales/destinos'), api('/api/administracion/materiales/importaciones')]) : api('/api/materiales/catalogo'),
        api('/api/materiales/despachos'), api('/api/materiales/inventario'),
    ]);
    if (catalogAdmin) { state.seasons = catalog[0].data; state.clients = catalog[1].data; state.items = catalog[2].data; state.destinations = catalog[3].data; state.imports = catalog[4].data; } else { state.seasons = catalog.temporada ? [catalog.temporada] : []; state.clients = catalog.clientes; state.items = catalog.items; state.destinations = catalog.destinos; state.imports = []; }
    if (!state.seasons.some((season) => season.id === state.selectedSeasonId)) state.selectedSeasonId = state.seasons.find((season) => season.activa)?.id || state.seasons[0]?.id || null;
    state.dispatches = dispatches.data; state.inventory = inventory.data;
    state.inventorySyncedAt = new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    renderAll();
}

async function refreshOperationalData() {
    if (state.operationalRefreshInFlight || !state.token || state.identity?.puede_consultar_despachos_materiales !== true) return;
    state.operationalRefreshInFlight = true;
    try {
        const [dispatches, inventory] = await Promise.all([
            api('/api/materiales/despachos'),
            api('/api/materiales/inventario'),
        ]);
        state.dispatches = dispatches.data;
        state.inventory = inventory.data;
        state.inventorySyncedAt = new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        renderDispatches(); renderInventory(); renderMetrics(); refreshDispatchLines();
    } catch (error) {
        if (error.status !== 401) console.warn('No fue posible actualizar el stock de materiales.', error);
    } finally {
        state.operationalRefreshInFlight = false;
    }
}

function addDispatchLine(itemId = '', amount = '') {
    const row = document.createElement('div'); row.className = 'dispatch-line';
    const hintId = `material-stock-${operationUuid()}`;
    const item = activeItems().find((candidate) => candidate.id === itemId);
    row.innerHTML = `<div class="material-item-picker"><input class="material-item-search" type="search" value="${escapeHtml(item ? itemLabel(item) : '')}" placeholder="Buscar ítem, código o cliente" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false"><input name="item_material_id" type="hidden" value="${escapeHtml(itemId)}"><div class="material-item-results is-hidden" role="listbox"></div><small class="material-stock" id="${hintId}" aria-live="polite"></small></div><input name="cantidad" type="number" min="0.001" step="0.001" value="${escapeHtml(amount)}" placeholder="Cantidad" required><button data-remove-line type="button" aria-label="Quitar">×</button>`;
    elements.dispatchLines.append(row);
    updateDispatchLine(row);
}
function dispatchItemsPayload() {
    const seen = new Set();
    return [...elements.dispatchLines.querySelectorAll('.dispatch-line')].map((row, index) => {
        const itemId = row.querySelector('[name="item_material_id"]').value;
        const amount = Number(row.querySelector('[name="cantidad"]').value || 0);
        const item = activeItems().find((candidate) => candidate.id === itemId);
        if (!item) throw new ApiError(`Selecciona un ítem válido en la línea ${index + 1}.`, 422);
        if (seen.has(itemId)) throw new ApiError(`${item.nombre} está repetido en la solicitud.`, 422);
        seen.add(itemId);
        const stock = availableStock(itemId);
        if (stock <= 0) throw new ApiError(`${item.nombre} no tiene stock disponible.`, 422);
        if (amount <= 0) throw new ApiError(`Indica una cantidad válida para ${item.nombre}.`, 422);
        if (amount > stock) throw new ApiError(`${item.nombre}: solicitaste ${quantity(amount)} ${item.unidad_medida}, pero solo hay ${quantity(stock)} disponibles.`, 422);
        return { item_material_id: itemId, cantidad: amount };
    });
}
function resetItemForm() { elements.itemForm.reset(); elements.itemForm.elements.id.value = ''; elements.itemForm.elements.activo.checked = true; elements.itemCancel.classList.add('is-hidden'); elements.itemError.textContent = ''; }
function resetSeasonForm() { elements.seasonForm.reset(); elements.seasonForm.elements.id.value = ''; elements.seasonCancel.classList.add('is-hidden'); elements.seasonError.textContent = ''; }
function resetClientForm() { elements.clientForm.reset(); elements.clientForm.elements.id.value = ''; elements.clientForm.elements.activo.checked = true; elements.clientCancel.classList.add('is-hidden'); elements.clientError.textContent = ''; }
function resetDestinationForm() { elements.destinationForm.reset(); elements.destinationForm.elements.id.value = ''; elements.destinationForm.elements.activo.checked = true; elements.destinationCancel.classList.add('is-hidden'); elements.destinationError.textContent = ''; }
function resetImportPreview() { state.importPreview = null; elements.importError.textContent = ''; renderImportPreview(); }

elements.login.addEventListener('submit', async (event) => { event.preventDefault(); elements.loginError.textContent = ''; setBusy(true, 'Validando acceso…'); try { const payload = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(elements.login))) }); if (payload.usuario.puede_consultar_despachos_materiales !== true) throw new ApiError('Tu perfil no puede consultar materiales.', 403); persist(payload); showApp(); await loadAll(); } catch (error) { elements.loginError.textContent = error.message; } finally { setBusy(false); } });
elements.seasonForm.addEventListener('submit', async (event) => { event.preventDefault(); elements.seasonError.textContent = ''; const data = Object.fromEntries(new FormData(elements.seasonForm)); const id = data.id; delete data.id; data.activa = elements.seasonForm.elements.activa.checked; setBusy(true, 'Guardando temporada…'); try { const response = await api(id ? `/api/administracion/materiales/temporadas/${id}` : '/api/administracion/materiales/temporadas', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) }); state.selectedSeasonId = response.data.id; resetSeasonForm(); await loadAll(); toast('Temporada guardada correctamente.'); } catch (error) { elements.seasonError.textContent = error.message; } finally { setBusy(false); } });
elements.clientForm.addEventListener('submit', async (event) => { event.preventDefault(); elements.clientError.textContent = ''; const data = Object.fromEntries(new FormData(elements.clientForm)); const id = data.id; delete data.id; data.activo = elements.clientForm.elements.activo.checked; setBusy(true, 'Guardando cliente…'); try { await api(id ? `/api/administracion/materiales/clientes/${id}` : '/api/administracion/materiales/clientes', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) }); resetClientForm(); await loadAll(); toast('Cliente guardado correctamente.'); } catch (error) { elements.clientError.textContent = error.message; } finally { setBusy(false); } });
elements.itemForm.addEventListener('submit', async (event) => { event.preventDefault(); elements.itemError.textContent = ''; const data = Object.fromEntries(new FormData(elements.itemForm)); const id = data.id; delete data.id; data.activo = elements.itemForm.elements.activo.checked; setBusy(true, 'Guardando ítem…'); try { await api(id ? `/api/administracion/materiales/items/${id}` : '/api/administracion/materiales/items', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) }); resetItemForm(); await loadAll(); toast('Ítem guardado correctamente.'); } catch (error) { elements.itemError.textContent = error.message; } finally { setBusy(false); } });
elements.destinationForm.addEventListener('submit', async (event) => { event.preventDefault(); elements.destinationError.textContent = ''; const data = Object.fromEntries(new FormData(elements.destinationForm)); const id = data.id; delete data.id; data.activo = elements.destinationForm.elements.activo.checked; setBusy(true, 'Guardando destino…'); try { await api(id ? `/api/administracion/materiales/destinos/${id}` : '/api/administracion/materiales/destinos', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) }); resetDestinationForm(); await loadAll(); toast('Destino guardado correctamente.'); } catch (error) { elements.destinationError.textContent = error.message; } finally { setBusy(false); } });
elements.seasonSelector.addEventListener('change', () => { state.selectedSeasonId = elements.seasonSelector.value || null; resetClientForm(); resetItemForm(); renderAll(); });
elements.seasonList.addEventListener('click', (event) => { const edit = event.target.closest('[data-edit-season]'); const activate = event.target.closest('[data-activate-season]'); if (edit) { const season = state.seasons.find((candidate) => candidate.id === edit.dataset.editSeason); if (!season) return; for (const field of ['id', 'codigo', 'nombre', 'fecha_inicio', 'fecha_fin']) elements.seasonForm.elements[field].value = season[field] || ''; elements.seasonForm.elements.activa.checked = season.activa; elements.seasonCancel.classList.remove('is-hidden'); } if (activate) { setBusy(true, 'Activando temporada…'); void api(`/api/administracion/materiales/temporadas/${activate.dataset.activateSeason}/activar`, { method: 'POST' }).then((response) => { state.selectedSeasonId = response.data.id; return loadAll(); }).then(() => toast('Temporada activada.')).catch((error) => toast(error.message, true)).finally(() => setBusy(false)); } });
elements.clientList.addEventListener('click', (event) => { const button = event.target.closest('[data-edit-client]'); if (!button) return; const client = state.clients.find((candidate) => candidate.id === button.dataset.editClient); if (!client) return; for (const field of ['id', 'codigo', 'nombre', 'codigo_externo']) elements.clientForm.elements[field].value = client[field] || ''; elements.clientForm.elements.activo.checked = client.activo; elements.clientCancel.classList.remove('is-hidden'); });
elements.itemList.addEventListener('click', (event) => { const button = event.target.closest('[data-edit-item]'); if (!button) return; const item = state.items.find((candidate) => candidate.id === button.dataset.editItem); if (!item) return; for (const field of ['id', 'codigo', 'nombre', 'categoria', 'unidad_medida', 'codigo_externo']) elements.itemForm.elements[field].value = item[field] || ''; elements.itemForm.elements.cliente_material_id.value = item.cliente?.id || ''; elements.itemForm.elements.activo.checked = item.activo; elements.itemCancel.classList.remove('is-hidden'); });
elements.destinationList.addEventListener('click', (event) => { const button = event.target.closest('[data-edit-destination]'); if (!button) return; const destination = state.destinations.find((candidate) => candidate.id === button.dataset.editDestination); if (!destination) return; for (const field of ['id', 'nombre', 'centro_costo', 'descripcion', 'codigo_externo']) elements.destinationForm.elements[field].value = destination[field] || ''; elements.destinationForm.elements.activo.checked = destination.activo; elements.destinationCancel.classList.remove('is-hidden'); });
elements.seasonCancel.addEventListener('click', resetSeasonForm); elements.clientCancel.addEventListener('click', resetClientForm); elements.itemCancel.addEventListener('click', resetItemForm); elements.destinationCancel.addEventListener('click', resetDestinationForm);
elements.importOpen.addEventListener('click', () => elements.importDialog.showModal());
elements.importClose.addEventListener('click', () => elements.importDialog.close());
elements.importTemplate.addEventListener('click', () => {
    const content = '\uFEFFtemporada_codigo;cliente_codigo;codigo;nombre;categoria;unidad_medida;codigo_externo;activo\n2026-2027;CLI-001;CAJ-5KG;Caja cartón 5 kg;Cajas;unidad;ERP-1054;si\n';
    const url = URL.createObjectURL(new Blob([content], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a'); link.href = url; link.download = 'plantilla_catalogo_materiales.csv'; link.click();
    URL.revokeObjectURL(url);
});
elements.importForm.addEventListener('submit', async (event) => {
    event.preventDefault(); resetImportPreview(); setBusy(true, 'Revisando planilla de materiales…');
    try {
        const response = await api('/api/administracion/materiales/importaciones/previsualizar', { method: 'POST', body: new FormData(elements.importForm) });
        state.importPreview = response.data; renderImportPreview();
        const history = await api('/api/administracion/materiales/importaciones'); state.imports = history.data; renderImportHistory();
    } catch (error) { elements.importError.textContent = error.message; } finally { setBusy(false); }
});
elements.importConfirm.addEventListener('click', async () => {
    if (!state.importPreview || state.importPreview.estado !== 'borrador') return;
    setBusy(true, 'Confirmando catálogo de materiales…');
    try {
        const response = await api(`/api/administracion/materiales/importaciones/${state.importPreview.id}/confirmar`, { method: 'POST' });
        state.importPreview = response.data; await loadAll(); renderImportPreview(); toast('Catálogo de materiales importado correctamente.');
    } catch (error) { elements.importError.textContent = error.message; } finally { setBusy(false); }
});
elements.addDispatchLine.addEventListener('click', () => { state.dispatchOperationId = null; addDispatchLine(); });
elements.dispatchLines.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-remove-line]');
    if (remove && elements.dispatchLines.children.length > 1) {
        state.dispatchOperationId = null; remove.closest('.dispatch-line').remove(); return;
    }
    const option = event.target.closest('[data-select-item]');
    if (!option) return;
    const row = option.closest('.dispatch-line');
    const item = activeItems().find((candidate) => candidate.id === option.dataset.selectItem);
    if (!item) return;
    row.querySelector('[name="item_material_id"]').value = item.id;
    row.querySelector('.material-item-search').value = itemLabel(item);
    closeItemResults(); updateDispatchLine(row); state.dispatchOperationId = null;
    row.querySelector('[name="cantidad"]').focus();
});
elements.dispatchLines.addEventListener('focusin', (event) => {
    const input = event.target.closest('.material-item-search');
    if (!input) return;
    const results = input.closest('.material-item-picker').querySelector('.material-item-results');
    closeItemResults(results); renderItemResults(input.closest('.dispatch-line'));
});
elements.dispatchLines.addEventListener('input', (event) => {
    const row = event.target.closest('.dispatch-line');
    if (!row) return;
    if (event.target.matches('.material-item-search')) {
        row.querySelector('[name="item_material_id"]').value = '';
        row.querySelector('[name="cantidad"]').value = '';
        updateDispatchLine(row); renderItemResults(row);
    } else if (event.target.matches('[name="cantidad"]')) {
        updateDispatchLine(row);
    }
});
elements.dispatchLines.addEventListener('keydown', (event) => {
    if (!event.target.matches('.material-item-search')) return;
    if (event.key === 'Escape') { closeItemResults(); event.target.blur(); }
    if (event.key === 'Enter') {
        const first = event.target.closest('.material-item-picker').querySelector('[data-select-item]:not(:disabled)');
        if (first) { event.preventDefault(); first.click(); }
    }
});
document.addEventListener('click', (event) => { if (!event.target.closest('.material-item-picker')) closeItemResults(); });
elements.dispatchForm.addEventListener('submit', async (event) => {
    event.preventDefault(); elements.dispatchError.textContent = '';
    try { await refreshOperationalData(); } catch { /* La validación local usará el último estado disponible. */ }
    let items;
    try { items = dispatchItemsPayload(); } catch (error) { elements.dispatchError.textContent = error.message; return; }
    const form = new FormData(elements.dispatchForm);
    state.dispatchOperationId ||= operationUuid();
    const payload = { operacion_id: state.dispatchOperationId, destino_material_id: form.get('destino_material_id'), observacion: form.get('observacion'), items };
    setBusy(true, 'Creando despacho y reservando existencia…');
    try {
        const response = await api('/api/materiales/despachos', { method: 'POST', body: JSON.stringify(payload) });
        state.dispatchOperationId = null; elements.dispatchForm.reset(); elements.dispatchLines.innerHTML = ''; addDispatchLine();
        await loadAll(); toast(`${response.data.codigo} fue creado correctamente.`);
    } catch (error) { elements.dispatchError.textContent = error.message; } finally { setBusy(false); }
});
elements.dispatchForm.addEventListener('input', () => { state.dispatchOperationId = null; });
elements.dispatchList.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-cancel-dispatch]');
    if (!button) return;
    const dispatchId = button.dataset.cancelDispatch;
    const previous = state.cancellationOperations.get(dispatchId);
    const reason = window.prompt(
        'Indica el motivo de la cancelación. Se liberarán las cantidades reservadas:',
        previous?.reason || '',
    );
    if (reason === null) return;
    const normalizedReason = reason.trim();
    if (normalizedReason.length < 3) { toast('El motivo debe contener al menos 3 caracteres.', true); return; }
    const operation = previous?.reason === normalizedReason
        ? previous
        : { id: operationUuid(), reason: normalizedReason };
    state.cancellationOperations.set(dispatchId, operation);
    setBusy(true, 'Cancelando despacho…');
    try {
        await api(`/api/materiales/despachos/${dispatchId}/cancelar`, {
            method: 'POST',
            body: JSON.stringify({ operacion_id: operation.id, motivo: operation.reason }),
        });
        state.cancellationOperations.delete(dispatchId);
        await loadAll();
        toast('Despacho cancelado y reservas liberadas.');
    } catch (error) {
        toast(error.message, true);
    } finally {
        setBusy(false);
    }
});
elements.inventorySearch.addEventListener('input', renderInventory); elements.reload.addEventListener('click', async () => { setBusy(true, 'Actualizando materiales…'); try { await loadAll(); toast('Información actualizada.'); } catch (error) { toast(error.message, true); } finally { setBusy(false); } });
elements.logout.addEventListener('click', async () => { try { await api('/api/acceso-oficina', { method: 'DELETE' }); } finally { clearSession(); } });

async function boot() { addDispatchLine(); if (!state.token || state.identity?.puede_consultar_despachos_materiales !== true) return; showApp(); setBusy(true, 'Cargando materiales…'); try { await loadAll(); } catch (error) { if (error.status !== 401) toast(error.message, true); } finally { setBusy(false); } }
window.setInterval(() => { if (document.visibilityState === 'visible') void refreshOperationalData(); }, 12000);
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') void refreshOperationalData(); });
void boot();
