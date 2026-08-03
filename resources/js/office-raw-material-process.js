const byId = (id) => document.getElementById(id);
const elements = {
    access: byId('officeAccess'), app: byId('officeApp'), login: byId('officeLoginForm'),
    loginError: byId('officeLoginError'), logout: byId('officeLogoutButton'),
    userName: byId('officeUserName'), userRole: byId('officeUserRole'), initials: byId('officeInitials'),
    reload: byId('reloadButton'), season: byId('seasonDescription'), filters: byId('processFilters'),
    list: byId('processLotList'), openLots: byId('openLotsCount'), available: byId('availableBinsCount'),
    delivered: byId('deliveredBinsCount'), completed: byId('completedLotsCount'),
    dialog: byId('deliveryDialog'), form: byId('deliveryForm'), title: byId('deliveryTitle'),
    description: byId('deliveryDescription'), summary: byId('deliverySummary'), error: byId('deliveryError'),
    loading: byId('officeLoading'), loadingText: byId('officeLoadingText'), toasts: byId('officeToasts'),
};
const keys = { token: 'estiba_wms_office_token', identity: 'estiba_wms_office_identity' };
const state = {
    token: localStorage.getItem(keys.token), identity: readJson(keys.identity), summary: null,
    lots: [], selected: null,
};

class ApiError extends Error {
    constructor(message, status = 0) { super(message); this.status = status; }
}

function readJson(key) {
    try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; }
}
function escapeHtml(value) {
    return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}
