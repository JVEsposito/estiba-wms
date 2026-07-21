const byId = (id) => document.getElementById(id);
const elements = {
    access: byId('officeAccess'), app: byId('officeApp'), login: byId('officeLoginForm'), loginError: byId('officeLoginError'),
    userName: byId('officeUserName'), userRole: byId('officeUserRole'), initials: byId('officeInitials'), logout: byId('officeLogoutButton'),
    managementNav: byId('officeManagementNav'), containerAccountsNav: byId('officeContainerAccountsNav'), camerasNav: byId('officeCamerasNav'), loadsNav: byId('officeLoadsNav'), materialsNav: byId('officeMaterialsNav'), validationNav: byId('officeValidationNav'), prefrioNav: byId('officePrefrioNav'), accessesNav: byId('officeAccessesNav'),
    reload: byId('reloadButton'), newReception: byId('newReceptionButton'), filters: byId('receptionFilters'), tableBody: byId('receptionTableBody'),
    entryCount: byId('entryCount'), exitCount: byId('exitCount'), closedCount: byId('closedCount'), netWeight: byId('netWeight'),
    paginationSummary: byId('paginationSummary'), previousPage: byId('previousPageButton'), nextPage: byId('nextPageButton'),
    detail: byId('receptionDetail'), detailTitle: byId('detailTitle'), detailSubtitle: byId('detailSubtitle'), detailFacts: byId('detailFacts'), detailTimeline: byId('detailTimeline'), weightBalance: byId('weightBalance'),
    editReception: byId('editReceptionButton'), confirmEntry: byId('confirmEntryButton'), closeReception: byId('closeReceptionButton'), downloadReceipt: byId('downloadReceiptButton'), closeDetail: byId('closeDetailButton'),
    receptionDialog: byId('receptionDialog'), receptionForm: byId('receptionForm'), receptionDialogTitle: byId('receptionDialogTitle'), receptionFormError: byId('receptionFormError'),
    serviceField: byId('serviceField'), containerConceptField: byId('containerConceptField'),
    tareDialog: byId('tareDialog'), tareForm: byId('tareForm'), tareDescription: byId('tareDescription'), tareFormError: byId('tareFormError'), netWeightPreview: byId('netWeightPreview'),
    loading: byId('officeLoading'), loadingText: byId('officeLoadingText'), toasts: byId('officeToasts'),
};

const keys = { token: 'estiba_wms_office_token', identity: 'estiba_wms_office_identity' };
const state = {
    token: localStorage.getItem(keys.token),
    identity: readJson(keys.identity),
    catalogs: { temporadas: [], clientes: [], tipos_servicio: [], tipos_envase: [], tipos_recepcion: [], conceptos_envases: [] },
    receptions: [],
    selected: null,
    page: 1,
    meta: null,
    timer: null,
};

class ApiError extends Error {
    constructor(message, status, data = {}) { super(message); this.name = 'ApiError'; this.status = status; this.data = data; }
}

function readJson(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; } }
function escapeHtml(value) { return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }
function errorMessage(data, fallback) { return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback; }
function operationUuid() {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16)); bytes[6] = (bytes[6] & 0x0f) | 0x40; bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
function formatDate(value, fallback = 'Pendiente') {
    if (!value) return fallback;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? fallback : new Intl.DateTimeFormat('es-CL', { dateStyle: 'short', timeStyle: 'short' }).format(date);
}
function formatWeight(value, fallback = '—') {
    if (value === null || value === undefined || value === '') return fallback;
    return `${new Intl.NumberFormat('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value))} kg`;
}
function label(value) {
    const labels = {
        en_bascula_ingreso: 'En báscula ingreso', en_bascula_salida: 'Pendiente de destare', cerrado: 'Cerrado',
        ingreso_registrado: 'Pesaje de ingreso registrado', ingreso_actualizado: 'Antecedentes de ingreso actualizados', ingreso_confirmado: 'Ingreso confirmado', recepcion_cerrada: 'Destare y recepción cerrados',
        almacenaje: 'Almacenaje', proceso: 'Proceso', prefrio: 'Pre-frío', bins: 'Bins', totes: 'Totes', esponjas: 'Esponjas', fruta_con_envases: 'Fruta con envases', solo_envases: 'Solo envases', compra: 'Compra', arriendo: 'Arriendo', pendiente: 'Pendiente', en_curso: 'En curso', validada: 'Validada',
    };
    return labels[value] || String(value || '').replaceAll('_', ' ').replace(/^./, (character) => character.toUpperCase());
}
function stateBadge(status) {
    const style = status === 'cerrado' ? 'closed' : status === 'en_bascula_salida' ? 'exit' : 'entry';
    return `<span class="state-badge state-badge--${style}">${escapeHtml(label(status))}</span>`;
}
function setBusy(active, message = 'Procesando…') {
    elements.loadingText.textContent = message;
    elements.loading.classList.toggle('is-hidden', !active);
    elements.loading.setAttribute('aria-hidden', String(!active));
}
function toast(message, error = false) {
    const node = document.createElement('div'); node.className = `toast${error ? ' toast--error' : ''}`; node.textContent = message; elements.toasts.append(node); window.setTimeout(() => node.remove(), 4500);
}
function persist(payload) {
    state.token = payload.token; state.identity = payload.usuario;
    localStorage.setItem(keys.token, payload.token); localStorage.setItem(keys.identity, JSON.stringify(payload.usuario));
}
function clearSession() {
    state.token = null; state.identity = null; state.receptions = []; state.selected = null;
    localStorage.removeItem(keys.token); localStorage.removeItem(keys.identity); window.clearInterval(state.timer);
    elements.app.classList.add('is-hidden'); elements.access.classList.remove('is-hidden');
}

