const byId = (id) => document.getElementById(id);
const elements = {
    access: byId('officeAccess'), app: byId('officeApp'), login: byId('officeLoginForm'),
    loginError: byId('officeLoginError'), userName: byId('officeUserName'), userRole: byId('officeUserRole'),
    initials: byId('officeInitials'), logout: byId('officeLogoutButton'), camerasNav: byId('officeCamerasNav'),
    loadsNav: byId('officeLoadsNav'), prefrioNav: byId('officePrefrioNav'), accessesNav: byId('officeAccessesNav'), managementNav: byId('officeManagementNav'), romanaNav: byId('officeRomanaNav'),
    reload: byId('reloadMaterialsButton'), admin: byId('materialsAdminCatalogs'), itemForm: byId('itemMaterialForm'),
    itemError: byId('itemMaterialError'), itemCancel: byId('cancelItemEdit'), itemList: byId('itemsMaterialList'),
    seasonList: byId('seasonsMaterialList'),
    seasonSelector: byId('materialSeasonSelector'), seasonActive: byId('materialsSeasonActive'),
    clientList: byId('clientsMaterialList'),
    providerForm: byId('providerMaterialForm'), providerError: byId('providerMaterialError'),
    providerCancel: byId('cancelProviderEdit'), providerList: byId('providersMaterialList'),
    providerClientOptions: byId('providerClientOptions'), providersSummary: byId('providersSummary'),
    destinationForm: byId('destinationMaterialForm'), destinationError: byId('destinationMaterialError'),
    destinationCancel: byId('cancelDestinationEdit'), destinationList: byId('destinationsMaterialList'),
    dispatchForm: byId('dispatchMaterialForm'), dispatchError: byId('dispatchMaterialError'),
    dispatchDestination: byId('dispatchDestination'), dispatchLines: byId('dispatchMaterialLines'),
    stockSync: byId('materialsStockSync'),
    addDispatchLine: byId('addDispatchLine'), dispatchList: byId('dispatchMaterialList'),
    inventorySearch: byId('materialsInventorySearch'), inventoryClient: byId('materialsInventoryClient'), inventorySummary: byId('materialsInventorySummary'), inventoryBody: byId('materialsInventoryBody'),
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
    correctionDialog: byId('materialCorrectionDialog'), correctionForm: byId('materialCorrectionForm'),
    correctionContext: byId('materialCorrectionContext'), correctionError: byId('materialCorrectionError'),
    correctionClose: byId('closeMaterialCorrection'), correctionCancel: byId('cancelMaterialCorrection'),
    loading: byId('officeLoading'), loadingText: byId('officeLoadingText'), toasts: byId('officeToasts'),
};
const keys = { token: 'estiba_wms_office_token', identity: 'estiba_wms_office_identity' };
const state = {
    token: localStorage.getItem(keys.token), identity: readJson(keys.identity),
    seasons: [], selectedSeasonId: null, clients: [], providers: [], items: [], destinations: [], dispatches: [], inventory: [], inventorySummary: [], imports: [], importPreview: null, dispatchOperationId: null, correctionOperationId: null,
    cancellationOperations: new Map(), operationalRefreshPromise: null, inventorySyncedAt: null,
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
function globalClients() {
    const prioritized = [...state.clients].sort((left, right) =>
        Number(right.temporada?.id === state.selectedSeasonId) - Number(left.temporada?.id === state.selectedSeasonId));
    return [...new Map(prioritized.filter((client) => client.cliente_id).map((client) => [client.cliente_id, client])).values()];
}
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
    elements.seasonsSummary.textContent = `${state.seasons.length} registradas`;
    elements.seasonSelector.innerHTML = state.seasons.map((season) => `<option value="${season.id}">${escapeHtml(season.codigo)} · ${escapeHtml(season.nombre)}${season.activa ? ' (activa)' : ''}</option>`).join('') || '<option value="">Sin temporadas</option>';
    elements.seasonSelector.value = state.selectedSeasonId || '';
    elements.seasonList.innerHTML = state.seasons.map((season) => `<article class="material-row${season.activa ? '' : ' is-inactive'}"><div><strong>${escapeHtml(season.codigo)} · ${escapeHtml(season.nombre)}</strong><small>${escapeHtml(season.fecha_inicio || 'Sin inicio')} → ${escapeHtml(season.fecha_fin || 'Sin término')} · ${season.clientes_activos} clientes · ${season.items_activos} ítems${season.activa ? ' · activa global' : ''}</small></div></article>`).join('') || '<p class="empty-state">No existen temporadas. Debes crearla en Accesos.</p>';
}
function renderClients() {
    const clients = seasonClients();
    elements.clientsSummary.textContent = `${clients.length} registrados`;
    elements.clientList.innerHTML = clients.map((client) => `<article class="material-row${client.activo ? '' : ' is-inactive'}"><div><strong>${escapeHtml(client.codigo)} · ${escapeHtml(client.nombre)}</strong><small>${client.items_activos} ítems activos${client.codigo_externo ? ` · ERP ${escapeHtml(client.codigo_externo)}` : ''} · administrado en Accesos</small></div></article>`).join('') || '<p class="empty-state">No existen clientes en esta temporada. Créalo o actívalo en Accesos.</p>';
    const current = elements.itemForm.elements.cliente_material_id.value;
    elements.itemForm.elements.cliente_material_id.innerHTML = '<option value="">Selecciona un cliente</option>' + clients.filter((client) => client.activo).map((client) => `<option value="${client.id}">${escapeHtml(client.codigo)} · ${escapeHtml(client.nombre)}</option>`).join('');
    if ([...elements.itemForm.elements.cliente_material_id.options].some((option) => option.value === current)) elements.itemForm.elements.cliente_material_id.value = current;
}
function renderProviders() {
    if (!elements.providerForm) return;
    elements.providersSummary.textContent = `${state.providers.length} registrados`;
    const checked = new Set([...elements.providerClientOptions.querySelectorAll('input:checked')].map((input) => input.value));
    elements.providerClientOptions.innerHTML = globalClients()
        .filter((client) => client.activo)
        .map((client) => `<label><input name="cliente_ids" type="checkbox" value="${client.cliente_id}"${checked.has(client.cliente_id) ? ' checked' : ''}><span>${escapeHtml(client.codigo)} · ${escapeHtml(client.nombre)}</span></label>`)
        .join('') || '<p class="empty-state">No existen clientes activos en Accesos.</p>';
    elements.providerList.innerHTML = state.providers.map((provider) => {
        const clients = (provider.clientes || []).map((client) => `${client.codigo} · ${client.nombre}`).join(', ');
        return `<article class="material-row${provider.activo ? '' : ' is-inactive'}"><div><strong>${escapeHtml(provider.codigo)} · ${escapeHtml(provider.nombre)}</strong><small>${escapeHtml(clients || 'Sin clientes asociados')}${provider.codigo_externo ? ` · ERP ${escapeHtml(provider.codigo_externo)}` : ''}</small></div><button data-edit-provider="${provider.id}" type="button">Editar</button></article>`;
    }).join('') || '<p class="empty-state">No existen proveedores registrados.</p>';
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
    const clients = [...new Map(state.inventory.map((folio) => [folio.item.cliente.id, folio.item.cliente])).values()];
    const currentClient = elements.inventoryClient.value;
    elements.inventoryClient.innerHTML = '<option value="">Todos los clientes</option>' + clients.map((client) => `<option value="${client.id}">${escapeHtml(client.codigo)} · ${escapeHtml(client.nombre)}</option>`).join('');
    elements.inventoryClient.value = clients.some((client) => client.id === currentClient) ? currentClient : '';
    const activeClientId = elements.inventoryClient.value;
    const rows = state.inventory.filter((folio) => (!activeClientId || folio.item.cliente.id === activeClientId) && `${folio.numero_folio} ${folio.item.cliente?.temporada?.codigo || ''} ${folio.item.cliente?.codigo || ''} ${folio.item.cliente?.nombre || ''} ${folio.item.codigo} ${folio.item.nombre} ${folio.camara?.codigo || ''} ${folio.posicion?.etiqueta || ''}`.toLowerCase().includes(query));
    const selectedSummary = activeClientId ? state.inventorySummary.find((summary) => summary.cliente.id === activeClientId) : null;
    elements.inventorySummary.textContent = selectedSummary
        ? `${selectedSummary.folios} folios · ${selectedSummary.items} ítems · ${selectedSummary.posiciones} posiciones`
        : `${rows.length} folios · ${clients.length} clientes`;
    const canCorrect = state.identity?.puede_corregir_items_estibados_materiales === true;
    elements.inventoryBody.innerHTML = rows.map((folio) => `<tr><td><strong>${escapeHtml(folio.numero_folio)}</strong><small>${escapeHtml(folio.lote || 'Sin lote')}</small></td><td><strong>${escapeHtml(folio.item.cliente?.codigo || '—')} · ${escapeHtml(folio.item.cliente?.nombre || '—')}</strong><small>${escapeHtml(folio.item.cliente?.temporada?.codigo || '—')}</small></td><td><strong>${escapeHtml(folio.item.codigo)}</strong><small>${escapeHtml(folio.item.nombre)}</small></td><td>${quantity(folio.cantidad_actual)} ${escapeHtml(folio.unidad_medida)}</td><td>${quantity(folio.cantidad_reservada)}</td><td>${quantity(folio.cantidad_disponible)}</td><td><strong>${escapeHtml(folio.camara?.codigo || 'Sin cámara')}</strong><small>${escapeHtml(folio.posicion?.etiqueta || 'Sin posición')}</small></td><td>${canCorrect ? `<button data-correct-material="${folio.folio_id}" type="button">Corregir código</button>` : '—'}</td></tr>`).join('') || '<tr><td colspan="8">No existen folios coincidentes.</td></tr>';
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
function renderAll() { renderSeasons(); renderClients(); renderProviders(); renderItems(); renderDestinations(); renderDispatches(); renderInventory(); renderMetrics(); renderImportHistory(); }

async function loadAll() {
    const catalogAdmin = state.identity?.puede_administrar_catalogos_materiales === true;
    const [catalog, dispatches, inventory] = await Promise.all([
        catalogAdmin ? Promise.all([api('/api/administracion/materiales/temporadas'), api('/api/administracion/materiales/clientes'), api('/api/administracion/materiales/items'), api('/api/administracion/materiales/destinos'), api('/api/administracion/materiales/importaciones'), api('/api/administracion/materiales/proveedores')]) : api('/api/materiales/catalogo'),
        api('/api/materiales/despachos'), api('/api/materiales/inventario'),
    ]);
    if (catalogAdmin) { state.seasons = catalog[0].data; state.clients = catalog[1].data; state.items = catalog[2].data; state.destinations = catalog[3].data; state.imports = catalog[4].data; state.providers = catalog[5].data; } else { state.seasons = catalog.temporada ? [catalog.temporada] : []; state.clients = catalog.clientes; state.items = catalog.items; state.destinations = catalog.destinos; state.imports = []; state.providers = []; }
    if (!state.seasons.some((season) => season.id === state.selectedSeasonId)) state.selectedSeasonId = state.seasons.find((season) => season.activa)?.id || state.seasons[0]?.id || null;
    state.dispatches = dispatches.data; state.inventory = inventory.data; state.inventorySummary = inventory.resumen_clientes || [];
    state.inventorySyncedAt = new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    renderAll();
}

async function refreshOperationalData({ required = false } = {}) {
    if (!state.token || state.identity?.puede_consultar_despachos_materiales !== true) {
        if (required) throw new ApiError('No fue posible verificar el stock disponible con la sesión actual.', 403);
        return false;
    }

    const operation = state.operationalRefreshPromise || Promise.all([
        api('/api/materiales/despachos'),
        api('/api/materiales/inventario'),
    ]);
    const ownsOperation = state.operationalRefreshPromise === null;
    if (ownsOperation) state.operationalRefreshPromise = operation;

    try {
        const [dispatches, inventory] = await operation;
        state.dispatches = dispatches.data;
        state.inventory = inventory.data;
        state.inventorySummary = inventory.resumen_clientes || [];
        state.inventorySyncedAt = new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        renderDispatches(); renderInventory(); renderMetrics(); refreshDispatchLines();
        return true;
    } catch (error) {
        if (error.status !== 401) console.warn('No fue posible actualizar el stock de materiales.', error);
        if (required) {
            throw new ApiError(
                'No fue posible verificar el stock actual. Revisa la conexión e inténtalo nuevamente.',
                error.status || 0,
            );
        }
        return false;
    } finally {
        if (ownsOperation && state.operationalRefreshPromise === operation) state.operationalRefreshPromise = null;
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
function resetProviderForm() { elements.providerForm.reset(); elements.providerForm.elements.id.value = ''; elements.providerForm.elements.activo.checked = true; elements.providerCancel.classList.add('is-hidden'); elements.providerError.textContent = ''; renderProviders(); }
function resetDestinationForm() { elements.destinationForm.reset(); elements.destinationForm.elements.id.value = ''; elements.destinationForm.elements.activo.checked = true; elements.destinationCancel.classList.add('is-hidden'); elements.destinationError.textContent = ''; }
function resetImportPreview() { state.importPreview = null; elements.importError.textContent = ''; renderImportPreview(); }

elements.login.addEventListener('submit', async (event) => { event.preventDefault(); elements.loginError.textContent = ''; setBusy(true, 'Validando acceso…'); try { const payload = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(elements.login))) }); if (payload.usuario.puede_consultar_despachos_materiales !== true) throw new ApiError('Tu perfil no puede consultar materiales.', 403); persist(payload); showApp(); await loadAll(); } catch (error) { elements.loginError.textContent = error.message; } finally { setBusy(false); } });
elements.providerForm.elements.codigo.addEventListener('input', (event) => { event.target.value = event.target.value.toUpperCase().replace(/[^A-Z0-9._-]/g, ''); });
elements.providerForm.addEventListener('submit', async (event) => {
    event.preventDefault(); elements.providerError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.providerForm));
    const id = data.id; delete data.id;
    data.activo = elements.providerForm.elements.activo.checked;
    data.cliente_ids = [...elements.providerClientOptions.querySelectorAll('input:checked')].map((input) => input.value);
    setBusy(true, 'Guardando proveedor…');
    try {
        await api(id ? `/api/administracion/materiales/proveedores/${id}` : '/api/administracion/materiales/proveedores', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) });
        resetProviderForm(); await loadAll(); toast('Proveedor y clientes asociados actualizados.');
    } catch (error) { elements.providerError.textContent = error.message; } finally { setBusy(false); }
});
elements.itemForm.addEventListener('submit', async (event) => { event.preventDefault(); elements.itemError.textContent = ''; const data = Object.fromEntries(new FormData(elements.itemForm)); const id = data.id; delete data.id; data.activo = elements.itemForm.elements.activo.checked; setBusy(true, 'Guardando ítem…'); try { await api(id ? `/api/administracion/materiales/items/${id}` : '/api/administracion/materiales/items', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) }); resetItemForm(); await loadAll(); toast('Ítem guardado correctamente.'); } catch (error) { elements.itemError.textContent = error.message; } finally { setBusy(false); } });
elements.destinationForm.addEventListener('submit', async (event) => { event.preventDefault(); elements.destinationError.textContent = ''; const data = Object.fromEntries(new FormData(elements.destinationForm)); const id = data.id; delete data.id; data.activo = elements.destinationForm.elements.activo.checked; setBusy(true, 'Guardando destino…'); try { await api(id ? `/api/administracion/materiales/destinos/${id}` : '/api/administracion/materiales/destinos', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) }); resetDestinationForm(); await loadAll(); toast('Destino guardado correctamente.'); } catch (error) { elements.destinationError.textContent = error.message; } finally { setBusy(false); } });
elements.seasonSelector.addEventListener('change', () => { state.selectedSeasonId = elements.seasonSelector.value || null; resetItemForm(); renderAll(); });
elements.providerList.addEventListener('click', (event) => {
    const button = event.target.closest('[data-edit-provider]'); if (!button) return;
    const provider = state.providers.find((candidate) => candidate.id === button.dataset.editProvider); if (!provider) return;
    for (const field of ['id', 'codigo', 'nombre', 'codigo_externo']) elements.providerForm.elements[field].value = provider[field] || '';
    elements.providerForm.elements.activo.checked = provider.activo;
    const clientIds = new Set((provider.clientes || []).map((client) => client.id));
    elements.providerClientOptions.querySelectorAll('input').forEach((input) => { input.checked = clientIds.has(input.value); });
    elements.providerCancel.classList.remove('is-hidden');
});
elements.itemList.addEventListener('click', (event) => { const button = event.target.closest('[data-edit-item]'); if (!button) return; const item = state.items.find((candidate) => candidate.id === button.dataset.editItem); if (!item) return; for (const field of ['id', 'codigo', 'nombre', 'categoria', 'unidad_medida', 'codigo_externo']) elements.itemForm.elements[field].value = item[field] || ''; elements.itemForm.elements.cliente_material_id.value = item.cliente?.id || ''; elements.itemForm.elements.activo.checked = item.activo; elements.itemCancel.classList.remove('is-hidden'); });
elements.destinationList.addEventListener('click', (event) => { const button = event.target.closest('[data-edit-destination]'); if (!button) return; const destination = state.destinations.find((candidate) => candidate.id === button.dataset.editDestination); if (!destination) return; for (const field of ['id', 'nombre', 'centro_costo', 'descripcion', 'codigo_externo']) elements.destinationForm.elements[field].value = destination[field] || ''; elements.destinationForm.elements.activo.checked = destination.activo; elements.destinationCancel.classList.remove('is-hidden'); });
elements.providerCancel.addEventListener('click', resetProviderForm); elements.itemCancel.addEventListener('click', resetItemForm); elements.destinationCancel.addEventListener('click', resetDestinationForm);
elements.importOpen.addEventListener('click', () => elements.importDialog.showModal());
elements.importClose.addEventListener('click', () => elements.importDialog.close());
elements.importTemplate.addEventListener('click', () => {
    const content = '\uFEFFtemporada_codigo;cliente_codigo;codigo;nombre;categoria;unidad_medida;codigo_externo;activo\n2026-2027;AG-001;CAJ-5KG;Caja cartón 5 kg;Cajas;unidad;ERP-1054;si\n';
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
    try {
        await refreshOperationalData({ required: true });
    } catch (error) {
        elements.dispatchError.textContent = error.message;
        return;
    }
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
elements.inventorySearch.addEventListener('input', renderInventory);
elements.inventoryClient.addEventListener('change', renderInventory);
elements.inventoryBody.addEventListener('click', (event) => {
    const button = event.target.closest('[data-correct-material]');
    if (!button || state.identity?.puede_corregir_items_estibados_materiales !== true) return;
    const folio = state.inventory.find((candidate) => candidate.folio_id === button.dataset.correctMaterial);
    if (!folio) return;
    const alternatives = activeItems().filter((item) => item.id !== folio.item.id
        && item.cliente.id === folio.item.cliente.id
        && item.unidad_medida === folio.unidad_medida);
    if (!alternatives.length) { toast('No existen otros ítems activos compatibles para este cliente y unidad.', true); return; }
    elements.correctionForm.reset();
    elements.correctionForm.elements.folio_id.value = folio.folio_id;
    elements.correctionForm.elements.item_material_id.innerHTML = '<option value="">Selecciona el código correcto</option>' + alternatives.map((item) => `<option value="${item.id}">${escapeHtml(item.cliente.nombre)} · ${escapeHtml(item.codigo)} · ${escapeHtml(item.nombre)}</option>`).join('');
    elements.correctionContext.textContent = `${folio.numero_folio} · ${folio.item.cliente.nombre} · código actual ${folio.item.codigo}`;
    elements.correctionError.textContent = '';
    state.correctionOperationId = operationUuid();
    elements.correctionDialog.showModal();
});
function closeCorrectionDialog() { state.correctionOperationId = null; elements.correctionDialog.close(); }
elements.correctionClose.addEventListener('click', closeCorrectionDialog);
elements.correctionCancel.addEventListener('click', closeCorrectionDialog);
elements.correctionForm.addEventListener('submit', async (event) => {
    event.preventDefault(); elements.correctionError.textContent = '';
    const form = Object.fromEntries(new FormData(elements.correctionForm));
    setBusy(true, 'Corrigiendo ítem y registrando kardex…');
    try {
        await api(`/api/materiales/inventario/${form.folio_id}/corregir-item`, {
            method: 'POST',
            body: JSON.stringify({ operacion_id: state.correctionOperationId, item_material_id: form.item_material_id, motivo: form.motivo }),
        });
        closeCorrectionDialog(); await loadAll(); toast('Ítem corregido y auditado correctamente.');
    } catch (error) { elements.correctionError.textContent = error.message; } finally { setBusy(false); }
});
elements.reload.addEventListener('click', async () => { setBusy(true, 'Actualizando materiales…'); try { await loadAll(); toast('Información actualizada.'); } catch (error) { toast(error.message, true); } finally { setBusy(false); } });
elements.logout.addEventListener('click', async () => { try { await api('/api/acceso-oficina', { method: 'DELETE' }); } finally { clearSession(); } });

async function boot() { addDispatchLine(); if (!state.token || state.identity?.puede_consultar_despachos_materiales !== true) return; showApp(); setBusy(true, 'Cargando materiales…'); try { await loadAll(); } catch (error) { if (error.status !== 401) toast(error.message, true); } finally { setBusy(false); } }
window.setInterval(() => { if (document.visibilityState === 'visible') void refreshOperationalData(); }, 12000);
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') void refreshOperationalData(); });
void boot();
