const byId = (id) => document.getElementById(id);
const keys = { token: 'estiba_wms_office_token', identity: 'estiba_wms_office_identity' };
const state = {
    token: localStorage.getItem(keys.token),
    identity: readJson(keys.identity),
    seasons: [],
    season: null,
    clients: [],
    species: [],
    csg: [],
};
const elements = {
    user: byId('catalogUserName'), initials: byId('catalogInitials'), logout: byId('catalogLogout'),
    selector: byId('catalogSeasonSelector'), reload: byId('catalogReload'),
    seasonForm: byId('catalogSeasonForm'), seasonError: byId('catalogSeasonError'),
    loading: byId('catalogLoading'), loadingText: byId('catalogLoadingText'), toasts: byId('catalogToasts'),
};

const entityConfig = {
    client: { form: 'clientForm', error: 'clientError', path: 'clientes', list: 'clientList' },
    brand: { form: 'brandForm', error: 'brandError', path: 'marcas', list: 'brandList' },
    species: { form: 'speciesForm', error: 'speciesError', path: 'especies', list: 'speciesList' },
    variety: { form: 'varietyForm', error: 'varietyError', path: 'variedades', list: 'varietyList' },
    caliber: { form: 'caliberForm', error: 'caliberError', path: 'calibres', list: 'caliberList' },
    package: { form: 'packageForm', error: 'packageError', path: 'envases', list: 'packageList' },
    csg: { form: 'csgForm', error: 'csgError', path: 'csg', list: 'csgList' },
};

class ApiError extends Error {
    constructor(message, status) { super(message); this.status = status; }
}
function readJson(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; } }
function escapeHtml(value) { return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }
function errorMessage(data, fallback) { return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback; }
function setBusy(active, message = 'Procesando…') { elements.loadingText.textContent = message; elements.loading.classList.toggle('is-hidden', !active); elements.loading.setAttribute('aria-hidden', String(!active)); }
function toast(message, error = false) { const node = document.createElement('div'); node.className = `toast${error ? ' toast--error' : ''}`; node.textContent = message; elements.toasts.append(node); window.setTimeout(() => node.remove(), 4500); }

async function api(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    headers.set('Authorization', `Bearer ${state.token}`);
    if (options.body) headers.set('Content-Type', 'application/json');
    let response;
    try { response = await fetch(path, { ...options, headers }); } catch { throw new ApiError('No fue posible conectar con Laravel.', 0); }
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401) leave();
        throw new ApiError(errorMessage(data, 'No fue posible completar la operación.'), response.status);
    }
    return data;
}

function leave() {
    localStorage.removeItem(keys.token);
    localStorage.removeItem(keys.identity);
    window.location.assign('/oficina/validacion');
}

function verifyAccess() {
    if (!state.token || !state.identity || state.identity.puede_administrar_catalogos_validacion !== true) {
        window.location.replace('/oficina/validacion');
        return false;
    }
    const name = state.identity.nombre || 'Administrador';
    elements.user.textContent = name;
    elements.initials.textContent = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    return true;
}

async function load(seasonId = null) {
    setBusy(true, 'Cargando catálogo…');
    try {
        const suffix = seasonId ? `?temporada_id=${encodeURIComponent(seasonId)}` : '';
        const admin = await api(`/api/administracion/validacion${suffix}`);
        state.seasons = admin.temporadas || [];
        state.season = admin.temporada || null;
        if (state.season) {
            const hierarchy = await api(`/api/administracion/validacion/temporadas/${state.season.id}/catalogo`);
            state.clients = hierarchy.clientes || [];
            state.species = hierarchy.especies || [];
            state.csg = hierarchy.csg || [];
        } else {
            state.clients = []; state.species = []; state.csg = [];
        }
        render();
    } catch (error) {
        toast(error.message, true);
    } finally {
        setBusy(false);
    }
}

function option(value, label) { return `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`; }
function activeClass(item) { return item.activo ? '' : ' is-inactive'; }
function editButton(type, id) { return `<button data-edit-type="${type}" data-edit-id="${id}" type="button">Editar</button>`; }
function row(title, detail, type, item) {
    return `<article class="validation-row${activeClass(item)}"><div><strong>${escapeHtml(title)}</strong><small>${escapeHtml(detail)}</small></div>${editButton(type, item.id)}</article>`;
}