async function api(path, options = {}) {
    const headers = new Headers(options.headers || {}); headers.set('Accept', 'application/json');
    if (state.token) headers.set('Authorization', `Bearer ${state.token}`);
    if (options.body) headers.set('Content-Type', 'application/json');
    let response;
    try { response = await fetch(path, { ...options, headers }); } catch { throw new ApiError('No fue posible conectar con Laravel.', 0); }
    const data = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401 && path !== '/api/acceso-oficina') clearSession();
        throw new ApiError(errorMessage(data, 'No fue posible completar la operación.'), response.status, data);
    }
    return data;
}

function showApp() {
    if (state.identity?.puede_consultar_romana !== true) return false;
    elements.access.classList.add('is-hidden'); elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Usuario de romana';
    elements.userName.textContent = name; elements.userRole.textContent = label(state.identity?.rol || 'consulta');
    elements.initials.textContent = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    elements.newReception.classList.toggle('is-hidden', state.identity?.puede_operar_romana !== true);
    elements.containerAccountsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_cuenta_envases !== true);
    elements.managementNav.classList.toggle('is-hidden', state.identity?.puede_consultar_panel_gerencial !== true);
    elements.camerasNav.classList.toggle('is-hidden', state.identity?.ambito_camaras === 'ninguno');
    elements.loadsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_cargas !== true);
    elements.materialsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_despachos_materiales !== true);
    elements.validationNav.classList.toggle('is-hidden', state.identity?.puede_consultar_validaciones_pallet !== true);
    elements.prefrioNav.classList.toggle('is-hidden', state.identity?.puede_consultar_prefrio !== true);
    elements.accessesNav.classList.toggle('is-hidden', state.identity?.puede_administrar_accesos !== true);
    return true;
}

function fillCatalogs() {
    const form = elements.receptionForm.elements;
    const activeSeasons = state.catalogs.temporadas.filter((season) => season.activa);
    form.temporada_id.innerHTML = '<option value="">Seleccionar temporada activa</option>' + activeSeasons.map((season) => `<option value="${escapeHtml(season.id)}">${escapeHtml(season.nombre)} · ${escapeHtml(season.codigo)}</option>`).join('');
    elements.filters.elements.temporada_id.innerHTML = '<option value="">Todas las temporadas</option>' + state.catalogs.temporadas.map((season) => `<option value="${escapeHtml(season.id)}">${escapeHtml(season.codigo)}</option>`).join('');
    form.cliente_id.innerHTML = '<option value="">Seleccionar cliente activo</option>' + state.catalogs.clientes.map((client) => `<option value="${escapeHtml(client.id)}">${escapeHtml(client.nombre)}${client.codigo ? ` · ${escapeHtml(client.codigo)}` : ''}</option>`).join('');
    form.tipo_servicio.innerHTML = state.catalogs.tipos_servicio.map((type) => `<option value="${escapeHtml(type.codigo)}">${escapeHtml(type.nombre)}</option>`).join('');
    form.tipo_recepcion.innerHTML = state.catalogs.tipos_recepcion.map((type) => `<option value="${escapeHtml(type.codigo)}">${escapeHtml(type.nombre)}</option>`).join('');
    form.concepto_envases.innerHTML = state.catalogs.conceptos_envases.map((type) => `<option value="${escapeHtml(type.codigo)}">${escapeHtml(type.nombre)}</option>`).join('');
}

