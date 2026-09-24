const element = (id) => document.getElementById(id);
const els = {
    access: element('officeAccess'), app: element('officeApp'), login: element('officeLoginForm'),
    loginError: element('officeLoginError'), name: element('officeUserName'), role: element('officeUserRole'),
    initials: element('officeInitials'), logout: element('officeLogoutButton'), form: element('defectFilters'),
    seasons: element('defectSeason'), rows: element('defectRows'), count: element('defectCount'),
    page: element('defectPage'), previous: element('defectPrevious'), next: element('defectNext'),
    refresh: element('refreshDefects'), dialog: element('defectDialog'), detail: element('defectDetail'),
    close: element('closeDefect'), busy: element('officeLoading'), busyText: element('officeLoadingText'),
    toasts: element('officeToasts'),
};
const tokenKey = 'estiba_wms_office_token';
const identityKey = 'estiba_wms_office_identity';
let identity;
try { identity = JSON.parse(localStorage.getItem(identityKey) || 'null'); } catch { identity = null; }
const state = { token: localStorage.getItem(tokenKey), identity, page: 1, pages: 1, filters: new URLSearchParams(), busy: false, photos: [], records: [], request: 0 };

function escapeHtml(value) {
    return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}
function alert(message, error = false) {
    const item = document.createElement('p'); item.className = error ? 'toast toast--error' : 'toast';
    item.textContent = message; els.toasts.append(item); setTimeout(() => item.remove(), 5000);
}
function message(data, fallback) { return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback; }
function clearSession() {
    state.token = null; state.identity = null; localStorage.removeItem(tokenKey); localStorage.removeItem(identityKey);
    els.app.classList.add('is-hidden'); els.access.classList.remove('is-hidden'); closeDetail();
}
function busy(on, label = 'Consultando registros…') {
    state.busy = on; els.busyText.textContent = label; els.busy.classList.toggle('is-hidden', !on);
    els.busy.setAttribute('aria-hidden', String(!on));
    els.form.querySelectorAll('button').forEach((button) => { button.disabled = on; });
    document.querySelectorAll('[data-defect-export]').forEach((button) => { button.disabled = on; });
}
async function request(path, options = {}) {
    const headers = new Headers(options.headers || {}); headers.set('Accept', options.binary ? '*/*' : 'application/json');
    if (state.token) headers.set('Authorization', `Bearer ${state.token}`);
    if (options.body) headers.set('Content-Type', 'application/json');
    let result;
    try { result = await fetch(path, { ...options, headers }); }
    catch { throw new Error('No fue posible conectar con el servidor.'); }
    if (!result.ok) {
        const data = await result.json().catch(() => ({}));
        if (result.status === 401 && path !== '/api/acceso-oficina') clearSession();
        throw new Error(message(data, 'No fue posible completar la consulta.'));
    }
    return options.binary ? result : result.status === 204 ? null : result.json();
}
function formatDate(date) {
    if (!date) return '—';
    const value = new Date(date);
    return Number.isNaN(value.getTime()) ? '—' : new Intl.DateTimeFormat('es-CL', { dateStyle: 'short', timeStyle: 'short' }).format(value);
}
const category = { envase_danado: 'Envase dañado', envase_sucio: 'Envase sucio', producto_danado: 'Producto dañado', otro: 'Otro' };
function showApp() {
    els.access.classList.add('is-hidden'); els.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Usuario'; els.name.textContent = name;
    els.role.textContent = String(state.identity?.rol || 'oficina').replaceAll('_', ' ');
    els.initials.textContent = name.split(/\s+/).slice(0, 2).map((part) => part[0] || '').join('').toUpperCase();
}
function readFilters() {
    const params = new URLSearchParams();
    for (const [key, value] of new FormData(els.form)) {
        if (String(value).trim()) params.set(key, String(value).trim());
    }
    return params;
}
function render() {
    els.count.textContent = `${state.total} registro${state.total === 1 ? '' : 's'}`;
    els.page.textContent = `Página ${state.page} de ${state.pages}`;
    els.previous.disabled = state.page <= 1; els.next.disabled = state.page >= state.pages;
    els.rows.innerHTML = state.records.length ? state.records.map((record, index) => `<tr>
        <td>${escapeHtml(formatDate(record.registrado_at))}</td>
        <td><strong>${escapeHtml(record.numero_recepcion || '—')}</strong><small>Guía ${escapeHtml(record.numero_guia_despacho || '—')}</small></td>
        <td>${escapeHtml(record.cliente || '—')}</td>
        <td><strong>${escapeHtml(category[record.categoria] || record.categoria)}</strong><small>${escapeHtml(record.tipo_envase || '—')} · ${escapeHtml(record.cantidad_afectada ?? 'Cantidad sin informar')}</small></td>
        <td>${escapeHtml(record.validador?.nombre || '—')}</td>
        <td><button type="button" data-defect-index="${index}">Ver detalle · ${record.evidencias.length} foto${record.evidencias.length === 1 ? '' : 's'}</button></td>
    </tr>`).join('') : '<tr><td colspan="6">No hay defectos registrados para los filtros seleccionados.</td></tr>';
}
async function loadSeasons() {
    const payload = await request('/api/materia-prima/defectos-recepcion/temporadas');
    els.seasons.replaceChildren();
    if (!payload.data.length) {
        els.seasons.add(new Option('Sin temporada activa', '')); return;
    }
    payload.data.forEach((season) => els.seasons.add(new Option(`${season.codigo} · ${season.nombre}${season.activa ? ' (activa)' : ''}`, season.id)));
    els.seasons.value = payload.data.find((season) => season.activa)?.id || payload.data[0].id;
    state.filters = readFilters();
}
async function loadRecords({ loading = true } = {}) {
    const serial = ++state.request;
    if (loading) busy(true);
    try {
        const params = new URLSearchParams(state.filters); params.set('page', String(state.page));
        const payload = await request(`/api/materia-prima/defectos-recepcion?${params}`);
        if (serial !== state.request) return;
        state.records = payload.data || []; state.total = payload.total || 0;
        state.page = payload.pagina || 1; state.pages = payload.paginas || 1;
        showApp(); render();
    } catch (error) {
        if (serial === state.request) alert(error.message, true);
        throw error;
    } finally { if (loading && serial === state.request) busy(false); }
}
function closeDetail() {
    if (els.dialog.open) els.dialog.close();
    state.photos.forEach((url) => URL.revokeObjectURL(url)); state.photos = [];
    els.detail.replaceChildren();
}
async function openDetail(record) {
    closeDetail(); els.dialog.showModal();
    const facts = [
        ['Fecha', formatDate(record.registrado_at)], ['Recepción', record.numero_recepcion],
        ['Guía de despacho', record.numero_guia_despacho], ['Cliente', record.cliente],
        ['Categoría', category[record.categoria] || record.categoria], ['Envase / afectados', `${record.tipo_envase || '—'} · ${record.cantidad_afectada ?? '—'}`],
        ['Validador', record.validador?.nombre], ['Tablet', record.dispositivo?.codigo],
    ];
    els.detail.innerHTML = `<div class="defect-detail-grid">${facts.map(([key, value]) => `<div><small>${escapeHtml(key)}</small><strong>${escapeHtml(value || '—')}</strong></div>`).join('')}</div>
        <h3>Descripción</h3><p class="defect-description">${escapeHtml(record.descripcion)}</p><h3>Fotografías</h3><div class="defect-photos" id="defectPhotos">Cargando imágenes…</div>`;
    const container = element('defectPhotos');
    if (!record.evidencias.length) { container.textContent = 'Sin fotos registradas.'; return; }
    container.replaceChildren();
    await Promise.all(record.evidencias.map(async (photo) => {
        const figure = document.createElement('figure'); const caption = document.createElement('figcaption');
        caption.textContent = photo.tipo === 'guia' ? 'Guía de despacho' : `Defecto · foto ${photo.posicion + 1}`;
        figure.textContent = 'Cargando imagen…'; container.append(figure);
        try {
            const result = await request(photo.url, { binary: true });
            const blob = await result.blob();
            if (!els.dialog.open || !container.isConnected) return;
            const url = URL.createObjectURL(blob); state.photos.push(url);
            const image = document.createElement('img'); image.src = url; image.alt = caption.textContent; image.loading = 'lazy';
            figure.replaceChildren(image, caption);
        } catch { if (container.isConnected) figure.textContent = 'No se pudo cargar esta fotografía.'; }
    }));
}
async function exportFile(format) {
    busy(true, 'Preparando descarga…');
    try {
        const result = await request(`/api/materia-prima/defectos-recepcion/exportaciones/${format}?${state.filters}`, { binary: true });
        const blob = await result.blob(); const disposition = result.headers.get('Content-Disposition') || '';
        const name = disposition.match(/filename="?([^";]+)"?/i)?.[1] || `defectos-recepcion.${format}`;
        const url = URL.createObjectURL(blob); const link = document.createElement('a');
        link.href = url; link.download = name; document.body.append(link); link.click(); link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (error) { alert(error.message, true); } finally { busy(false); }
}
els.form.addEventListener('submit', (event) => { event.preventDefault(); state.filters = readFilters(); state.page = 1; void loadRecords().catch(() => {}); });
els.refresh.addEventListener('click', () => { void loadRecords().catch(() => {}); });
els.previous.addEventListener('click', () => { state.page -= 1; void loadRecords().catch(() => {}); });
els.next.addEventListener('click', () => { state.page += 1; void loadRecords().catch(() => {}); });
els.rows.addEventListener('click', (event) => {
    const button = event.target.closest('[data-defect-index]'); if (button) void openDetail(state.records[Number(button.dataset.defectIndex)]);
});
els.close.addEventListener('click', closeDetail);
els.dialog.addEventListener('close', closeDetail);
document.querySelectorAll('[data-defect-export]').forEach((button) => button.addEventListener('click', () => void exportFile(button.dataset.defectExport)));
els.login.addEventListener('submit', async (event) => {
    event.preventDefault(); els.loginError.textContent = ''; const form = new FormData(els.login); busy(true, 'Validando acceso…');
    try {
        const payload = await request('/api/acceso-oficina', { method: 'POST', body: JSON.stringify({ email: form.get('email'), password: form.get('password') }) });
        state.token = payload.token; state.identity = payload.usuario;
        localStorage.setItem(tokenKey, payload.token); localStorage.setItem(identityKey, JSON.stringify(payload.usuario));
        await loadSeasons(); await loadRecords({ loading: false });
    } catch (error) { els.loginError.textContent = error.message; } finally { busy(false); }
});
els.logout.addEventListener('click', async () => {
    try { await request('/api/acceso-oficina', { method: 'DELETE' }); } catch { /* La sesión local se cierra igualmente. */ }
    clearSession();
});
if (state.token) {
    busy(true);
    loadSeasons().then(() => loadRecords({ loading: false })).catch((error) => {
        if (state.token) { clearSession(); els.loginError.textContent = error.message; }
    }).finally(() => busy(false));
} else clearSession();