function errorMessage(data, fallback) {
    return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback;
}
function uuid() {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    const values = crypto.getRandomValues(new Uint8Array(16));
    values[6] = (values[6] & 15) | 64; values[8] = (values[8] & 63) | 128;
    const hex = [...values].map((item) => item.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
function formatNumber(value) { return new Intl.NumberFormat('es-CL').format(Number(value || 0)); }
function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('es-CL', { dateStyle: 'short', timeStyle: 'short' }).format(date);
}
function label(value) {
    const labels = { asignado_camara: 'Disponible', entrega_parcial_proceso: 'Entrega parcial', entregado_proceso: 'Completado', camarero_frio: 'Camarero', supervisor_frio: 'Supervisor de frío', administrador: 'Administrador' };
    return labels[value] || String(value || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
}
function setBusy(active, text = 'Procesando…') {
    elements.loadingText.textContent = text;
    elements.loading.classList.toggle('is-hidden', !active);
    elements.loading.setAttribute('aria-hidden', String(!active));
}
function toast(message, error = false) {
    const item = document.createElement('div');
    item.className = `toast${error ? ' toast--error' : ''}`; item.textContent = message;
    elements.toasts.append(item); window.setTimeout(() => item.remove(), 5000);
}
function persist(payload) {
    state.token = payload.token; state.identity = payload.usuario;
    localStorage.setItem(keys.token, payload.token);
    localStorage.setItem(keys.identity, JSON.stringify(payload.usuario));
}
function clearSession() {
    state.token = null; state.identity = null; state.lots = []; state.summary = null;
    localStorage.removeItem(keys.token); localStorage.removeItem(keys.identity);
    elements.app.classList.add('is-hidden'); elements.access.classList.remove('is-hidden');
}
async function api(path, options = {}) {
    const headers = new Headers(options.headers || {}); headers.set('Accept', 'application/json');
    if (state.token) headers.set('Authorization', `Bearer ${state.token}`);
    if (options.body) headers.set('Content-Type', 'application/json');
    let response;
    try { response = await fetch(path, { ...options, headers }); }
    catch { throw new ApiError('No fue posible conectar con Laravel.'); }
    const data = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401 && path !== '/api/acceso-oficina') clearSession();
        throw new ApiError(errorMessage(data, 'No fue posible completar la operación.'), response.status);
    }
    return data;
}
function can(capability) {
    return state.identity?.[capability] === true || state.identity?.capacidades?.[capability] === true;
}
function showApp() {
    if (!can('puede_consultar_fruta_proceso')) return false;
    elements.access.classList.add('is-hidden'); elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Usuario';
    elements.userName.textContent = name; elements.userRole.textContent = label(state.identity?.rol);
    elements.initials.textContent = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    return true;
}
function renderSummary() {
    const summary = state.summary || {};
    elements.openLots.textContent = formatNumber(summary.lotes_abiertos);
    elements.available.textContent = formatNumber(summary.bins_disponibles);
    elements.delivered.textContent = formatNumber(summary.bins_entregados);
    elements.completed.textContent = formatNumber(summary.lotes_completados);
    elements.season.textContent = summary.temporada
        ? `${summary.temporada.nombre} · ${summary.temporada.codigo}. Saldos vigentes desde cámaras de materia prima.`
        : 'No existe una temporada global activa.';
}
function renderDelivery(delivery) {
    const destination = `${delivery.linea_proceso} · turno ${delivery.turno} · orden ${delivery.numero_orden}`;
    return `<article class="process-delivery${delivery.anulado ? ' is-void' : ''}">
        <strong>${escapeHtml(delivery.cantidad_envases)} bins</strong>
        <div><strong>${escapeHtml(destination)}</strong><small>${escapeHtml(delivery.entregado_por?.nombre)} · ${escapeHtml(formatDate(delivery.entregado_at))}${delivery.anulado ? ` · Anulada: ${escapeHtml(delivery.motivo_anulacion)}` : ''}</small></div>
        ${delivery.puede_anular ? `<button data-annul="${escapeHtml(delivery.id)}" type="button">Anular</button>` : ''}
    </article>`;
}
function renderLots() {
    if (!state.lots.length) {
        elements.list.innerHTML = '<div class="process-empty">No existen lotes de bins para los filtros seleccionados.</div>';
        return;
    }
    elements.list.innerHTML = state.lots.map((lot) => {
        const complete = lot.progreso.disponibles === 0;
        const product = [lot.producto.especie, lot.producto.variedad, lot.producto.calibre].filter(Boolean).join(' · ');
        return `<article class="process-lot-card${complete ? ' is-complete' : ''}">
            <div class="process-lot-card__heading"><div><h3>${escapeHtml(lot.numero_lote)}</h3><p>${escapeHtml(lot.cliente?.nombre)} · recepción ${escapeHtml(lot.recepcion?.numero_recepcion)}</p></div><span class="process-status">${escapeHtml(label(lot.estado))}</span></div>
            <div class="process-facts"><div><span>CÁMARA</span><strong>${escapeHtml(lot.camara?.codigo)} · ${escapeHtml(lot.camara?.nombre)}</strong></div><div><span>PRODUCTO</span><strong>${escapeHtml(product)}</strong></div><div><span>ORIGEN</span><strong>CSG ${escapeHtml(lot.producto.csg)} · ${escapeHtml(lot.producto.predio)}</strong></div></div>
            <div class="process-progress"><div class="process-progress__labels"><span><strong>${formatNumber(lot.progreso.entregados)}/${formatNumber(lot.progreso.total)}</strong> bins entregados</span><span>${formatNumber(lot.progreso.disponibles)} disponibles</span></div><div class="process-progress__track"><span style="width:${Math.min(100, Number(lot.progreso.porcentaje))}%"></span></div></div>
            ${!complete && can('puede_entregar_fruta_proceso') ? `<div class="process-actions"><button class="primary-button" data-deliver="${escapeHtml(lot.id)}" type="button">+ Registrar viaje</button></div>` : ''}
            <details class="process-history"><summary>Historial de viajes (${lot.entregas.length})</summary><div class="process-history__list">${lot.entregas.length ? lot.entregas.map(renderDelivery).join('') : '<small>Este lote aún no registra entregas.</small>'}</div></details>
        </article>`;
    }).join('');
}
async function load({ silent = false } = {}) {
    const query = new URLSearchParams(new FormData(elements.filters)); query.set('per_page', '200');
    if (!silent) setBusy(true, 'Actualizando fruta a proceso…');
    try {
        const [summary, lots] = await Promise.all([api('/api/materia-prima/fruta-proceso/resumen'), api(`/api/materia-prima/fruta-proceso/lotes?${query}`)]);
        state.summary = summary; state.lots = lots.data || []; renderSummary(); renderLots();
    } catch (error) { if (!silent) toast(error.message, true); }
    finally { if (!silent) setBusy(false); }
}
function openDelivery(lotId) {
    const lot = state.lots.find((item) => item.id === lotId); if (!lot) return;
    state.selected = lot; elements.form.reset(); elements.form.elements.lote_id.value = lot.id;
    elements.form.elements.cantidad_envases.max = String(lot.progreso.disponibles);
    elements.title.textContent = `Entregar ${lot.numero_lote}`;
    elements.description.textContent = `${lot.progreso.disponibles} bins disponibles en ${lot.camara?.codigo}.`;
    elements.summary.innerHTML = `<div><span>LOTE</span><strong>${escapeHtml(lot.numero_lote)}</strong></div><div><span>PROGRESO</span><strong>${formatNumber(lot.progreso.entregados)}/${formatNumber(lot.progreso.total)}</strong></div><div><span>SALDO MÁXIMO</span><strong>${formatNumber(lot.progreso.disponibles)} bins</strong></div>`;
    elements.error.textContent = ''; elements.dialog.showModal();
}
async function submitDelivery() {
    const data = new FormData(elements.form); const quantity = Number(data.get('cantidad_envases'));
    if (!Number.isInteger(quantity) || quantity < 1 || quantity > Number(state.selected?.progreso.disponibles || 0)) {
        elements.error.textContent = `Ingresa una cantidad entre 1 y ${state.selected?.progreso.disponibles || 0}.`; return;
    }
    const payload = {
        operacion_id: uuid(), cantidad_envases: quantity, linea_proceso: String(data.get('linea_proceso') || '').trim(),
        turno: data.get('turno'), numero_orden: String(data.get('numero_orden') || '').trim(), observacion: String(data.get('observacion') || '').trim() || null,
    };
    if (!payload.linea_proceso || !payload.turno || !payload.numero_orden) { elements.error.textContent = 'Completa línea, turno y número de orden.'; return; }
    setBusy(true, 'Registrando viaje…');
    try {
        await api(`/api/materia-prima/fruta-proceso/lotes/${state.selected.id}/entregas`, { method: 'POST', body: JSON.stringify(payload) });
        elements.dialog.close(); toast('Viaje registrado y saldo actualizado.'); await load();
    } catch (error) { elements.error.textContent = error.message; }
    finally { setBusy(false); }
}
async function annulDelivery(deliveryId) {
    const reason = window.prompt('Motivo de la anulación (mínimo 5 caracteres):');
    if (reason === null) return;
    if (reason.trim().length < 5) { toast('Ingresa un motivo de al menos 5 caracteres.', true); return; }
    setBusy(true, 'Anulando entrega…');
    try {
        await api(`/api/materia-prima/fruta-proceso/entregas/${deliveryId}/anular`, { method: 'POST', body: JSON.stringify({ operacion_id: uuid(), motivo: reason.trim() }) });
        toast('Entrega anulada; el saldo del lote fue restituido.'); await load();
    } catch (error) { toast(error.message, true); }
    finally { setBusy(false); }
}

