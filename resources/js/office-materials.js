const byId = (id) => document.getElementById(id);
const elements = {
    access: byId('officeAccess'), app: byId('officeApp'), login: byId('officeLoginForm'),
    loginError: byId('officeLoginError'), userName: byId('officeUserName'), userRole: byId('officeUserRole'),
    initials: byId('officeInitials'), logout: byId('officeLogoutButton'), camerasNav: byId('officeCamerasNav'),
    loadsNav: byId('officeLoadsNav'), prefrioNav: byId('officePrefrioNav'), accessesNav: byId('officeAccessesNav'),
    reload: byId('reloadMaterialsButton'), admin: byId('materialsAdminCatalogs'), itemForm: byId('itemMaterialForm'),
    itemError: byId('itemMaterialError'), itemCancel: byId('cancelItemEdit'), itemList: byId('itemsMaterialList'),
    destinationForm: byId('destinationMaterialForm'), destinationError: byId('destinationMaterialError'),
    destinationCancel: byId('cancelDestinationEdit'), destinationList: byId('destinationsMaterialList'),
    dispatchForm: byId('dispatchMaterialForm'), dispatchError: byId('dispatchMaterialError'),
    dispatchDestination: byId('dispatchDestination'), dispatchLines: byId('dispatchMaterialLines'),
    addDispatchLine: byId('addDispatchLine'), dispatchList: byId('dispatchMaterialList'),
    inventorySearch: byId('materialsInventorySearch'), inventoryBody: byId('materialsInventoryBody'),
    itemCount: byId('materialsItemCount'), folioCount: byId('materialsFolioCount'),
    dispatchCount: byId('materialsDispatchCount'), destinationCount: byId('materialsDestinationCount'),
    itemsSummary: byId('itemsSummary'), destinationsSummary: byId('destinationsSummary'),
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
    items: [], destinations: [], dispatches: [], inventory: [], imports: [], importPreview: null, dispatchOperationId: null,
    cancellationOperations: new Map(),
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
    elements.loadsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_cargas !== true);
    elements.prefrioNav.classList.toggle('is-hidden', state.identity?.puede_consultar_prefrio !== true);
    elements.admin.classList.toggle('is-hidden', state.identity?.puede_administrar_catalogos_materiales !== true);
    elements.dispatchForm.classList.toggle(
        'is-hidden',
        state.identity?.puede_gestionar_despachos_materiales !== true,
    );
}