function filterQuery() {
    const values = Object.fromEntries(new FormData(elements.filters));
    const query = new URLSearchParams({ por_pagina: '30', pagina: String(state.page) });
    Object.entries(values).forEach(([key, value]) => { if (value) query.set(key, value); });
    query.set('page', String(state.page));
    query.delete('pagina');
    return query.toString();
}

function renderList(payload) {
    state.receptions = payload.data; state.meta = payload.meta;
    elements.entryCount.textContent = String(payload.resumen.en_bascula_ingreso);
    elements.exitCount.textContent = String(payload.resumen.en_bascula_salida);
    elements.closedCount.textContent = String(payload.resumen.cerradas);
    elements.netWeight.textContent = formatWeight(payload.resumen.peso_neto);
    elements.paginationSummary.textContent = `${payload.meta.total} ${payload.meta.total === 1 ? 'recepción' : 'recepciones'} · página ${payload.meta.pagina_actual} de ${payload.meta.ultima_pagina}`;
    elements.previousPage.disabled = payload.meta.pagina_actual <= 1;
    elements.nextPage.disabled = payload.meta.pagina_actual >= payload.meta.ultima_pagina;

    if (!state.receptions.length) {
        elements.tableBody.innerHTML = '<tr class="weighbridge-empty"><td colspan="6">No existen recepciones para los filtros seleccionados.</td></tr>';
        return;
    }

    elements.tableBody.innerHTML = state.receptions.map((reception) => `
        <tr data-reception-id="${escapeHtml(reception.id)}" tabindex="0">
            <td><strong>${escapeHtml(reception.numero_recepcion || 'Ingreso abierto')}</strong><small>${escapeHtml(formatDate(reception.ingreso_at))}</small></td>
            <td><strong>${escapeHtml(reception.cliente.nombre)}</strong><small>Guía ${escapeHtml(reception.numero_guia_despacho)}</small></td>
            <td><strong>${escapeHtml(reception.patente_camion)}</strong><small>${escapeHtml(reception.nombre_conductor)}</small></td>
            <td><strong>${escapeHtml(envasesLabel(reception))}</strong><small>${escapeHtml(label(reception.tipo_recepcion))}</small></td>
            <td class="weight-cell"><strong>${escapeHtml(formatWeight(reception.peso_neto, formatWeight(reception.peso_bruto)))}</strong><small>${reception.peso_neto === null ? 'Peso bruto' : 'Peso neto'}</small></td>
            <td>${stateBadge(reception.estado)}</td>
        </tr>`).join('');
}

async function loadCatalogs() { state.catalogs = await api('/api/romana/catalogos'); fillCatalogs(); }
async function loadReceptions({ silent = false } = {}) {
    const payload = await api(`/api/romana/recepciones?${filterQuery()}`); renderList(payload);
    if (state.selected) {
        const exists = state.receptions.some((item) => item.id === state.selected.id);
        if (exists) await selectReception(state.selected.id, { silent: true });
        else closeDetail();
    }
    if (!silent) toast('Recepciones actualizadas.');
}

