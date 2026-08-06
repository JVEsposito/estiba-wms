const byId = (id) => document.getElementById(id);
const elements = {
    access: byId('officeAccess'), app: byId('officeApp'), login: byId('officeLoginForm'),
    loginError: byId('officeLoginError'), logout: byId('officeLogoutButton'),
    userName: byId('officeUserName'), userRole: byId('officeUserRole'), initials: byId('officeInitials'),
    reload: byId('reloadButton'), form: byId('repaForm'), sourceInput: byId('sourceFolioInput'),
    addSource: byId('addSourceButton'), sourceError: byId('sourceError'), repaError: byId('repaError'),
    sourceList: byId('sourceList'), sourceCount: byId('sourceCount'), keptField: byId('keptFolioField'),
    newField: byId('newFolioField'), hardRule: byId('hardRule'), mixWarnings: byId('mixWarnings'),
    previewFolio: byId('previewFolio'), previewType: byId('previewType'),
    previewQuantity: byId('previewQuantity'), previewThermal: byId('previewThermal'),
    specPreview: byId('specPreview'), clear: byId('clearButton'), history: byId('historyList'),
    historyFilter: byId('historyFilter'), loading: byId('officeLoading'),
    loadingText: byId('officeLoadingText'), toasts: byId('officeToasts'),
};
const keys = { token: 'estiba_wms_office_token', identity: 'estiba_wms_office_identity' };
const state = {
    token: localStorage.getItem(keys.token),
    identity: readJson(keys.identity),
    sources: [],
    history: [],
};
const hardFields = [
    ['cliente', 'cliente'], ['especie', 'especie'], ['marca', 'marca'],
    ['condicion_termica', 'estado térmico'],
];
const mixFields = [
    ['variedad', 'Variedad'], ['calibre', 'Calibre'], ['envase', 'Envase'],
    ['categoria', 'Categoría'], ['csg', 'CSG'], ['predio', 'Predio'], ['cuartel', 'Cuartel'],
];

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
function uuid() {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const hex = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
function errorMessage(data, fallback) {
    return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback;
}
function setBusy(active, text = 'Procesando…') {
    elements.loadingText.textContent = text;
    elements.loading.classList.toggle('is-hidden', !active);
    elements.loading.setAttribute('aria-hidden', String(!active));
}
function toast(message, error = false) {
    const item = document.createElement('div');
    item.className = `toast${error ? ' toast--error' : ''}`;
    item.textContent = message;
    elements.toasts.append(item);
    window.setTimeout(() => item.remove(), 5000);
}
async function api(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
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
function persist(payload) {
    state.token = payload.token; state.identity = payload.usuario;
    localStorage.setItem(keys.token, payload.token);
    localStorage.setItem(keys.identity, JSON.stringify(payload.usuario));
}
function clearSession() {
    state.token = null; state.identity = null; state.sources = []; state.history = [];
    localStorage.removeItem(keys.token); localStorage.removeItem(keys.identity);
    elements.app.classList.add('is-hidden'); elements.access.classList.remove('is-hidden');
}
function can(key) {
    return state.identity?.[key] === true || state.identity?.capacidades?.[key] === true;
}
function showApp() {
    if (!can('puede_consultar_validaciones_pallet')) return false;
    elements.access.classList.add('is-hidden'); elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Usuario';
    elements.userName.textContent = name;
    elements.userRole.textContent = String(state.identity?.rol || 'Oficina').replaceAll('_', ' ');
    elements.initials.textContent = name.split(/\s+/).filter(Boolean).slice(0, 2)
        .map((part) => part[0]).join('').toUpperCase();
    elements.form.querySelector('button[type="submit"]').disabled = !can('puede_validar_pallets');
    return true;
}
function formValue(name) { return elements.form.elements[name]?.value ?? ''; }
function normalized(value) { return String(value ?? '').trim().toLocaleUpperCase('es-CL'); }
function common(field) {
    if (!state.sources.length) return '—';
    const values = [...new Set(state.sources.map((source) => normalized(source[field])))];
    return values.length === 1 ? state.sources[0]?.[field] ?? '—' : 'MIX';
}
function hardMismatch() {
    if (state.sources.length < 2) return [];
    return hardFields.filter(([field]) => (
        new Set(state.sources.map((source) => normalized(source[field]))).size > 1
    ));
}
function total() {
    return state.sources.reduce((sum, source) => sum + Number(source.aporte || 0), 0);
}
function resultNumber() {
    if (formValue('estrategia_folio') === 'conservar') {
        return state.sources.find((source) => source.id === formValue('folio_conservado_id'))?.numero_folio || '';
    }
    return normalized(formValue('numero_folio_resultante'));
}
function render() {
    const strategy = formValue('estrategia_folio');
    const type = formValue('tipo_resultado');
    const target = Number(formValue('cantidad_objetivo') || 0);
    elements.keptField.classList.toggle('is-hidden', strategy !== 'conservar');
    elements.newField.classList.toggle('is-hidden', strategy !== 'nuevo');

    const select = elements.form.elements.folio_conservado_id;
    const selected = select.value;
    select.innerHTML = `<option value="">Seleccionar</option>${state.sources.map((source) => (
        `<option value="${escapeHtml(source.id)}">${escapeHtml(source.numero_folio)} · ${source.cantidad_cajas} cajas</option>`
    )).join('')}`;
    if (state.sources.some((source) => source.id === selected)) select.value = selected;
    else if (state.sources.length) select.value = state.sources[0].id;

    elements.sourceCount.textContent = `${state.sources.length} saldo${state.sources.length === 1 ? '' : 's'}`;
    elements.sourceList.innerHTML = state.sources.length ? state.sources.map((source) => (
        `<article class="source-card">
            <div><strong>${escapeHtml(source.numero_folio)}</strong>
                <small>${escapeHtml(source.cliente)} · ${escapeHtml(source.especie)} · ${escapeHtml(source.marca)}</small>
                <small>${escapeHtml(source.calibre)} · CSG ${escapeHtml(source.csg)} · ${escapeHtml(source.condicion_termica)}</small>
            </div>
            <label>DISPONIBLE<span class="source-value">${source.cantidad_cajas}</span></label>
            <label>APORTA<input data-contribution="${escapeHtml(source.id)}" type="number" min="1" max="${source.cantidad_cajas}" value="${source.aporte}"></label>
            <label>QUEDA<span class="source-value">${Math.max(0, source.cantidad_cajas - source.aporte)}</span></label>
            <button data-remove="${escapeHtml(source.id)}" type="button">Quitar</button>
        </article>`
    )).join('') : '<p class="empty-copy">Agrega al menos dos folios tipo saldo.</p>';

    const mismatches = hardMismatch();
    elements.hardRule.classList.toggle('is-invalid', mismatches.length > 0);
    elements.hardRule.querySelector('strong').textContent = mismatches.length
        ? `INCOMPATIBLE: ${mismatches.map(([, label]) => label).join(', ')}`
        : 'Compatibilidad obligatoria';
    const mixes = mixFields.filter(([field]) => state.sources.length > 1 && common(field) === 'MIX');
    elements.mixWarnings.classList.toggle('is-hidden', !mixes.length);
    elements.mixWarnings.innerHTML = mixes.map(([, label]) => (
        `<span>⚠ MIX ${escapeHtml(label.toUpperCase())}</span>`
    )).join('');

    elements.previewFolio.textContent = resultNumber() || 'Sin definir';
    elements.previewType.textContent = type === 'pallet' ? 'Pallet completo' : 'Saldo consolidado';
    elements.previewQuantity.textContent = type === 'pallet' ? `${total()} / ${target || '—'}` : `${total()} cajas`;
    elements.previewThermal.textContent = state.sources.length ? common('condicion_termica') : '—';
    const specs = [
        ['Cliente', common('cliente')], ['Especie', common('especie')], ['Marca', common('marca')],
        ...mixFields.map(([field, label]) => [label, common(field)]),
    ];
    elements.specPreview.innerHTML = state.sources.length ? specs.map(([label, value]) => (
        `<div><span>${escapeHtml(label.toUpperCase())}</span><strong class="${value === 'MIX' ? 'is-mix' : ''}">${escapeHtml(value || '—')}</strong></div>`
    )).join('') : '';
}
async function addSource() {
    const number = normalized(elements.sourceInput.value);
    elements.sourceError.textContent = '';
    if (!number) { elements.sourceError.textContent = 'Escanea o escribe un folio.'; return; }
    if (state.sources.some((source) => source.numero_folio === number)) {
        elements.sourceError.textContent = 'Ese folio ya fue agregado.'; return;
    }
    setBusy(true, 'Buscando saldo…');
    try {
        const source = await api(`/api/validacion/repaletizajes/folios/${encodeURIComponent(number)}`);
        if (!source.existe) throw new ApiError('El folio no existe.');
        if (!source.activo || source.tipo_bulto !== 'saldo' || source.cantidad_cajas < 1) {
            throw new ApiError('El folio no es un saldo activo con cajas disponibles.');
        }
        if (!['pendiente_prefrio', 'prefrio_aprobado'].includes(source.condicion_termica)) {
            throw new ApiError('El folio posee un estado térmico transitorio o retenido.');
        }
        const target = Number(formValue('cantidad_objetivo') || 0);
        const remaining = Math.max(0, target - total());
        const amount = formValue('tipo_resultado') === 'pallet'
            ? Math.min(source.cantidad_cajas, remaining || source.cantidad_cajas)
            : source.cantidad_cajas;
        state.sources.push({ ...source, aporte: amount });
        elements.sourceInput.value = '';
        render();
    } catch (error) { elements.sourceError.textContent = error.message; }
    finally { setBusy(false); }
}
function reset() {
    state.sources = []; elements.form.reset();
    elements.form.elements.cantidad_objetivo.value = 120;
    elements.sourceError.textContent = ''; elements.repaError.textContent = '';
    render();
}
async function submit() {
    elements.repaError.textContent = '';
    if (state.sources.length < 2) { elements.repaError.textContent = 'Agrega al menos dos saldos.'; return; }
    if (hardMismatch().length) {
        elements.repaError.textContent = 'Cliente, especie, marca y estado térmico deben ser idénticos.'; return;
    }
    const type = formValue('tipo_resultado');
    const target = Number(formValue('cantidad_objetivo') || 0);
    if (type === 'pallet' && (!target || total() !== target)) {
        elements.repaError.textContent = `El pallet debe completar exactamente ${target || 'la capacidad indicada'} cajas.`; return;
    }
    if (type === 'saldo' && target && total() >= target) {
        elements.repaError.textContent = 'Un saldo debe quedar bajo la capacidad completa.'; return;
    }
    const strategy = formValue('estrategia_folio');
    const number = resultNumber();
    if (!number) { elements.repaError.textContent = 'Define el folio resultante.'; return; }
    const kept = strategy === 'conservar' ? formValue('folio_conservado_id') : null;
    if (kept) {
        const source = state.sources.find((item) => item.id === kept);
        if (source && source.aporte !== source.cantidad_cajas) {
            elements.repaError.textContent = 'El folio conservado debe aportar todas sus cajas.'; return;
        }
    }
    setBusy(true, 'Confirmando repaletizaje…');
    try {
        const response = await api('/api/validacion/repaletizajes', {
            method: 'POST',
            body: JSON.stringify({
                operacion_id: uuid(), tipo_resultado: type, estrategia_folio: strategy,
                numero_folio_resultante: number, folio_conservado_id: kept,
                cantidad_objetivo: target || null,
                origenes: state.sources.map((source) => ({
                    folio_id: source.id, cantidad_aportada: Number(source.aporte),
                })),
                observacion: String(formValue('observacion') || '').trim() || null,
            }),
        });
        toast(`${response.data.codigo}: ${response.data.folio_resultante.numero_folio} confirmado.`);
        reset(); await loadHistory();
    } catch (error) { elements.repaError.textContent = error.message; }
    finally { setBusy(false); }
}
function renderHistory() {
    elements.history.innerHTML = state.history.length ? state.history.map((repa) => (
        `<article class="repa-history-card${repa.estado === 'anulado' ? ' is-void' : ''}">
            <header><h3>${escapeHtml(repa.codigo)} · ${escapeHtml(repa.folio_resultante?.numero_folio)}</h3><span>${escapeHtml(repa.estado)}</span></header>
            <p>${repa.tipo_resultado === 'pallet' ? 'Pallet completo' : 'Saldo'} · ${repa.cantidad_resultante} cajas · ${escapeHtml(repa.condicion_termica)}</p>
            ${repa.advertencias?.length ? `<p>⚠ ${repa.advertencias.map((item) => escapeHtml(item.campo)).join(' · ')}</p>` : ''}
            <details><summary>Composición (${repa.origenes.length})</summary>
                ${repa.origenes.map((origin) => `<div class="repa-origin"><span>${escapeHtml(origin.folio.numero_folio)}</span><strong>${origin.cajas_aportadas} cajas</strong></div>`).join('')}
            </details>
            ${can('puede_rechazar_pallets') && repa.puede_anular ? `<button data-annul="${escapeHtml(repa.id)}" type="button">Anular repa</button>` : ''}
        </article>`
    )).join('') : '<p class="empty-copy">No existen repaletizajes para esta selección.</p>';
}
async function loadHistory() {
    const query = new URLSearchParams({ per_page: '50' });
    if (elements.historyFilter.value.trim()) query.set('folio', elements.historyFilter.value.trim());
    const response = await api(`/api/validacion/repaletizajes?${query}`);
    state.history = response.data || []; renderHistory();
}
async function annul(id) {
    const reason = window.prompt('Motivo de anulación (mínimo 5 caracteres):');
    if (reason === null) return;
    if (reason.trim().length < 5) { toast('El motivo es demasiado breve.', true); return; }
    setBusy(true, 'Anulando repa…');
    try {
        await api(`/api/validacion/repaletizajes/${id}/anular`, {
            method: 'POST', body: JSON.stringify({ operacion_id: uuid(), motivo: reason.trim() }),
        });
        toast('Repaletizaje anulado y cantidades restauradas.'); await loadHistory();
    } catch (error) { toast(error.message, true); }
    finally { setBusy(false); }
}

elements.login.addEventListener('submit', async (event) => {
    event.preventDefault(); elements.loginError.textContent = ''; setBusy(true, 'Validando acceso…');
    try {
        const data = new FormData(elements.login);
        const payload = await api('/api/acceso-oficina', {
            method: 'POST', body: JSON.stringify({ email: data.get('email'), password: data.get('password') }),
        });
        persist(payload);
        if (!showApp()) { clearSession(); throw new ApiError('Tu perfil no tiene acceso a repaletizajes.', 403); }
        await loadHistory();
    } catch (error) { elements.loginError.textContent = error.message; }
    finally { setBusy(false); }
});
elements.logout.addEventListener('click', async () => {
    try { await api('/api/acceso-oficina', { method: 'DELETE' }); } catch {}
    clearSession();
});
elements.addSource.addEventListener('click', () => void addSource());
elements.sourceInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') { event.preventDefault(); void addSource(); }
});
elements.form.addEventListener('change', render);
elements.form.addEventListener('input', (event) => {
    if (!event.target.matches('[data-contribution]')) { render(); return; }
    const source = state.sources.find((item) => item.id === event.target.dataset.contribution);
    if (source) source.aporte = Math.min(source.cantidad_cajas, Math.max(1, Number(event.target.value || 1)));
    render();
});
elements.sourceList.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-remove]');
    if (remove) { state.sources = state.sources.filter((source) => source.id !== remove.dataset.remove); render(); }
});
elements.form.addEventListener('submit', (event) => { event.preventDefault(); void submit(); });
elements.clear.addEventListener('click', reset);
elements.reload.addEventListener('click', () => void loadHistory());
elements.historyFilter.addEventListener('change', () => void loadHistory());
elements.history.addEventListener('click', (event) => {
    const button = event.target.closest('[data-annul]'); if (button) void annul(button.dataset.annul);
});

render();
if (state.token && state.identity && showApp()) void loadHistory(); else clearSession();