function render() {
    elements.selector.innerHTML = state.seasons.map((season) => option(season.id, `${season.codigo} · ${season.nombre}${season.activa ? ' (activa)' : ''}`)).join('') || '<option value="">Sin temporadas</option>';
    elements.selector.value = state.season?.id || '';

    const copySelect = elements.seasonForm.elements.copiar_desde_temporada_id;
    copySelect.innerHTML = '<option value="">Comenzar vacía</option>' + state.seasons.map((season) => option(season.id, `${season.codigo} · ${season.nombre}`)).join('');

    const clientOptions = '<option value="">Selecciona un cliente</option>' + state.clients.map((item) => option(item.id, item.nombre)).join('');
    byId('brandForm').elements.cliente_validacion_id.innerHTML = clientOptions;

    const speciesOptions = '<option value="">Selecciona una especie</option>' + state.species.map((item) => option(item.id, item.nombre)).join('');
    for (const formId of ['varietyForm', 'caliberForm', 'packageForm']) byId(formId).elements.especie_validacion_id.innerHTML = speciesOptions;

    const varieties = state.species.flatMap((species) => (species.variedades || []).map((item) => ({ ...item, species: species.nombre })));
    byId('csgVarietyOptions').innerHTML = varieties.map((item) => `<label><input name="variedad_ids" type="checkbox" value="${item.id}"><span>${escapeHtml(item.species)} · ${escapeHtml(item.nombre)}</span></label>`).join('') || '<p class="empty-validation">Crea variedades antes de registrar un CSG.</p>';

    byId('clientCount').textContent = String(state.clients.length);
    byId('clientList').innerHTML = state.clients.map((item) => row(item.nombre, item.codigo_externo || 'Sin código externo', 'client', item)).join('') || '<p class="empty-validation">Sin clientes.</p>';

    const brands = state.clients.flatMap((client) => (client.marcas || []).map((item) => ({ ...item, clientId: client.id, client: client.nombre })));
    byId('brandCount').textContent = String(brands.length);
    byId('brandList').innerHTML = brands.map((item) => row(item.nombre, `Cliente: ${item.client}`, 'brand', item)).join('') || '<p class="empty-validation">Sin marcas.</p>';

    byId('speciesCount').textContent = String(state.species.length);
    byId('speciesList').innerHTML = state.species.map((item) => row(item.nombre, `${item.variedades?.length || 0} variedades · ${item.calibres?.length || 0} calibres · ${item.envases?.length || 0} envases`, 'species', item)).join('') || '<p class="empty-validation">Sin especies.</p>';

    renderChildren('variety', 'varietyCount', 'varietyList', 'variedades');
    renderChildren('caliber', 'caliberCount', 'caliberList', 'calibres');
    renderChildren('package', 'packageCount', 'packageList', 'envases');

    byId('csgCount').textContent = String(state.csg.length);
    byId('csgList').innerHTML = state.csg.map((item) => row(item.codigo, `${item.predio || 'Sin predio'} · ${item.variedades?.length || 0} variedades autorizadas`, 'csg', item)).join('') || '<p class="empty-validation">Sin CSG.</p>';
}

function renderChildren(type, countId, listId, relation) {
    const items = state.species.flatMap((species) => (species[relation] || []).map((item) => ({ ...item, speciesId: species.id, species: species.nombre })));
    byId(countId).textContent = String(items.length);
    byId(listId).innerHTML = items.map((item) => row(item.nombre, `Especie: ${item.species}`, type, item)).join('') || '<p class="empty-validation">Sin registros.</p>';
}

function resetForm(form) {
    form.reset();
    form.elements.id.value = '';
    if (form.elements.activo) form.elements.activo.checked = true;
    form.querySelectorAll('input[name="variedad_ids"]').forEach((input) => { input.checked = false; });
    const formId = form.getAttribute('id') || '';\n    const error = byId(formId.replace('Form', 'Error'));
    if (error) error.textContent = '';
}