function fact(title, value, weight = false) { return `<article class="detail-fact${weight ? ' detail-fact--weight' : ''}"><span>${escapeHtml(title)}</span><strong>${escapeHtml(value ?? '—')}</strong></article>`; }
function envasesLabel(reception) { return (reception.envases || []).map((item) => `${item.cantidad_declarada} ${label(item.tipo_envase)}`).join(' · ') || 'Sin envases'; }
function renderDetail(reception) {
    state.selected = reception;
    elements.detail.classList.remove('is-hidden');
    elements.detailTitle.textContent = reception.numero_recepcion || `Ingreso · ${reception.patente_camion}`;
    elements.detailSubtitle.innerHTML = `${stateBadge(reception.estado)} · Guía ${escapeHtml(reception.numero_guia_despacho)} · ${escapeHtml(reception.cliente.nombre)}`;
    elements.detailFacts.innerHTML = [
        fact('INGRESO', formatDate(reception.ingreso_at)), fact('SALIDA / DESTARE', formatDate(reception.salida_at)), fact('TEMPORADA GLOBAL', `${reception.temporada.nombre} · ${reception.temporada.codigo}`), fact('CLIENTE', reception.cliente.nombre), fact('TIPO RECEPCIÓN', label(reception.tipo_recepcion)), fact('SERVICIO / CONCEPTO', reception.tipo_recepcion === 'solo_envases' ? label(reception.concepto_envases) : label(reception.tipo_servicio)), fact('GUÍA', reception.numero_guia_despacho),
        fact('CAMIÓN', reception.patente_camion), fact('CARRO', reception.patente_carro || 'No informado'), fact('CONDUCTOR', reception.nombre_conductor), fact('RUT', reception.rut_conductor), fact('ENVASES DECLARADOS', envasesLabel(reception)), fact('VALIDACIÓN MP', label(reception.estado_validacion_mp)),
        fact('PESO BRUTO', formatWeight(reception.peso_bruto), true), fact('TARA', formatWeight(reception.peso_tara), true), fact('PESO NETO', formatWeight(reception.peso_neto), true), fact('VERSIÓN', String(reception.version)), fact('OBS. INGRESO', reception.observacion || 'Sin observaciones'), fact('OBS. CIERRE', reception.observacion_cierre || 'Sin observaciones'),
    ].join('');
    elements.detailTimeline.innerHTML = (reception.eventos || []).map((event) => `<article class="timeline-item"><i></i><div><strong>${escapeHtml(label(event.tipo))}</strong><small>${escapeHtml(event.usuario?.nombre || 'Sistema')} · ${escapeHtml(event.estado_anterior ? `${label(event.estado_anterior)} → ${label(event.estado_nuevo)}` : label(event.estado_nuevo))}</small></div><time>${escapeHtml(formatDate(event.ocurrido_at))}</time></article>`).join('');
    elements.weightBalance.innerHTML = `<div><span>Bruto</span><strong>${escapeHtml(formatWeight(reception.peso_bruto))}</strong></div><div><span>Tara</span><strong>${escapeHtml(formatWeight(reception.peso_tara))}</strong></div><div class="net-row"><span>Neto legal</span><strong>${escapeHtml(formatWeight(reception.peso_neto))}</strong></div>`;
    const canOperate = state.identity?.puede_operar_romana === true;
    elements.editReception.classList.toggle('is-hidden', !canOperate || !reception.puede_editar);
    elements.confirmEntry.classList.toggle('is-hidden', !canOperate || !reception.puede_confirmar_ingreso);
    elements.closeReception.classList.toggle('is-hidden', !canOperate || !reception.puede_cerrar);
    elements.downloadReceipt.classList.toggle('is-hidden', !reception.aviso_recibo_disponible);
    elements.detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function selectReception(id, { silent = false } = {}) {
    if (!silent) setBusy(true, 'Abriendo expediente de recepción…');
    try { const payload = await api(`/api/romana/recepciones/${id}`); renderDetail(payload.data); }
    catch (error) { toast(error.message, true); }
    finally { if (!silent) setBusy(false); }
}
function closeDetail() { state.selected = null; elements.detail.classList.add('is-hidden'); }

function openNewReception() {
    elements.receptionForm.reset(); elements.receptionForm.elements.recepcion_id.value = ''; elements.receptionFormError.textContent = '';
    elements.receptionDialogTitle.textContent = 'Registrar ingreso';
    const activeSeasons = state.catalogs.temporadas.filter((season) => season.activa);
    if (!activeSeasons.length) {
        toast('No existe una temporada global activa para recibir.', true); return;
    }
    if (!state.catalogs.clientes.length) {
        toast('No existen clientes operacionales activos para recibir.', true); return;
    }
    if (activeSeasons.length === 1) elements.receptionForm.elements.temporada_id.value = activeSeasons[0].id;
    toggleReceptionType();
    elements.receptionDialog.showModal();
}
function openEditReception() {
    if (!state.selected?.puede_editar) return;
    const reception = state.selected; const form = elements.receptionForm.elements;
    elements.receptionForm.reset(); elements.receptionFormError.textContent = ''; elements.receptionDialogTitle.textContent = 'Editar pesaje de ingreso';
    form.recepcion_id.value = reception.id; form.temporada_id.value = reception.temporada.id; form.cliente_id.value = reception.cliente.id; form.tipo_recepcion.value = reception.tipo_recepcion; form.concepto_envases.value = reception.concepto_envases || ''; form.tipo_servicio.value = reception.tipo_servicio;
    form.numero_guia_despacho.value = reception.numero_guia_despacho;
    ['bins', 'totes', 'esponjas'].forEach((tipo) => { const item = reception.envases.find((envase) => envase.tipo_envase === tipo); form[`cantidad_${tipo}`].value = item?.cantidad_declarada || 0; });
    form.patente_camion.value = reception.patente_camion; form.patente_carro.value = reception.patente_carro || ''; form.rut_conductor.value = reception.rut_conductor; form.nombre_conductor.value = reception.nombre_conductor;
    form.peso_bruto.value = reception.peso_bruto; form.observacion.value = reception.observacion || '';
    toggleReceptionType();
    elements.receptionDialog.showModal();
}

elements.login.addEventListener('submit', async (event) => {
    event.preventDefault(); elements.loginError.textContent = ''; setBusy(true, 'Validando acceso a Romana…');
    try {
        const payload = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(elements.login))) });
        state.token = payload.token;
        if (payload.usuario.puede_consultar_romana !== true) {
            await api('/api/acceso-oficina', { method: 'DELETE' }).catch(() => {}); state.token = null;
            throw new ApiError('Tu perfil no posee acceso al módulo de Romana.', 403);
        }
        persist(payload); showApp(); setBusy(true, 'Cargando operación de Romana…'); await Promise.all([loadCatalogs(), loadReceptions({ silent: true })]); startRefresh();
    } catch (error) { elements.loginError.textContent = error.message; }
    finally { setBusy(false); }
});

