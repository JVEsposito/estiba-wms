const profileElements = {
    form: document.getElementById('labelProfileForm'),
    error: document.getElementById('labelProfileError'),
    cancel: document.getElementById('cancelLabelProfileEdit'),
    summary: document.getElementById('labelProfilesSummary'),
    table: document.getElementById('labelProfilesTableBody'),
    reload: document.getElementById('reloadAccessesButton'),
};
const profileState = { profiles: [], loading: false };

function profileToken() {
    return localStorage.getItem('estiba_wms_office_token');
}

function profileIdentity() {
    try {
        return JSON.parse(localStorage.getItem('estiba_wms_office_identity') || 'null');
    } catch {
        return null;
    }
}

function profileEscape(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

async function profileApi(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    headers.set('Authorization', `Bearer ${profileToken()}`);
    if (options.body) headers.set('Content-Type', 'application/json');
    const response = await fetch(path, { ...options, headers });
    const data = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(Object.values(data?.errors || {}).flat()[0] || data?.message || 'No fue posible guardar el perfil.');
    }
    return data;
}

function renderProfiles() {
    profileElements.summary.textContent = `${profileState.profiles.length} registrados`;
    profileElements.table.innerHTML = profileState.profiles.map((profile) => `
        <tr>
            <td><strong>${profileEscape(profile.codigo)} · ${profileEscape(profile.nombre)}</strong><small>${profile.predeterminado ? 'Predeterminado' : 'Alternativo'}</small></td>
            <td>${profileEscape(profile.fabricante)}<small>${profileEscape(profile.modelo || 'Modelo compatible')}</small></td>
            <td>${profileEscape(profile.ancho_mm)} × ${profileEscape(profile.alto_mm)} mm<small>${Number(profile.dpi)} dpi · ${profileEscape(profile.orientacion)}</small></td>
            <td>${profileEscape(String(profile.lenguaje).toUpperCase())}</td>
            <td><span class="access-status access-status--${profile.activo ? 'active' : 'inactive'}">${profile.activo ? 'Activo' : 'Inactivo'}</span></td>
            <td><div class="admin-season-actions"><button data-edit-label-profile="${profile.id}" type="button">Editar</button></div></td>
        </tr>
    `).join('') || '<tr class="admin-empty"><td colspan="6">No existen perfiles de etiqueta.</td></tr>';
}

function resetProfileForm() {
    profileElements.form.reset();
    profileElements.form.elements.id.value = '';
    profileElements.form.elements.ancho_mm.value = '100';
    profileElements.form.elements.alto_mm.value = '50';
    profileElements.form.elements.activo.checked = true;
    profileElements.cancel.classList.add('is-hidden');
    profileElements.error.textContent = '';
}

async function loadProfiles() {
    if (profileState.loading || !profileToken() || profileIdentity()?.puede_administrar_accesos !== true) return;
    profileState.loading = true;
    try {
        const response = await profileApi('/api/administracion/etiquetas/materiales/perfiles');
        profileState.profiles = response.data || [];
        renderProfiles();
    } finally {
        profileState.loading = false;
    }
}

profileElements.form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    profileElements.error.textContent = '';
    const data = Object.fromEntries(new FormData(profileElements.form));
    const id = data.id;
    delete data.id;
    data.dpi = Number(data.dpi);
    data.ancho_mm = Number(data.ancho_mm);
    data.alto_mm = Number(data.alto_mm);
    data.activo = profileElements.form.elements.activo.checked;
    data.predeterminado = profileElements.form.elements.predeterminado.checked;
    try {
        await profileApi(
            id
                ? `/api/administracion/etiquetas/materiales/perfiles/${encodeURIComponent(id)}`
                : '/api/administracion/etiquetas/materiales/perfiles',
            { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) },
        );
        resetProfileForm();
        await loadProfiles();
    } catch (error) {
        profileElements.error.textContent = error.message;
    }
});

profileElements.table?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-edit-label-profile]');
    if (!button) return;
    const profile = profileState.profiles.find((candidate) => candidate.id === button.dataset.editLabelProfile);
    if (!profile) return;
    for (const field of ['id', 'codigo', 'nombre', 'fabricante', 'modelo', 'lenguaje', 'dpi', 'ancho_mm', 'alto_mm', 'orientacion']) {
        profileElements.form.elements[field].value = profile[field] ?? '';
    }
    profileElements.form.elements.activo.checked = profile.activo;
    profileElements.form.elements.predeterminado.checked = profile.predeterminado;
    profileElements.cancel.classList.remove('is-hidden');
    profileElements.form.elements.codigo.focus();
});

profileElements.cancel?.addEventListener('click', resetProfileForm);
profileElements.reload?.addEventListener('click', () => void loadProfiles());
window.addEventListener('estiba:office-session', (event) => {
    if (event.detail?.authenticated) void loadProfiles();
    else profileState.profiles = [];
});

if (profileElements.form) void loadProfiles().catch((error) => {
    profileElements.error.textContent = error.message;
});
