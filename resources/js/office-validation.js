const byId = (id) => document.getElementById(id);
const elements = {
    access: byId('officeAccess'), app: byId('officeApp'), login: byId('officeLoginForm'), loginError: byId('officeLoginError'),
    userName: byId('officeUserName'), userRole: byId('officeUserRole'), initials: byId('officeInitials'), logout: byId('officeLogoutButton'),
    camerasNav: byId('officeCamerasNav'), loadsNav: byId('officeLoadsNav'), materialsNav: byId('officeMaterialsNav'), prefrioNav: byId('officePrefrioNav'), accessesNav: byId('officeAccessesNav'),
    reload: byId('reloadValidationButton'), seasonSelector: byId('seasonSelector'), admin: byId('validationAdmin'), filters: byId('validationFilters'), history: byId('validationHistoryBody'),
    catalogVersion: byId('catalogVersion'), articleCount: byId('activeArticleCount'), originCount: byId('activeOriginCount'), combinationCount: byId('activeCombinationCount'), observedCount: byId('observedCount'),
    seasonForm: byId('seasonForm'), seasonError: byId('seasonError'), seasonCancel: byId('cancelSeasonEdit'), seasonList: byId('seasonList'), seasonStatus: byId('seasonStatus'),
    articleForm: byId('articleForm'), articleError: byId('articleError'), articleCancel: byId('cancelArticleEdit'), articleList: byId('articleList'), articleSummary: byId('articleSummary'),
    originForm: byId('originForm'), originError: byId('originError'), originCancel: byId('cancelOriginEdit'), originList: byId('originList'), originSummary: byId('originSummary'),
    combinationForm: byId('combinationForm'), combinationError: byId('combinationError'), combinationCancel: byId('cancelCombinationEdit'), combinationList: byId('combinationList'), combinationSummary: byId('combinationSummary'),
    importForm: byId('importForm'), importError: byId('importError'), importPreview: byId('importPreview'), importList: byId('importList'),
    loading: byId('officeLoading'), loadingText: byId('officeLoadingText'), toasts: byId('officeToasts'),
};
const keys = { token: 'estiba_wms_office_token', identity: 'estiba_wms_office_identity' };
const state = {
    token: localStorage.getItem(keys.token), identity: readJson(keys.identity), seasons: [], season: null,
    articles: [], origins: [], combinations: [], imports: [], history: [], preview: null,
};