elements.receptionForm.addEventListener('submit', async (event) => {
    if (event.submitter?.value === 'cancel') return;
    event.preventDefault(); elements.receptionFormError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.receptionForm)); const id = data.recepcion_id; delete data.recepcion_id; data.operacion_id = operationUuid();
    data.envases = ['bins', 'totes', 'esponjas'].map((tipo) => ({ tipo_envase: tipo, cantidad: Number(data[`cantidad_${tipo}`] || 0) })).filter((item) => item.cantidad > 0);
    ['bins', 'totes', 'esponjas'].forEach((tipo) => delete data[`cantidad_${tipo}`]);
    setBusy(true, id ? 'Actualizando ingreso…' : 'Registrando pesaje de ingreso…');
    try {
        const payload = await api(id ? `/api/romana/recepciones/${id}` : '/api/romana/recepciones', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) });
        elements.receptionDialog.close(); await loadReceptions({ silent: true }); await selectReception(payload.data.id, { silent: true }); toast(id ? 'Ingreso actualizado.' : 'Pesaje de ingreso registrado.');
    } catch (error) { elements.receptionFormError.textContent = error.message; }
    finally { setBusy(false); }
});

elements.confirmEntry.addEventListener('click', async () => {
    if (!state.selected || !window.confirm(`¿Confirmar el ingreso de ${state.selected.patente_camion}? Después de esta acción los antecedentes contractuales no podrán editarse.`)) return;
    setBusy(true, 'Confirmando ingreso y liberando camión…');
    try {
        const payload = await api(`/api/romana/recepciones/${state.selected.id}/confirmar-ingreso`, { method: 'POST', body: JSON.stringify({ operacion_id: operationUuid() }) });
        await loadReceptions({ silent: true }); renderDetail(payload.data); toast('Ingreso confirmado. El camión quedó pendiente de destare.');
    } catch (error) { toast(error.message, true); }
    finally { setBusy(false); }
});

elements.closeReception.addEventListener('click', () => {
    if (!state.selected?.puede_cerrar) return;
    elements.tareForm.reset(); elements.tareFormError.textContent = ''; elements.netWeightPreview.textContent = '—';
    elements.tareDescription.textContent = `${state.selected.patente_camion} · bruto ${formatWeight(state.selected.peso_bruto)}. Captura la lectura del camión vacío.`;
    elements.tareDialog.showModal(); elements.tareForm.elements.peso_tara.focus();
});
elements.tareForm.elements.peso_tara.addEventListener('input', () => {
    const tare = Number(elements.tareForm.elements.peso_tara.value); const gross = Number(state.selected?.peso_bruto || 0); const net = gross - tare;
    elements.netWeightPreview.textContent = tare > 0 && net > 0 ? formatWeight(net) : '—';
});
elements.tareForm.addEventListener('submit', async (event) => {
    if (event.submitter?.value === 'cancel') return;
    event.preventDefault(); if (!state.selected) return; elements.tareFormError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.tareForm)); data.operacion_id = operationUuid(); setBusy(true, 'Calculando neto y cerrando recepción…');
    try {
        const payload = await api(`/api/romana/recepciones/${state.selected.id}/cerrar`, { method: 'POST', body: JSON.stringify(data) });
        elements.tareDialog.close(); await loadReceptions({ silent: true }); renderDetail(payload.data); toast(`${payload.data.numero_recepcion} cerrada correctamente.`);
    } catch (error) { elements.tareFormError.textContent = error.message; }
    finally { setBusy(false); }
});