elements.login.addEventListener('submit', async (event) => {
    event.preventDefault(); elements.loginError.textContent = ''; setBusy(true, 'Validando acceso…');
    try {
        const data = new FormData(elements.login);
        const payload = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify({ email: data.get('email'), password: data.get('password') }) });
        persist(payload); if (!showApp()) { clearSession(); throw new ApiError('Tu perfil no tiene acceso a Fruta a proceso.', 403); }
        await load();
    } catch (error) { elements.loginError.textContent = error.message; }
    finally { setBusy(false); }
});
elements.logout.addEventListener('click', async () => { try { await api('/api/acceso-oficina', { method: 'DELETE' }); } catch {} clearSession(); });
elements.reload.addEventListener('click', () => void load());
elements.filters.addEventListener('submit', (event) => { event.preventDefault(); void load(); });
elements.list.addEventListener('click', (event) => {
    const deliver = event.target.closest('[data-deliver]'); if (deliver) openDelivery(deliver.dataset.deliver);
    const annul = event.target.closest('[data-annul]'); if (annul) void annulDelivery(annul.dataset.annul);
});
elements.form.addEventListener('submit', (event) => {
    event.preventDefault(); if (event.submitter?.value === 'cancel') { elements.dialog.close(); return; }
    void submitDelivery();
});

if (state.token && state.identity && showApp()) void load(); else clearSession();
window.setInterval(() => {
    if (state.token && !elements.app.classList.contains('is-hidden') && !elements.dialog.open) {
        void load({ silent: true });
    }
}, 15000);