function activeItems() { return state.items.filter((item) => item.activo); }
function activeDestinations() { return state.destinations.filter((destination) => destination.activo); }
function renderMetrics() {
    elements.itemCount.textContent = String(activeItems().length); elements.folioCount.textContent = String(state.inventory.length);
    elements.dispatchCount.textContent = String(state.dispatches.filter((dispatch) => ['pendiente', 'parcial'].includes(dispatch.estado)).length);
    elements.destinationCount.textContent = String(activeDestinations().length);
}
function renderItems() {
    const canAdminister = state.identity?.puede_administrar_catalogos_materiales === true;
    elements.itemsSummary.textContent = `${state.items.length} registrados`;
    elements.itemList.innerHTML = state.items.map((item) => `<article class="material-row${item.activo ? '' : ' is-inactive'}"><div><strong>${escapeHtml(item.codigo)} · ${escapeHtml(item.nombre)}</strong><small>${escapeHtml(item.categoria || 'Sin categoría')} · ${escapeHtml(item.unidad_medida)} · ${item.folios_activos} folios activos</small></div>${canAdminister ? `<button data-edit-item="${item.id}" type="button">Editar</button>` : ''}</article>`).join('') || '<p class="empty-state">No existen ítems.</p>';
    const options = activeItems().map((item) => `<option value="${item.id}">${escapeHtml(item.codigo)} · ${escapeHtml(item.nombre)} (${escapeHtml(item.unidad_medida)})</option>`).join('');
    elements.dispatchLines.querySelectorAll('select[name="item_material_id"]').forEach((select) => { const current = select.value; select.innerHTML = options; if ([...select.options].some((option) => option.value === current)) select.value = current; });
}
function renderDestinations() {
    const canAdminister = state.identity?.puede_administrar_catalogos_materiales === true;
    elements.destinationsSummary.textContent = `${state.destinations.length} registrados`;
    elements.destinationList.innerHTML = state.destinations.map((destination) => `<article class="material-row${destination.activo ? '' : ' is-inactive'}"><div><strong>${escapeHtml(destination.nombre)}</strong><small>${escapeHtml(destination.centro_costo)}${destination.descripcion ? ` · ${escapeHtml(destination.descripcion)}` : ''}</small></div>${canAdminister ? `<button data-edit-destination="${destination.id}" type="button">Editar</button>` : ''}</article>`).join('') || '<p class="empty-state">No existen destinos.</p>';
    elements.dispatchDestination.innerHTML = '<option value="">Selecciona un destino</option>' + activeDestinations().map((destination) => `<option value="${destination.id}">${escapeHtml(destination.nombre)} · ${escapeHtml(destination.centro_costo)}</option>`).join('');
}
function renderDispatches() {
    elements.dispatchList.innerHTML = state.dispatches.map((dispatch) => {
        const detail = dispatch.items.map((item) => `${item.item.codigo}: ${quantity(item.cantidad_despachada)}/${quantity(item.cantidad_solicitada)} ${item.unidad_medida}`).join(' · ');
        const shortage = dispatch.items.some((item) => Number(item.cantidad_reservada) + Number(item.cantidad_despachada) < Number(item.cantidad_solicitada));
        const canCancel = state.identity?.puede_cancelar_despachos_materiales === true;
        return `<article class="dispatch-row"><div><strong>${escapeHtml(dispatch.codigo)} · ${escapeHtml(dispatch.destino.nombre)}</strong><small>${escapeHtml(dispatch.destino.centro_costo)} · ${escapeHtml(detail)}${shortage ? ' · Falta existencia por reservar' : ''}</small></div><div class="dispatch-row__state"><span>${escapeHtml(statusText(dispatch.estado))}</span>${canCancel && ['pendiente', 'parcial'].includes(dispatch.estado) ? `<button data-cancel-dispatch="${dispatch.id}" type="button">Cancelar</button>` : ''}</div></article>`;
    }).join('') || '<p class="empty-state">No existen despachos de materiales.</p>';
}
function renderInventory() {
    const query = elements.inventorySearch.value.trim().toLowerCase();
    const rows = state.inventory.filter((folio) => `${folio.numero_folio} ${folio.item.codigo} ${folio.item.nombre} ${folio.camara?.codigo || ''} ${folio.posicion?.etiqueta || ''}`.toLowerCase().includes(query));
    elements.inventoryBody.innerHTML = rows.map((folio) => `<tr><td><strong>${escapeHtml(folio.numero_folio)}</strong><small>${escapeHtml(folio.lote || 'Sin lote')}</small></td><td><strong>${escapeHtml(folio.item.codigo)}</strong><small>${escapeHtml(folio.item.nombre)}</small></td><td>${quantity(folio.cantidad_actual)} ${escapeHtml(folio.unidad_medida)}</td><td>${quantity(folio.cantidad_reservada)}</td><td>${quantity(folio.cantidad_disponible)}</td><td><strong>${escapeHtml(folio.camara?.codigo || 'Sin cámara')}</strong><small>${escapeHtml(folio.posicion?.etiqueta || 'Sin posición')}</small></td></tr>`).join('') || '<tr><td colspan="6">No existen folios coincidentes.</td></tr>';
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
    elements.importRows.innerHTML = (preview.filas || []).slice(0, 100).map((row) => `<tr><td>${Number(row.fila)}</td><td><strong>${escapeHtml(row.codigo)}</strong></td><td>${escapeHtml(row.nombre)}</td><td>${escapeHtml(row.unidad_medida)}</td><td><span class="material-import-action">${escapeHtml(statusText(row.accion))}</span></td></tr>`).join('') || '<tr><td colspan="5">No existen filas válidas para mostrar.</td></tr>';
    const confirmed = preview.estado === 'confirmada';
    elements.importConfirm.disabled = errors.length > 0 || confirmed;
    elements.importConfirmationHelp.textContent = confirmed
        ? `Importación confirmada: ${Number(summary.creados || 0)} creados, ${Number(summary.actualizados || 0)} actualizados y ${Number(summary.sin_cambios || 0)} sin cambios.`
        : errors.length > 0
            ? 'Corrige la planilla y vuelve a previsualizarla. No se aplicó ningún cambio.'
            : 'La confirmación actualizará solo el catálogo. Los ítems ausentes permanecerán sin cambios.';
}
function renderAll() { renderItems(); renderDestinations(); renderDispatches(); renderInventory(); renderMetrics(); renderImportHistory(); }