class ApiError extends Error {
    constructor(message, status, data = {}) { super(message); this.name = 'ApiError'; this.status = status; this.data = data; }
}
function readJson(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; } }
function escapeHtml(value) { return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }
function statusText(value) { return String(value || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()); }
function errorMessage(data, fallback) { return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback; }
function formatDate(value, fallback = 'Sin fecha') { if (!value) return fallback; const date = new Date(value); return Number.isNaN(date.getTime()) ? fallback : new Intl.DateTimeFormat('es-CL', { dateStyle: 'short', timeStyle: 'short' }).format(date); }
function dateInput(value) { return value ? String(value).slice(0, 10) : ''; }
function setBusy(active, message = 'Procesando…') { elements.loadingText.textContent = message; elements.loading.classList.toggle('is-hidden', !active); elements.loading.setAttribute('aria-hidden', String(!active)); }
function toast(message, error = false) { const node = document.createElement('div'); node.className = `toast${error ? ' toast--error' : ''}`; node.textContent = message; elements.toasts.append(node); window.setTimeout(() => node.remove(), 4500); }
function persist(payload) { state.token = payload.token; state.identity = payload.usuario; localStorage.setItem(keys.token, payload.token); localStorage.setItem(keys.identity, JSON.stringify(payload.usuario)); }
function clearSession() { state.token = null; state.identity = null; localStorage.removeItem(keys.token); localStorage.removeItem(keys.identity); elements.app.classList.add('is-hidden'); elements.access.classList.remove('is-hidden'); }
function operationUuid() { if (typeof crypto.randomUUID === 'function') return crypto.randomUUID(); const bytes = crypto.getRandomValues(new Uint8Array(16)); bytes[6] = (bytes[6] & 0x0f) | 0x40; bytes[8] = (bytes[8] & 0x3f) | 0x80; const hex = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join(''); return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`; }

async function api(path, options = {}) {
    const headers = new Headers(options.headers || {}); headers.set('Accept', 'application/json');
    if (state.token) headers.set('Authorization', `Bearer ${state.token}`);
    if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
    let response;
    try { response = await fetch(path, { ...options, headers }); } catch { throw new ApiError('No fue posible conectar con Laravel.', 0); }
    const data = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) { if (response.status === 401) clearSession(); throw new ApiError(errorMessage(data, 'No fue posible completar la operación.'), response.status, data); }
    return data;
}

function showApp() {
    elements.access.classList.add('is-hidden'); elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Usuario'; elements.userName.textContent = name; elements.userRole.textContent = statusText(state.identity?.rol);
    elements.initials.textContent = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    elements.accessesNav.classList.toggle('is-hidden', state.identity?.puede_administrar_accesos !== true);
    elements.camerasNav.classList.toggle('is-hidden', state.identity?.ambito_camaras === 'ninguno');
    elements.loadsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_cargas !== true);
    elements.materialsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_despachos_materiales !== true);
    elements.prefrioNav.classList.toggle('is-hidden', state.identity?.puede_consultar_prefrio !== true);
    elements.admin.classList.toggle('is-hidden', state.identity?.puede_administrar_catalogos_validacion !== true);
}

async function loadHistory() {
    const params = new URLSearchParams();
    const values = Object.fromEntries(new FormData(elements.filters));
    for (const [key, value] of Object.entries(values)) if (String(value).trim()) params.set(key, String(value).trim());
    params.set('per_page', '25');
    const response = await api(`/api/validacion/pallets?${params}`);
    state.history = response.data || [];
    renderHistory();
}

async function loadAdministration(seasonId = null) {
    if (state.identity?.puede_administrar_catalogos_validacion !== true) return;
    const suffix = seasonId ? `?temporada_id=${encodeURIComponent(seasonId)}` : '';
    const response = await api(`/api/administracion/validacion${suffix}`);
    state.seasons = response.temporadas || []; state.season = response.temporada || null; state.articles = response.articulos || [];
    state.origins = response.origenes || []; state.combinations = response.combinaciones || []; state.imports = response.importaciones || [];
    renderAdministration();
}

async function loadAll(seasonId = null) {
    await Promise.all([loadHistory(), loadAdministration(seasonId)]);
}

function renderMetrics() {
    elements.catalogVersion.textContent = state.season?.version_catalogo ?? '—';
    elements.articleCount.textContent = String(state.articles.filter((item) => item.activo).length);
    elements.originCount.textContent = String(state.origins.filter((item) => item.activo).length);
    elements.combinationCount.textContent = String(state.combinations.filter((item) => item.activo).length);
    elements.observedCount.textContent = String(state.history.filter((item) => item.resultado === 'observado').length);
}

function renderHistory() {
    elements.history.innerHTML = state.history.map((item) => {
        const article = item.catalogo?.articulo || {}; const origin = item.catalogo?.origen || {};
        const resultClass = item.estado === 'conflicto' ? 'conflicto' : item.resultado;
        return `<tr><td><strong>${escapeHtml(item.numero_folio)}</strong><small>Intento ${item.numero_intento} · ${escapeHtml(statusText(item.tipo_bulto))}</small></td><td><strong>${escapeHtml(article.especie || 'Sin artículo')} · ${escapeHtml(article.variedad || '')}</strong><small>${escapeHtml(article.calibre || '')} · ${escapeHtml(article.envase || '')}</small></td><td><strong>${escapeHtml(origin.cliente || 'Sin origen')}</strong><small>${escapeHtml(origin.marca || '')} · CSG ${escapeHtml(origin.csg || '—')}</small></td><td><span class="validation-result validation-result--${escapeHtml(resultClass)}">${escapeHtml(item.estado === 'conflicto' ? 'Conflicto' : item.resultado)}</span>${item.motivo ? `<small>${escapeHtml(statusText(item.motivo))}</small>` : ''}</td><td><strong>${escapeHtml(item.usuario?.nombre || '—')}</strong><small>${escapeHtml(item.dispositivo?.codigo || '')}</small></td><td>${escapeHtml(formatDate(item.recibido_servidor_at))}</td></tr>`;
    }).join('') || '<tr><td class="empty-validation" colspan="6">No existen validaciones coincidentes.</td></tr>';
    renderMetrics();
}

function renderAdministration() {
    elements.seasonSelector.innerHTML = state.seasons.map((season) => `<option value="${season.id}"${season.id === state.season?.id ? ' selected' : ''}>${escapeHtml(season.codigo)} · ${escapeHtml(season.nombre)}${season.activa ? ' (activa)' : ''}</option>`).join('') || '<option value="">Sin temporadas</option>';
    elements.seasonStatus.textContent = state.season ? `${state.season.activa ? 'Activa' : 'Inactiva'} · versión ${state.season.version_catalogo}` : 'Sin temporada';
    elements.seasonList.innerHTML = state.seasons.map((season) => `<article class="validation-row${season.activa ? '' : ' is-inactive'}"><div><strong>${escapeHtml(season.codigo)} · ${escapeHtml(season.nombre)}</strong><small>${escapeHtml(dateInput(season.fecha_inicio) || 'Sin inicio')} → ${escapeHtml(dateInput(season.fecha_fin) || 'Sin término')} · versión ${season.version_catalogo}</small></div><div class="validation-row__actions"><button data-edit-season="${season.id}" type="button">Editar</button>${season.activa ? '' : `<button data-activate-season="${season.id}" type="button">Activar</button>`}</div></article>`).join('') || '<p class="empty-validation">No existen temporadas.</p>';
    elements.articleSummary.textContent = `${state.articles.length} registrados`;
    elements.articleList.innerHTML = state.articles.map((item) => `<article class="validation-row${item.activo ? '' : ' is-inactive'}"><div><strong>${escapeHtml(item.especie)} · ${escapeHtml(item.variedad)} · ${escapeHtml(item.calibre)}</strong><small>${escapeHtml(item.envase)} · ${item.combinaciones_count || 0} combinaciones${item.codigo_externo ? ` · ${escapeHtml(item.codigo_externo)}` : ''}</small></div><button data-edit-article="${item.id}" type="button">Editar</button></article>`).join('') || '<p class="empty-validation">No existen artículos.</p>';
    elements.originSummary.textContent = `${state.origins.length} registrados`;
    elements.originList.innerHTML = state.origins.map((item) => `<article class="validation-row${item.activo ? '' : ' is-inactive'}"><div><strong>${escapeHtml(item.cliente)} · ${escapeHtml(item.marca)}</strong><small>CSG ${escapeHtml(item.csg)}${item.predio ? ` · ${escapeHtml(item.predio)}` : ''} · ${item.combinaciones_count || 0} combinaciones</small></div><button data-edit-origin="${item.id}" type="button">Editar</button></article>`).join('') || '<p class="empty-validation">No existen orígenes.</p>';
    elements.combinationSummary.textContent = `${state.combinations.length} registradas`;
    elements.combinationList.innerHTML = state.combinations.map((item) => `<article class="validation-row${item.activo ? '' : ' is-inactive'}"><div><strong>${escapeHtml(item.articulo?.especie)} · ${escapeHtml(item.articulo?.variedad)} · ${escapeHtml(item.articulo?.calibre)}</strong><small>${escapeHtml(item.origen?.cliente)} · ${escapeHtml(item.origen?.marca)} · CSG ${escapeHtml(item.origen?.csg)}${item.codigo_externo ? ` · ${escapeHtml(item.codigo_externo)}` : ''}</small></div><button data-edit-combination="${item.id}" type="button">Editar</button></article>`).join('') || '<p class="empty-validation">No existen combinaciones habilitadas.</p>';
    const articleOptions = state.articles.map((item) => `<option value="${item.id}">${escapeHtml(item.especie)} · ${escapeHtml(item.variedad)} · ${escapeHtml(item.calibre)} · ${escapeHtml(item.envase)}</option>`).join('');
    const originOptions = state.origins.map((item) => `<option value="${item.id}">${escapeHtml(item.cliente)} · ${escapeHtml(item.marca)} · CSG ${escapeHtml(item.csg)}</option>`).join('');
    const articleCurrent = elements.combinationForm.elements.articulo_validacion_id.value; const originCurrent = elements.combinationForm.elements.origen_validacion_id.value;
    elements.combinationForm.elements.articulo_validacion_id.innerHTML = `<option value="">Selecciona un artículo</option>${articleOptions}`;
    elements.combinationForm.elements.origen_validacion_id.innerHTML = `<option value="">Selecciona un origen</option>${originOptions}`;
    if ([...elements.combinationForm.elements.articulo_validacion_id.options].some((option) => option.value === articleCurrent)) elements.combinationForm.elements.articulo_validacion_id.value = articleCurrent;
    if ([...elements.combinationForm.elements.origen_validacion_id.options].some((option) => option.value === originCurrent)) elements.combinationForm.elements.origen_validacion_id.value = originCurrent;
    elements.importList.innerHTML = state.imports.map((item) => `<article class="validation-row"><div><strong>${escapeHtml(item.nombre_archivo)}</strong><small>${escapeHtml(statusText(item.estado))} · ${formatDate(item.created_at)} · ${item.resumen?.filas_validas || 0} filas válidas</small></div>${item.estado === 'borrador' ? `<button data-confirm-import="${item.id}" type="button">Confirmar</button>` : ''}</article>`).join('') || '<p class="empty-validation">Sin importaciones recientes.</p>';
    renderMetrics();
}

function resetForm(form, cancelButton, error) { form.reset(); if (form.elements.id) form.elements.id.value = ''; if (form.elements.activo) form.elements.activo.checked = true; cancelButton?.classList.add('is-hidden'); error.textContent = ''; }
function fillForm(form, item, fields) { for (const field of fields) if (form.elements[field]) form.elements[field].value = item[field] ?? ''; if (form.elements.activo) form.elements.activo.checked = Boolean(item.activo); }

async function saveJson(form, errorNode, basePath, label) {
    errorNode.textContent = ''; if (!state.season && form !== elements.seasonForm) { errorNode.textContent = 'Primero crea o selecciona una temporada.'; return; }
    const data = Object.fromEntries(new FormData(form)); const id = data.id; delete data.id;
    if (form !== elements.seasonForm) data.temporada_id = state.season.id;
    if (form.elements.activo) data.activo = form.elements.activo.checked;
    setBusy(true, `Guardando ${label}…`);
    try {
        await api(id ? `${basePath}/${id}` : basePath, { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) });
        resetForm(form, form.querySelector('.secondary-button'), errorNode); await loadAdministration(state.season?.id); toast(`${statusText(label)} guardado correctamente.`);
    } catch (error) { errorNode.textContent = error.message; } finally { setBusy(false); }
}

function renderImportPreview(item) {
    state.preview = item; const summary = item.resumen || {}; const errors = item.errores || [];
    elements.importPreview.classList.remove('is-hidden');
    elements.importPreview.innerHTML = `<strong>${escapeHtml(item.nombre_archivo)}</strong><div class="import-preview__metrics"><span><strong>${summary.filas_validas || 0}</strong><br>filas válidas</span><span><strong>${summary.combinaciones_detectadas || 0}</strong><br>combinaciones</span><span><strong>${summary.filas_con_error || 0}</strong><br>errores</span></div>${errors.length ? `<div class="import-errors">${errors.map((error) => `<p>Fila ${error.fila}: ${escapeHtml(error.mensaje)}</p>`).join('')}</div>` : '<p>La previsualización no detectó errores bloqueantes.</p>'}${item.estado === 'borrador' ? `<button class="primary-button" id="confirmPreviewImport" type="button">Confirmar e importar</button>` : ''}`;
    byId('confirmPreviewImport')?.addEventListener('click', () => void confirmImport(item.id));
}

async function confirmImport(id) {
    setBusy(true, 'Confirmando importación…'); elements.importError.textContent = '';
    try { await api(`/api/administracion/validacion/importaciones/${id}/confirmar`, { method: 'POST' }); state.preview = null; elements.importPreview.classList.add('is-hidden'); await loadAdministration(state.season?.id); toast('Catálogo importado y versión actualizada.'); }
    catch (error) { elements.importError.textContent = error.message; }
    finally { setBusy(false); }
}

elements.login.addEventListener('submit', async (event) => { event.preventDefault(); elements.loginError.textContent = ''; setBusy(true, 'Validando acceso…'); try { const payload = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(elements.login))) }); if (payload.usuario.puede_consultar_validaciones_pallet !== true) throw new ApiError('Tu perfil no puede consultar validaciones.', 403); persist(payload); showApp(); await loadAll(); } catch (error) { elements.loginError.textContent = error.message; } finally { setBusy(false); } });
elements.logout.addEventListener('click', async () => { try { await api('/api/acceso-oficina', { method: 'DELETE' }); } catch {} clearSession(); });
elements.reload.addEventListener('click', () => { setBusy(true, 'Actualizando validación…'); void loadAll(state.season?.id).catch((error) => toast(error.message, true)).finally(() => setBusy(false)); });
elements.filters.addEventListener('submit', (event) => { event.preventDefault(); setBusy(true, 'Consultando historial…'); void loadHistory().catch((error) => toast(error.message, true)).finally(() => setBusy(false)); });
elements.seasonSelector.addEventListener('change', () => { setBusy(true, 'Cambiando temporada…'); void loadAdministration(elements.seasonSelector.value || null).catch((error) => toast(error.message, true)).finally(() => setBusy(false)); });

elements.seasonForm.addEventListener('submit', (event) => { event.preventDefault(); void saveJson(elements.seasonForm, elements.seasonError, '/api/administracion/validacion/temporadas', 'temporada'); });
elements.articleForm.addEventListener('submit', (event) => { event.preventDefault(); void saveJson(elements.articleForm, elements.articleError, '/api/administracion/validacion/articulos', 'artículo'); });
elements.originForm.addEventListener('submit', (event) => { event.preventDefault(); void saveJson(elements.originForm, elements.originError, '/api/administracion/validacion/origenes', 'origen'); });
elements.combinationForm.addEventListener('submit', (event) => { event.preventDefault(); void saveJson(elements.combinationForm, elements.combinationError, '/api/administracion/validacion/combinaciones', 'combinación'); });
elements.seasonCancel.addEventListener('click', () => resetForm(elements.seasonForm, elements.seasonCancel, elements.seasonError));
elements.articleCancel.addEventListener('click', () => resetForm(elements.articleForm, elements.articleCancel, elements.articleError));
elements.originCancel.addEventListener('click', () => resetForm(elements.originForm, elements.originCancel, elements.originError));
elements.combinationCancel.addEventListener('click', () => resetForm(elements.combinationForm, elements.combinationCancel, elements.combinationError));

elements.seasonList.addEventListener('click', (event) => { const edit = event.target.closest('[data-edit-season]'); const activate = event.target.closest('[data-activate-season]'); if (edit) { const item = state.seasons.find((candidate) => candidate.id === edit.dataset.editSeason); if (!item) return; fillForm(elements.seasonForm, item, ['id', 'codigo', 'nombre']); elements.seasonForm.elements.fecha_inicio.value = dateInput(item.fecha_inicio); elements.seasonForm.elements.fecha_fin.value = dateInput(item.fecha_fin); elements.seasonForm.elements.activa.checked = item.activa; elements.seasonCancel.classList.remove('is-hidden'); } if (activate) { setBusy(true, 'Activando temporada…'); void api(`/api/administracion/validacion/temporadas/${activate.dataset.activateSeason}/activar`, { method: 'POST' }).then(() => loadAdministration(activate.dataset.activateSeason)).then(() => toast('Temporada activada.')).catch((error) => toast(error.message, true)).finally(() => setBusy(false)); } });
elements.articleList.addEventListener('click', (event) => { const button = event.target.closest('[data-edit-article]'); if (!button) return; const item = state.articles.find((candidate) => candidate.id === button.dataset.editArticle); if (!item) return; fillForm(elements.articleForm, item, ['id', 'especie', 'variedad', 'calibre', 'envase', 'codigo_externo']); elements.articleCancel.classList.remove('is-hidden'); });
elements.originList.addEventListener('click', (event) => { const button = event.target.closest('[data-edit-origin]'); if (!button) return; const item = state.origins.find((candidate) => candidate.id === button.dataset.editOrigin); if (!item) return; fillForm(elements.originForm, item, ['id', 'cliente', 'marca', 'csg', 'predio', 'codigo_externo']); elements.originCancel.classList.remove('is-hidden'); });
elements.combinationList.addEventListener('click', (event) => { const button = event.target.closest('[data-edit-combination]'); if (!button) return; const item = state.combinations.find((candidate) => candidate.id === button.dataset.editCombination); if (!item) return; fillForm(elements.combinationForm, { id: item.id, articulo_validacion_id: item.articulo_validacion_id, origen_validacion_id: item.origen_validacion_id, codigo_externo: item.codigo_externo, activo: item.activo }, ['id', 'articulo_validacion_id', 'origen_validacion_id', 'codigo_externo']); elements.combinationCancel.classList.remove('is-hidden'); });
elements.importList.addEventListener('click', (event) => { const button = event.target.closest('[data-confirm-import]'); if (button) void confirmImport(button.dataset.confirmImport); });
elements.importForm.addEventListener('submit', async (event) => { event.preventDefault(); elements.importError.textContent = ''; if (!state.season) { elements.importError.textContent = 'Primero crea o selecciona una temporada.'; return; } const data = new FormData(elements.importForm); data.set('temporada_id', state.season.id); setBusy(true, 'Leyendo y validando planilla…'); try { const response = await api('/api/administracion/validacion/importaciones/previsualizar', { method: 'POST', body: data }); renderImportPreview(response.data); await loadAdministration(state.season.id); } catch (error) { elements.importError.textContent = error.message; } finally { setBusy(false); } });

if (state.token && state.identity?.puede_consultar_validaciones_pallet === true) { showApp(); setBusy(true, 'Cargando validación…'); void loadAll().catch(() => clearSession()).finally(() => setBusy(false)); }
