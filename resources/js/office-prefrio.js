const byId = (id) => document.getElementById(id);
const elements = {
    access: byId('officeAccess'), app: byId('officeApp'), login: byId('officeLoginForm'), loginError: byId('officeLoginError'),
    userName: byId('officeUserName'), userRole: byId('officeUserRole'), initials: byId('officeInitials'), logout: byId('officeLogoutButton'),
    camerasNav: byId('officeCamerasNav'), loadsNav: byId('officeLoadsNav'), materialsNav: byId('officeMaterialsNav'), validationNav: byId('officeValidationNav'), accessesNav: byId('officeAccessesNav'), managementNav: byId('officeManagementNav'), romanaNav: byId('officeRomanaNav'),
    reload: byId('reloadPrefrioButton'), newTunnel: byId('newTunnelButton'), newProcess: byId('newProcessButton'),
    activeTunnels: byId('activeTunnelCount'), running: byId('runningProcessCount'), pending: byId('pendingVerificationCount'), reprocess: byId('reprocessCount'), activeFolios: byId('activeFolioCount'),
    tunnelSummary: byId('tunnelSummary'), tunnelList: byId('tunnelList'), filters: byId('processFilters'), processBody: byId('processTableBody'),
    detail: byId('processDetail'), detailTitle: byId('processDetailTitle'), detailSubtitle: byId('processDetailSubtitle'), detailMetrics: byId('processDetailMetrics'),
    tunnelMap: byId('processTunnelMap'), timeline: byId('processTimeline'), refreshProcess: byId('refreshProcessButton'), closeDetail: byId('closeProcessDetailButton'),
    decisionPanel: byId('decisionPanel'), decisionFolios: byId('decisionFolios'), decisionError: byId('decisionError'),
    approve: byId('approveProcessButton'), reprocessButton: byId('reprocessProcessButton'), cancelProcess: byId('cancelProcessButton'),
    tunnelDialog: byId('tunnelDialog'), tunnelForm: byId('tunnelForm'), tunnelDialogTitle: byId('tunnelDialogTitle'), tunnelError: byId('tunnelFormError'), tunnelPreview: byId('tunnelPreview'), tunnelPreviewSummary: byId('tunnelPreviewSummary'),
    processDialog: byId('processDialog'), processForm: byId('processForm'), processError: byId('processFormError'),
    reasonDialog: byId('reasonDialog'), reasonForm: byId('reasonForm'), reasonTitle: byId('reasonTitle'), reasonDescription: byId('reasonDescription'), reasonEyebrow: byId('reasonEyebrow'), reasonError: byId('reasonError'), confirmReason: byId('confirmReasonButton'),
    loading: byId('officeLoading'), loadingText: byId('officeLoadingText'), toasts: byId('officeToasts'),
};
const keys = { token: 'estiba_wms_office_token', identity: 'estiba_wms_office_identity' };
const activeStates = new Set(['borrador', 'cargando', 'listo_para_iniciar', 'en_proceso', 'pendiente_verificacion']);
const state = {
    token: localStorage.getItem(keys.token), identity: readJson(keys.identity), tunnels: [], processes: [], summary: null, selectedTunnelId: null,
    selectedProcess: null, reasonMode: null,
};