async function loadAll() {
    const catalogAdmin = state.identity?.puede_administrar_catalogos_materiales === true;
    const [catalog, dispatches, inventory] = await Promise.all([
        catalogAdmin ? Promise.all([api('/api/administracion/materiales/items'), api('/api/administracion/materiales/destinos'), api('/api/administracion/materiales/importaciones')]) : api('/api/materiales/catalogo'),
        api('/api/materiales/despachos'), api('/api/materiales/inventario'),
    ]);
    if (catalogAdmin) { state.items = catalog[0].data; state.destinations = catalog[1].data; state.imports = catalog[2].data; } else { state.items = catalog.items; state.destinations = catalog.destinos; state.imports = []; }
    state.dispatches = dispatches.data; state.inventory = inventory.data; renderAll();
}

function addDispatchLine(itemId = '', amount = '') {
    const row = document.createElement('div'); row.className = 'dispatch-line';
    row.innerHTML = `<select name="item_material_id" required>${activeItems().map((item) => `<option value="${item.id}"${item.id === itemId ? ' selected' : ''}>${escapeHtml(item.codigo)} · ${escapeHtml(item.nombre)}</option>`).join('')}</select><input name="cantidad" type="number" min="0.001" step="0.001" value="${escapeHtml(amount)}" placeholder="Cantidad" required><button data-remove-line type="button" aria-label="Quitar">×</button>`;
    elements.dispatchLines.append(row);
}
function resetItemForm() { elements.itemForm.reset(); elements.itemForm.elements.id.value = ''; elements.itemForm.elements.activo.checked = true; elements.itemCancel.classList.add('is-hidden'); elements.itemError.textContent = ''; }
function resetDestinationForm() { elements.destinationForm.reset(); elements.destinationForm.elements.id.value = ''; elements.destinationForm.elements.activo.checked = true; elements.destinationCancel.classList.add('is-hidden'); elements.destinationError.textContent = ''; }
function resetImportPreview() { state.importPreview = null; elements.importError.textContent = ''; renderImportPreview(); }