function itemFor(type, id) {
    if (type === 'client') return state.clients.find((item) => item.id === id);
    if (type === 'brand') return state.clients.flatMap((parent) => (parent.marcas || []).map((item) => ({ ...item, cliente_validacion_id: parent.id }))).find((item) => item.id === id);
    if (type === 'species') return state.species.find((item) => item.id === id);
    if (type === 'csg') return state.csg.find((item) => item.id === id);
    const relation = type === 'variety' ? 'variedades' : type === 'caliber' ? 'calibres' : 'envases';
    return state.species.flatMap((parent) => (parent[relation] || []).map((item) => ({ ...item, especie_validacion_id: parent.id }))).find((item) => item.id === id);
}

function edit(type, id) {
    const config = entityConfig[type];
    const form = byId(config.form);
    const item = itemFor(type, id);
    if (!item) return;
    resetForm(form);
    for (const field of ['id', 'nombre', 'codigo_externo', 'codigo', 'predio', 'cliente_validacion_id', 'especie_validacion_id']) {
        if (form.elements[field]) form.elements[field].value = item[field] ?? '';
    }
    if (form.elements.activo) form.elements.activo.checked = Boolean(item.activo);
    if (type === 'csg') {
        const allowed = new Set((item.variedades || []).map((entry) => entry.id));
        form.querySelectorAll('input[name="variedad_ids"]').forEach((input) => { input.checked = allowed.has(input.value); });
    }
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function save(type) {
    const config = entityConfig[type];
    const form = byId(config.form);
    const errorNode = byId(config.error);
    errorNode.textContent = '';
    if (!state.season) { errorNode.textContent = 'Primero crea o selecciona una temporada.'; return; }

    const data = Object.fromEntries(new FormData(form));
    const id = data.id; delete data.id;
    if (['client', 'species', 'csg'].includes(type)) data.temporada_id = state.season.id;
    data.activo = form.elements.activo.checked;
    if (type === 'csg') data.variedad_ids = [...form.querySelectorAll('input[name="variedad_ids"]:checked')].map((input) => input.value);

    setBusy(true, 'Actualizando catálogo…');
    try {
        await api(`/api/administracion/validacion/${config.path}${id ? `/${id}` : ''}`, {
            method: id ? 'PUT' : 'POST',
            body: JSON.stringify(data),
        });
        resetForm(form);
        await load(state.season.id);
        toast('Catálogo actualizado y publicado para la PDA.');
    } catch (error) {
        errorNode.textContent = error.message;
    } finally {
        setBusy(false);
    }
}

for (const [type, config] of Object.entries(entityConfig)) {
    byId(config.form).addEventListener('submit', (event) => { event.preventDefault(); void save(type); });
}
document.addEventListener('click', (event) => {
    const editTarget = event.target.closest('[data-edit-type]');
    if (editTarget) edit(editTarget.dataset.editType, editTarget.dataset.editId);
    const resetTarget = event.target.closest('[data-reset-form]');
    if (resetTarget) resetForm(byId(resetTarget.dataset.resetForm));
});

elements.seasonForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.seasonError.textContent = '';
    const data = Object.fromEntries(new FormData(elements.seasonForm));
    data.activa = elements.seasonForm.elements.activa.checked;
    if (!data.copiar_desde_temporada_id) delete data.copiar_desde_temporada_id;
    setBusy(true, 'Creando temporada…');
    try {
        const response = await api('/api/administracion/validacion/temporadas', { method: 'POST', body: JSON.stringify(data) });
        elements.seasonForm.reset();
        await load(response.data.id);
        toast(data.copiar_desde_temporada_id ? 'Temporada creada con el catálogo copiado.' : 'Temporada vacía creada.');
    } catch (error) {
        elements.seasonError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.selector.addEventListener('change', () => void load(elements.selector.value));
elements.reload.addEventListener('click', () => void load(state.season?.id));
elements.logout.addEventListener('click', async () => {
    try { await api('/api/acceso-oficina', { method: 'DELETE' }); } catch { /* limpia igualmente */ }
    leave();
});

if (verifyAccess()) void load();