elements.downloadReceipt.addEventListener('click', async () => {
    if (!state.selected?.aviso_recibo_disponible) return; setBusy(true, 'Generando Aviso de Recibo…');
    try {
        const response = await fetch(`/api/romana/recepciones/${state.selected.id}/aviso-recibo`, { headers: { Accept: 'application/pdf', Authorization: `Bearer ${state.token}` } });
        if (!response.ok) { const data = await response.json().catch(() => ({})); throw new ApiError(errorMessage(data, 'No fue posible generar el PDF.'), response.status); }
        const blob = await response.blob(); const url = URL.createObjectURL(blob); const anchor = document.createElement('a'); anchor.href = url; anchor.download = `aviso-recibo-${state.selected.numero_recepcion.toLowerCase()}.pdf`; anchor.click(); window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) { toast(error.message, true); }
    finally { setBusy(false); }
});

elements.tableBody.addEventListener('click', (event) => { const row = event.target.closest('[data-reception-id]'); if (row) void selectReception(row.dataset.receptionId); });
elements.tableBody.addEventListener('keydown', (event) => { const row = event.target.closest('[data-reception-id]'); if (row && ['Enter', ' '].includes(event.key)) { event.preventDefault(); void selectReception(row.dataset.receptionId); } });
elements.filters.addEventListener('submit', async (event) => { event.preventDefault(); state.page = 1; setBusy(true, 'Aplicando filtros…'); try { await loadReceptions({ silent: true }); } catch (error) { toast(error.message, true); } finally { setBusy(false); } });
elements.previousPage.addEventListener('click', async () => { if (state.page <= 1) return; state.page--; await loadReceptions({ silent: true }); });
elements.nextPage.addEventListener('click', async () => { if (state.meta && state.page >= state.meta.ultima_pagina) return; state.page++; await loadReceptions({ silent: true }); });
elements.reload.addEventListener('click', async () => { setBusy(true, 'Actualizando Romana…'); try { await Promise.all([loadCatalogs(), loadReceptions({ silent: true })]); toast('Romana actualizada.'); } catch (error) { toast(error.message, true); } finally { setBusy(false); } });
elements.newReception.addEventListener('click', openNewReception); elements.editReception.addEventListener('click', openEditReception); elements.closeDetail.addEventListener('click', closeDetail);
function toggleReceptionType() { const soloEnvases = elements.receptionForm.elements.tipo_recepcion.value === 'solo_envases'; elements.serviceField.classList.toggle('is-hidden', soloEnvases); elements.containerConceptField.classList.toggle('is-hidden', !soloEnvases); elements.receptionForm.elements.tipo_servicio.required = !soloEnvases; elements.receptionForm.elements.concepto_envases.required = soloEnvases; }
elements.receptionForm.elements.tipo_recepcion.addEventListener('change', toggleReceptionType);
['patente_camion', 'patente_carro'].forEach((name) => elements.receptionForm.elements[name].addEventListener('input', (event) => { event.target.value = event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ''); }));
elements.logout.addEventListener('click', async () => { try { await api('/api/acceso-oficina', { method: 'DELETE' }); } finally { clearSession(); } });

function startRefresh() {
    window.clearInterval(state.timer);
    state.timer = window.setInterval(() => {
        if (!document.hidden && !elements.receptionDialog.open && !elements.tareDialog.open) void loadReceptions({ silent: true }).catch(() => {});
    }, 30000);
}

async function boot() {
    if (!state.token || state.identity?.puede_consultar_romana !== true) { if (state.token) clearSession(); return; }
    if (!showApp()) return; setBusy(true, 'Cargando operación de Romana…');
    try { await Promise.all([loadCatalogs(), loadReceptions({ silent: true })]); startRefresh(); }
    catch (error) { if (error.status !== 401) toast(error.message, true); }
    finally { setBusy(false); }
}

void boot();