class ApiError extends Error {
    constructor(message, status, data = {}) { super(message); this.name = 'ApiError'; this.status = status; this.data = data; }
}
function readJson(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; } }
function escapeHtml(value) { return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }
function errorMessage(data, fallback) { return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback; }
function formatDate(value, fallback = 'Sin fecha') { if (!value) return fallback; const date = new Date(value); return Number.isNaN(date.getTime()) ? fallback : new Intl.DateTimeFormat('es-CL', { dateStyle: 'short', timeStyle: 'short' }).format(date); }
function statusText(value) {
    const labels = {
        borrador: 'Borrador', cargando: 'Cargando', listo_para_iniciar: 'Listo para iniciar', en_proceso: 'En proceso', pendiente_verificacion: 'Pendiente de verificación',
        aprobado: 'Aprobado', requiere_reproceso: 'Requiere reproceso', cancelado: 'Cancelado', operativo: 'Operativo', mantenimiento: 'Mantenimiento', fuera_de_servicio: 'Fuera de servicio',
        activo: 'Activo', inactivo: 'Inactivo', carga_iniciada: 'Carga iniciada', pallet_agregado: 'Pallet agregado', pallet_retirado: 'Pallet retirado', armado_confirmado: 'Armado confirmado',
        proceso_iniciado: 'Proceso iniciado', inversion_registrada: 'Inversión registrada', pausa: 'Pausa', reanudacion: 'Reanudación', deshielo: 'Deshielo', lectura: 'Lectura',
        verificacion_final: 'Verificación final', aprobacion: 'Aprobación', reproceso: 'Reproceso', cancelacion: 'Cancelación',
    };
    return labels[value] || String(value || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
}
function positionMeta(number) {
    return { side: number % 2 === 1 ? 'A' : 'B', depth: Math.ceil(number / 2) };
}
function setBusy(active, message = 'Procesando…') { elements.loadingText.textContent = message; elements.loading.classList.toggle('is-hidden', !active); elements.loading.setAttribute('aria-hidden', String(!active)); }
function toast(message, error = false) { const node = document.createElement('div'); node.className = `toast${error ? ' toast--error' : ''}`; node.textContent = message; elements.toasts.append(node); window.setTimeout(() => node.remove(), 4500); }
function operationUuid() { if (typeof crypto.randomUUID === 'function') return crypto.randomUUID(); const bytes = crypto.getRandomValues(new Uint8Array(16)); bytes[6] = (bytes[6] & 0x0f) | 0x40; bytes[8] = (bytes[8] & 0x3f) | 0x80; const hex = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join(''); return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`; }
function persist(payload) { state.token = payload.token; state.identity = payload.usuario; localStorage.setItem(keys.token, payload.token); localStorage.setItem(keys.identity, JSON.stringify(payload.usuario)); }
function clearSession() { state.token = null; state.identity = null; localStorage.removeItem(keys.token); localStorage.removeItem(keys.identity); elements.app.classList.add('is-hidden'); elements.access.classList.remove('is-hidden'); }
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
    if (state.identity?.puede_consultar_prefrio !== true) { clearSession(); elements.loginError.textContent = 'El usuario no posee acceso a Prefrío.'; return false; }
    elements.access.classList.add('is-hidden'); elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Usuario'; elements.userName.textContent = name; elements.userRole.textContent = statusText(state.identity?.rol);
    elements.initials.textContent = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    elements.accessesNav.classList.toggle('is-hidden', state.identity?.puede_administrar_accesos !== true);
    elements.managementNav.classList.toggle('is-hidden', state.identity?.puede_consultar_panel_gerencial !== true);
    elements.romanaNav.classList.toggle('is-hidden', state.identity?.puede_consultar_romana !== true);
    elements.camerasNav.classList.toggle('is-hidden', state.identity?.ambito_camaras === 'ninguno');
    elements.loadsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_cargas !== true);
    elements.materialsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_despachos_materiales !== true);
    elements.validationNav.classList.toggle('is-hidden', state.identity?.puede_consultar_validaciones_pallet !== true);
    elements.newTunnel.classList.toggle('is-hidden', state.identity?.puede_administrar_tuneles_prefrio !== true);
    elements.newProcess.classList.toggle('is-hidden', state.identity?.puede_operar_prefrio !== true);
    return true;
}

async function loadTunnels() {
    const response = await api('/api/prefrio/tuneles'); state.tunnels = response.data || [];
    if (state.selectedTunnelId && !state.tunnels.some((item) => item.id === state.selectedTunnelId)) state.selectedTunnelId = null;
    renderTunnels(); renderProcessTunnelOptions(); renderProcessFormOptions(); renderMetrics();
}
async function loadProcesses() {
    const params = new URLSearchParams(); const values = Object.fromEntries(new FormData(elements.filters));
    for (const [key, value] of Object.entries(values)) if (String(value).trim()) params.set(key, String(value).trim());
    params.set('per_page', '50');
    const response = await api(`/api/prefrio/procesos?${params}`); state.processes = response.data || [];
    renderProcesses(); renderMetrics();
}
async function loadSummary() { state.summary = await api('/api/prefrio/resumen'); renderMetrics(); }
async function loadAll() { await Promise.all([loadTunnels(), loadProcesses(), loadSummary()]); if (state.selectedProcess?.id) await selectProcess(state.selectedProcess.id, false); }

function renderMetrics() {
    elements.activeTunnels.textContent = String(state.tunnels.filter((item) => item.estado_administrativo === 'activo' && item.estado_tecnico === 'operativo').length);
    elements.running.textContent = String(state.summary?.en_proceso ?? '—');
    elements.pending.textContent = String(state.summary?.pendiente_verificacion ?? '—');
    elements.reprocess.textContent = String(state.summary?.requiere_reproceso ?? '—');
    elements.activeFolios.textContent = String(state.summary?.folios_activos ?? '—');
}
function renderTunnels() {
    elements.tunnelSummary.textContent = `${state.tunnels.length} configurados`;
    elements.tunnelList.innerHTML = state.tunnels.map((item) => {
        const active = item.proceso_activo;
        const inactive = item.estado_administrativo !== 'activo' || item.estado_tecnico !== 'operativo';
        return `<article class="tunnel-card${state.selectedTunnelId === item.id ? ' is-selected' : ''}${inactive ? ' is-inactive' : ''}" data-tunnel-id="${item.id}">
            <div class="tunnel-card__top"><strong>${escapeHtml(item.codigo)}</strong><span class="status-pill status-pill--${escapeHtml(active?.estado || item.estado_tecnico)}">${escapeHtml(active ? statusText(active.estado) : statusText(item.estado_tecnico))}</span></div>
            <div><h3>${escapeHtml(item.nombre)}</h3><p>${item.capacidad_posiciones} posiciones · setpoint ${item.setpoint_habitual ?? '—'} °C</p></div>
            <div class="tunnel-card__meta"><span>${escapeHtml(statusText(item.estado_administrativo))}</span><span>versión ${item.version_configuracion}</span></div>
            <div class="tunnel-card__footer"><span>${active ? `${escapeHtml(active.codigo)} · ${active.folios_cargados} folios` : 'Sin proceso activo'}</span>${state.identity?.puede_administrar_tuneles_prefrio === true ? `<button data-edit-tunnel="${item.id}" type="button">Configurar</button>` : ''}</div>
        </article>`;
    }).join('') || '<p class="empty-prefrio">No existen túneles configurados.</p>';
}
function renderProcessTunnelOptions() {
    const select = elements.filters.elements.tunel_prefrio_id; const current = select.value;
    select.innerHTML = `<option value="">Todos los túneles</option>${state.tunnels.map((item) => `<option value="${item.id}">${escapeHtml(item.codigo)} · ${escapeHtml(item.nombre)}</option>`).join('')}`;
    if ([...select.options].some((option) => option.value === current)) select.value = current;
}
function renderProcessFormOptions() {
    const select = elements.processForm.elements.tunel_prefrio_id; const current = select.value;
    const available = state.tunnels.filter((item) => item.estado_administrativo === 'activo' && item.estado_tecnico === 'operativo' && !item.proceso_activo);
    select.innerHTML = `<option value="">Selecciona un túnel disponible</option>${available.map((item) => `<option value="${item.id}">${escapeHtml(item.codigo)} · ${escapeHtml(item.nombre)} · ${item.capacidad_posiciones} posiciones</option>`).join('')}`;
    if ([...select.options].some((option) => option.value === current)) select.value = current;
}
function renderProcesses() {
    elements.processBody.innerHTML = state.processes.map((item) => {
        const activeFolios = (item.folios || []).filter((folio) => !['retirado', 'cancelado'].includes(folio.estado));
        const finalText = item.estado === 'aprobado' ? 'Habilitado' : item.estado === 'requiere_reproceso' ? 'Retenido' : item.estado === 'cancelado' ? 'Cancelado' : 'Pendiente';
        return `<tr data-process-id="${item.id}"><td><strong>${escapeHtml(item.codigo)}</strong><small>v${item.version} · ${escapeHtml(item.formato_referencia || 'Sin formato')}</small></td><td><strong>${escapeHtml(item.tunel?.codigo || '—')}</strong><small>${escapeHtml(item.tunel?.nombre || '')}</small></td><td><span class="status-pill status-pill--${escapeHtml(item.estado)}">${escapeHtml(statusText(item.estado))}</span></td><td><strong>${activeFolios.length}</strong><small>de ${item.tunel?.capacidad_posiciones || '—'} posiciones</small></td><td>${escapeHtml(formatDate(item.iniciado_at, 'No iniciado'))}</td><td><strong>${escapeHtml(finalText)}</strong><small>${escapeHtml(formatDate(item.finalizado_at, '—'))}</small></td></tr>`;
    }).join('') || '<tr><td class="empty-prefrio" colspan="6">No existen procesos coincidentes.</td></tr>';
}

async function selectProcess(id, scroll = true) {
    setBusy(true, 'Cargando proceso…');
    try {
        const response = await api(`/api/prefrio/procesos/${id}`); state.selectedProcess = response.data; renderProcessDetail();
        if (scroll) elements.detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) { toast(error.message, true); } finally { setBusy(false); }
}
function renderProcessDetail() {
    const process = state.selectedProcess; if (!process) { elements.detail.classList.add('is-hidden'); return; }
    elements.detail.classList.remove('is-hidden'); elements.detailTitle.textContent = `${process.codigo} · ${process.tunel?.codigo || 'Sin túnel'}`;
    elements.detailSubtitle.textContent = `${process.tunel?.nombre || ''} · ${statusText(process.estado)} · setpoint ${process.setpoint} °C · versión ${process.version}`;
    const occupied = (process.folios || []).filter((item) => !['retirado', 'cancelado'].includes(item.estado));
    elements.detailMetrics.innerHTML = [
        ['ESTADO', statusText(process.estado)], ['FOLIOS', occupied.length], ['CAPACIDAD', process.tunel?.capacidad_posiciones || '—'], ['INICIO', formatDate(process.iniciado_at, 'No iniciado')], ['FINAL', formatDate(process.finalizado_at, 'Pendiente')],
    ].map(([label, value]) => `<article><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></article>`).join('');
    renderTunnelMap(process, occupied); renderTimeline(process); renderDecisionPanel(process, occupied);
}
function renderTunnelMap(process, occupied) {
    const byPosition = new Map(occupied.map((item) => [item.posicion?.numero, item])); const capacity = Number(process.tunel?.capacidad_posiciones || 0);
    elements.tunnelMap.innerHTML = Array.from({ length: capacity }, (_, index) => {
        const number = index + 1; const item = byPosition.get(number); const reprocess = item?.estado === 'requiere_reproceso';
        const meta = positionMeta(number);
        return `<article class="tunnel-slot${item ? ' is-occupied' : ''}${reprocess ? ' is-reprocess' : ''}"><strong>P${String(number).padStart(2, '0')}</strong><small>Lado ${meta.side} · Prof. ${meta.depth}</small><span>${item ? escapeHtml(item.folio?.numero_folio || 'Ocupado') : 'Libre'}</span>${item?.temperatura_final !== null && item?.temperatura_final !== undefined ? `<span>${item.temperatura_final} °C</span>` : ''}</article>`;
    }).join('') || '<p class="empty-prefrio">El túnel no posee posiciones configuradas.</p>';
}
function renderTimeline(process) {
    elements.timeline.innerHTML = (process.eventos || []).map((event) => `<article class="event-item"><strong>${escapeHtml(statusText(event.tipo))}</strong><span>${escapeHtml(event.usuario?.nombre || 'Usuario')} · ${escapeHtml(event.dispositivo?.codigo || 'Oficina')}</span><small>${escapeHtml(formatDate(event.ocurrido_at))}${event.observacion ? ` · ${escapeHtml(event.observacion)}` : ''}</small></article>`).join('') || '<p class="empty-prefrio">Sin eventos registrados.</p>';
}
function renderDecisionPanel(process, occupied) {
    const supervisor = state.identity?.puede_supervisar_prefrio === true; const terminal = !activeStates.has(process.estado);
    elements.decisionPanel.classList.toggle('is-hidden', !supervisor || terminal);
    if (!supervisor || terminal) return;
    const pending = process.estado === 'pendiente_verificacion';
    elements.approve.classList.toggle('is-hidden', !pending); elements.reprocessButton.classList.toggle('is-hidden', !pending); elements.cancelProcess.classList.toggle('is-hidden', terminal);
    elements.decisionFolios.classList.toggle('is-hidden', !pending);
    elements.decisionFolios.innerHTML = occupied.map((item) => `<article class="decision-folio" data-decision-folio="${item.folio.id}"><div class="decision-folio__title"><strong>${escapeHtml(item.folio.numero_folio)}</strong><small>${escapeHtml(item.posicion?.etiqueta || '')}</small></div><div class="decision-folio__fields"><input name="temperatura_final" type="number" min="-20" max="50" step="0.1" value="${item.temperatura_final ?? ''}" placeholder="Temp. final"><input name="observacion" maxlength="1000" value="${escapeHtml(item.observacion || '')}" placeholder="Observación"></div></article>`).join('') || '<p class="empty-prefrio">El proceso no posee folios activos.</p>';
    elements.decisionError.textContent = '';
}
function decisionResults() {
    return [...elements.decisionFolios.querySelectorAll('[data-decision-folio]')].map((node) => {
        const temperature = node.querySelector('[name="temperatura_final"]').value;
        const observation = node.querySelector('[name="observacion"]').value.trim();
        return { folio_id: node.dataset.decisionFolio, temperatura_final: temperature === '' ? null : Number(temperature), observation: observation || null };
    }).map(({ observation, ...item }) => ({ ...item, observacion: observation }));
}

function openTunnelDialog(tunnel = null) {
    elements.tunnelForm.reset(); elements.tunnelError.textContent = ''; elements.tunnelForm.elements.id.value = tunnel?.id || '';
    elements.tunnelDialogTitle.textContent = tunnel ? `${tunnel.codigo} · Editar túnel` : 'Nuevo túnel';
    if (tunnel) for (const field of ['nombre', 'capacidad_posiciones', 'setpoint_habitual', 'estado_administrativo', 'estado_tecnico', 'codigo_externo', 'observacion']) if (elements.tunnelForm.elements[field]) elements.tunnelForm.elements[field].value = tunnel[field] ?? '';
    else { elements.tunnelForm.elements.capacidad_posiciones.value = 22; elements.tunnelForm.elements.setpoint_habitual.value = -1.5; elements.tunnelForm.elements.estado_administrativo.value = 'activo'; elements.tunnelForm.elements.estado_tecnico.value = 'operativo'; }
    renderTunnelPreview(); elements.tunnelDialog.showModal();
}
function renderTunnelPreview() {
    const capacity = Math.max(2, Math.min(100, Number(elements.tunnelForm.elements.capacidad_posiciones.value || 2)));
    elements.tunnelPreviewSummary.textContent = `${capacity} posiciones`;
    elements.tunnelPreview.innerHTML = Array.from({ length: capacity }, (_, index) => {
        const number = index + 1; const meta = positionMeta(number);
        return `<article class="tunnel-slot"><strong>P${String(number).padStart(2, '0')}</strong><small>Lado ${meta.side} · Prof. ${meta.depth}</small><span>Activa</span></article>`;
    }).join('');
}
function openProcessDialog() {
    elements.processForm.reset(); elements.processError.textContent = ''; renderProcessFormOptions();
    const first = elements.processForm.elements.tunel_prefrio_id.options[1]; if (first) { elements.processForm.elements.tunel_prefrio_id.value = first.value; syncProcessSetpoint(); }
    elements.processDialog.showModal();
}
function syncProcessSetpoint() {
    const tunnel = state.tunnels.find((item) => item.id === elements.processForm.elements.tunel_prefrio_id.value);
    if (tunnel?.setpoint_habitual !== null && tunnel?.setpoint_habitual !== undefined) elements.processForm.elements.setpoint.value = tunnel.setpoint_habitual;
}
function openReasonDialog(mode) {
    state.reasonMode = mode; elements.reasonForm.reset(); elements.reasonError.textContent = '';
    const reprocess = mode === 'reprocess'; elements.reasonEyebrow.textContent = reprocess ? 'REPROCESO' : 'CANCELACIÓN'; elements.reasonTitle.textContent = reprocess ? 'Enviar proceso a reproceso' : 'Cancelar proceso';
    elements.reasonDescription.textContent = reprocess ? 'Los folios quedarán retenidos y podrán incorporarse a un nuevo ciclo.' : 'La cancelación conserva el historial y retiene los folios cuando el ciclo ya había iniciado.';
    elements.confirmReason.textContent = reprocess ? 'Confirmar reproceso' : 'Confirmar cancelación'; elements.reasonDialog.showModal();
}

async function saveTunnel() {
    const values = Object.fromEntries(new FormData(elements.tunnelForm)); const id = values.id; delete values.id;
    values.capacidad_posiciones = Number(values.capacidad_posiciones); values.setpoint_habitual = values.setpoint_habitual === '' ? null : Number(values.setpoint_habitual);
    const path = id ? `/api/administracion/prefrio/tuneles/${id}` : '/api/administracion/prefrio/tuneles';
    setBusy(true, id ? 'Actualizando túnel…' : 'Creando túnel…');
    try { await api(path, { method: id ? 'PUT' : 'POST', body: JSON.stringify(values) }); elements.tunnelDialog.close(); toast(id ? 'Túnel actualizado.' : 'Túnel creado.'); await loadAll(); }
    catch (error) { elements.tunnelError.textContent = error.message; } finally { setBusy(false); }
}
async function saveProcess() {
    const values = Object.fromEntries(new FormData(elements.processForm));
    const payload = { operacion_id: operationUuid(), tunel_prefrio_id: values.tunel_prefrio_id, setpoint: Number(values.setpoint), duracion_objetivo_minutos: values.duracion_objetivo_minutos ? Number(values.duracion_objetivo_minutos) : null, formato_referencia: values.formato_referencia.trim() || null, observacion: values.observacion.trim() || null, ocurrido_at: new Date().toISOString() };
    setBusy(true, 'Creando proceso…');
    try { const response = await api('/api/prefrio/procesos', { method: 'POST', body: JSON.stringify(payload) }); elements.processDialog.close(); toast(`Proceso ${response.data.codigo} creado.`); await loadAll(); await selectProcess(response.data.id); }
    catch (error) { elements.processError.textContent = error.message; } finally { setBusy(false); }
}
async function approveProcess() {
    const process = state.selectedProcess; if (!process || !window.confirm(`¿Aprobar ${process.codigo} y habilitar sus folios para almacenamiento?`)) return;
    setBusy(true, 'Aprobando proceso…');
    try { const response = await api(`/api/prefrio/procesos/${process.id}/aprobar`, { method: 'POST', body: JSON.stringify({ operacion_id: operationUuid(), version_conocida: process.version, resultados: decisionResults(), ocurrido_at: new Date().toISOString() }) }); state.selectedProcess = response.data; renderProcessDetail(); toast('Proceso aprobado y folios habilitados.'); await Promise.all([loadTunnels(), loadProcesses()]); }
    catch (error) { elements.decisionError.textContent = error.message; } finally { setBusy(false); }
}
async function submitReason() {
    const process = state.selectedProcess; const values = Object.fromEntries(new FormData(elements.reasonForm)); const mode = state.reasonMode;
    const payload = { operacion_id: operationUuid(), version_conocida: process.version, motivo: values.motivo.trim(), observacion: values.observacion.trim() || null, ocurrido_at: new Date().toISOString() };
    if (mode === 'reprocess') payload.resultados = decisionResults();
    const path = mode === 'reprocess' ? `/api/prefrio/procesos/${process.id}/reprocesar` : `/api/prefrio/procesos/${process.id}/cancelar`;
    setBusy(true, mode === 'reprocess' ? 'Registrando reproceso…' : 'Cancelando proceso…');
    try { const response = await api(path, { method: 'POST', body: JSON.stringify(payload) }); elements.reasonDialog.close(); state.selectedProcess = response.data; renderProcessDetail(); toast(mode === 'reprocess' ? 'Proceso enviado a reproceso.' : 'Proceso cancelado.'); await Promise.all([loadTunnels(), loadProcesses()]); }
    catch (error) { elements.reasonError.textContent = error.message; } finally { setBusy(false); }
}

async function bootstrap() {
    if (!state.token || !state.identity) return;
    if (!showApp()) return;
    setBusy(true, 'Cargando Prefrío…');
    try { await loadAll(); } catch (error) { toast(error.message, true); if (error.status === 403) clearSession(); } finally { setBusy(false); }
}

elements.login.addEventListener('submit', async (event) => {
    event.preventDefault(); elements.loginError.textContent = ''; setBusy(true, 'Validando acceso…');
    const values = Object.fromEntries(new FormData(elements.login));
    try { const payload = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify(values) }); persist(payload); if (showApp()) await loadAll(); }
    catch (error) { elements.loginError.textContent = error.message; } finally { setBusy(false); }
});
elements.logout.addEventListener('click', async () => { try { await api('/api/acceso-oficina', { method: 'DELETE' }); } catch {} clearSession(); });
elements.reload.addEventListener('click', async () => { setBusy(true, 'Actualizando tablero…'); try { await loadAll(); toast('Prefrío actualizado.'); } catch (error) { toast(error.message, true); } finally { setBusy(false); } });
elements.filters.addEventListener('submit', async (event) => { event.preventDefault(); setBusy(true, 'Filtrando procesos…'); try { await loadProcesses(); } catch (error) { toast(error.message, true); } finally { setBusy(false); } });
elements.tunnelList.addEventListener('click', (event) => { const edit = event.target.closest('[data-edit-tunnel]'); if (edit) { event.stopPropagation(); openTunnelDialog(state.tunnels.find((item) => item.id === edit.dataset.editTunnel)); return; } const card = event.target.closest('[data-tunnel-id]'); if (!card) return; state.selectedTunnelId = card.dataset.tunnelId; renderTunnels(); elements.filters.elements.tunel_prefrio_id.value = state.selectedTunnelId; elements.filters.requestSubmit(); });
elements.processBody.addEventListener('click', (event) => { const row = event.target.closest('[data-process-id]'); if (row) selectProcess(row.dataset.processId); });
elements.newTunnel.addEventListener('click', () => openTunnelDialog()); elements.newProcess.addEventListener('click', openProcessDialog);
elements.tunnelForm.elements.capacidad_posiciones.addEventListener('input', renderTunnelPreview);
elements.tunnelForm.addEventListener('submit', (event) => { event.preventDefault(); if (event.submitter?.value === 'cancel') { elements.tunnelDialog.close(); return; } if (!elements.tunnelForm.reportValidity()) return; saveTunnel(); });
elements.processForm.elements.tunel_prefrio_id.addEventListener('change', syncProcessSetpoint);
elements.processForm.addEventListener('submit', (event) => { event.preventDefault(); if (event.submitter?.value === 'cancel') { elements.processDialog.close(); return; } if (!elements.processForm.reportValidity()) return; saveProcess(); });
elements.refreshProcess.addEventListener('click', () => state.selectedProcess && selectProcess(state.selectedProcess.id, false));
elements.closeDetail.addEventListener('click', () => { state.selectedProcess = null; elements.detail.classList.add('is-hidden'); });
elements.approve.addEventListener('click', approveProcess); elements.reprocessButton.addEventListener('click', () => openReasonDialog('reprocess')); elements.cancelProcess.addEventListener('click', () => openReasonDialog('cancel'));
elements.reasonForm.addEventListener('submit', (event) => { event.preventDefault(); if (event.submitter?.value === 'cancel') { elements.reasonDialog.close(); return; } if (!elements.reasonForm.reportValidity()) return; submitReason(); });

bootstrap();