elements.login.addEventListener('submit', async (event) => { event.preventDefault(); elements.loginError.textContent = ''; setBusy(true, 'Validando acceso…'); try { const payload = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(elements.login))) }); if (payload.usuario.puede_consultar_despachos_materiales !== true) throw new ApiError('Tu perfil no puede consultar materiales.', 403); persist(payload); showApp(); await loadAll(); } catch (error) { elements.loginError.textContent = error.message; } finally { setBusy(false); } });
elements.itemForm.addEventListener('submit', async (event) => { event.preventDefault(); elements.itemError.textContent = ''; const data = Object.fromEntries(new FormData(elements.itemForm)); const id = data.id; delete data.id; data.activo = elements.itemForm.elements.activo.checked; setBusy(true, 'Guardando ítem…'); try { await api(id ? `/api/administracion/materiales/items/${id}` : '/api/administracion/materiales/items', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) }); resetItemForm(); await loadAll(); toast('Ítem guardado correctamente.'); } catch (error) { elements.itemError.textContent = error.message; } finally { setBusy(false); } });
elements.destinationForm.addEventListener('submit', async (event) => { event.preventDefault(); elements.destinationError.textContent = ''; const data = Object.fromEntries(new FormData(elements.destinationForm)); const id = data.id; delete data.id; data.activo = elements.destinationForm.elements.activo.checked; setBusy(true, 'Guardando destino…'); try { await api(id ? `/api/administracion/materiales/destinos/${id}` : '/api/administracion/materiales/destinos', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) }); resetDestinationForm(); await loadAll(); toast('Destino guardado correctamente.'); } catch (error) { elements.destinationError.textContent = error.message; } finally { setBusy(false); } });
elements.itemList.addEventListener('click', (event) => { const button = event.target.closest('[data-edit-item]'); if (!button) return; const item = state.items.find((candidate) => candidate.id === button.dataset.editItem); if (!item) return; for (const field of ['id', 'codigo', 'nombre', 'categoria', 'unidad_medida', 'codigo_externo']) elements.itemForm.elements[field].value = item[field] || ''; elements.itemForm.elements.activo.checked = item.activo; elements.itemCancel.classList.remove('is-hidden'); });
elements.destinationList.addEventListener('click', (event) => { const button = event.target.closest('[data-edit-destination]'); if (!button) return; const destination = state.destinations.find((candidate) => candidate.id === button.dataset.editDestination); if (!destination) return; for (const field of ['id', 'nombre', 'centro_costo', 'descripcion', 'codigo_externo']) elements.destinationForm.elements[field].value = destination[field] || ''; elements.destinationForm.elements.activo.checked = destination.activo; elements.destinationCancel.classList.remove('is-hidden'); });
elements.itemCancel.addEventListener('click', resetItemForm); elements.destinationCancel.addEventListener('click', resetDestinationForm);
elements.importOpen.addEventListener('click', () => elements.importDialog.showModal());
elements.importClose.addEventListener('click', () => elements.importDialog.close());
elements.importTemplate.addEventListener('click', () => {
    const content = '\uFEFFcodigo;nombre;categoria;unidad_medida;codigo_externo;activo\nCAJ-5KG;Caja cartón 5 kg;Cajas;unidad;ERP-1054;si\n';
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
elements.addDispatchLine.addEventListener('click', () => { state.dispatchOperationId = null; addDispatchLine(); }); elements.dispatchLines.addEventListener('click', (event) => { if (event.target.closest('[data-remove-line]') && elements.dispatchLines.children.length > 1) { state.dispatchOperationId = null; event.target.closest('.dispatch-line').remove(); } });
elements.dispatchForm.addEventListener('submit', async (event) => { event.preventDefault(); elements.dispatchError.textContent = ''; const form = new FormData(elements.dispatchForm); const items = [...elements.dispatchLines.querySelectorAll('.dispatch-line')].map((row) => ({ item_material_id: row.querySelector('[name="item_material_id"]').value, cantidad: row.querySelector('[name="cantidad"]').value })); state.dispatchOperationId ||= operationUuid(); const payload = { operacion_id: state.dispatchOperationId, destino_material_id: form.get('destino_material_id'), observacion: form.get('observacion'), items }; setBusy(true, 'Creando despacho y reservando existencia…'); try { const response = await api('/api/materiales/despachos', { method: 'POST', body: JSON.stringify(payload) }); state.dispatchOperationId = null; elements.dispatchForm.reset(); elements.dispatchLines.innerHTML = ''; addDispatchLine(); await loadAll(); toast(`${response.data.codigo} fue creado correctamente.`); } catch (error) { elements.dispatchError.textContent = error.message; } finally { setBusy(false); } });
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
void boot();
