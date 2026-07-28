const profileForm = document.getElementById('accessProfileForm');
const profileTable = document.getElementById('accessProfilesTableBody');

if (profileForm && profileTable) {
    const tokenKey = 'estiba_wms_office_token';
    const state = {
        profiles: [],
        macros: [],
        roles: [],
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    async function api(path, options = {}) {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        headers.set('Authorization', `Bearer ${localStorage.getItem(tokenKey) || ''}`);
        if (options.body) headers.set('Content-Type', 'application/json');
        const response = await fetch(path, { ...options, headers });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(Object.values(data?.errors || {}).flat()[0] || data?.message || 'No fue posible guardar el perfil.');
        }

        return data;
    }

    function selectedRole() {
        return state.roles.find((role) => role.clave === profileForm.elements.rol_base.value);
    }

    function selectedModules() {
        return [...profileForm.querySelectorAll('[name="modulos[]"]:checked')]
            .map((input) => input.value);
    }

    function updateMacroState(fieldset) {
        const macro = fieldset.querySelector('[data-macro-toggle]');
        const modules = [...fieldset.querySelectorAll('[name="modulos[]"]:not(:disabled)')];
        const selected = modules.filter((input) => input.checked);
        macro.checked = modules.length > 0 && selected.length === modules.length;
        macro.indeterminate = selected.length > 0 && selected.length < modules.length;
        macro.disabled = modules.length === 0;
    }

    function applyRoleAvailability(selectDefaults = false) {
        const available = new Set(selectedRole()?.modulos_disponibles || []);
        profileForm.querySelectorAll('[name="modulos[]"]').forEach((input) => {
            const enabled = available.has(input.value);
            input.disabled = !enabled;
            input.closest('.access-module-option')?.classList.toggle('is-disabled', !enabled);
            if (!enabled) input.checked = false;
            else if (selectDefaults) input.checked = true;
        });
        profileForm.querySelectorAll('[data-access-macro]').forEach(updateMacroState);
    }

    function renderSelector() {
        const container = document.getElementById('accessModuleSelector');
        container.innerHTML = state.macros.map((macro) => `
            <fieldset class="access-module-group" data-access-macro="${escapeHtml(macro.clave)}">
                <legend>
                    <label>
                        <input data-macro-toggle type="checkbox">
                        <span><strong>${escapeHtml(macro.nombre)}</strong><small>${escapeHtml(macro.descripcion)}</small></span>
                    </label>
                </legend>
                <div class="access-module-options">
                    ${macro.modulos.map((module) => `
                        <label class="access-module-option">
                            <input name="modulos[]" type="checkbox" value="${escapeHtml(module.clave)}">
                            <span><strong>${escapeHtml(module.nombre)}</strong><small>${escapeHtml(module.descripcion)}</small></span>
                        </label>
                    `).join('')}
                </div>
            </fieldset>
        `).join('');

        container.querySelectorAll('[data-macro-toggle]').forEach((input) => {
            input.addEventListener('change', () => {
                const fieldset = input.closest('[data-access-macro]');
                fieldset.querySelectorAll('[name="modulos[]"]:not(:disabled)').forEach((module) => {
                    module.checked = input.checked;
                });
                updateMacroState(fieldset);
            });
        });
        container.querySelectorAll('[name="modulos[]"]').forEach((input) => {
            input.addEventListener('change', () => updateMacroState(input.closest('[data-access-macro]')));
        });
    }

    function renderProfiles() {
        document.getElementById('accessProfilesSummary').textContent =
            `${state.profiles.length} ${state.profiles.length === 1 ? 'configurado' : 'configurados'}`;
        profileTable.innerHTML = state.profiles.map((profile) => `
            <tr>
                <td><strong>${escapeHtml(profile.codigo)} · ${escapeHtml(profile.nombre)}</strong><small>${profile.predeterminado ? 'Perfil inicial · ' : ''}${escapeHtml(profile.descripcion || 'Sin descripción')}</small></td>
                <td><span class="role-badge">${escapeHtml(profile.rol_base_nombre)}</span></td>
                <td><strong>${profile.modulos.length}</strong><small>módulos habilitados</small></td>
                <td>${profile.usuarios_count}</td>
                <td><span class="access-status access-status--${profile.activo ? 'active' : 'inactive'}">${profile.activo ? 'Activo' : 'Inactivo'}</span></td>
                <td>${profile.protegido
                    ? '<span class="access-profile-lock">Protegido</span>'
                    : `<button data-edit-access-profile="${profile.id}" type="button">Editar</button>`}
                </td>
            </tr>
        `).join('') || '<tr class="admin-empty"><td colspan="6">No existen perfiles configurados.</td></tr>';
    }

    function resetForm() {
        profileForm.reset();
        profileForm.elements.id.value = '';
        profileForm.elements.activo.checked = true;
        profileForm.elements.rol_base.disabled = false;
        profileForm.elements.rol_base.value = state.roles[0]?.clave || '';
        document.getElementById('cancelAccessProfileEdit').classList.add('is-hidden');
        document.getElementById('accessProfileError').textContent = '';
        applyRoleAvailability(true);
    }

    function editProfile(profile) {
        profileForm.elements.id.value = profile.id;
        profileForm.elements.codigo.value = profile.codigo;
        profileForm.elements.nombre.value = profile.nombre;
        profileForm.elements.descripcion.value = profile.descripcion || '';
        profileForm.elements.rol_base.value = profile.rol_base;
        profileForm.elements.rol_base.disabled = profile.predeterminado;
        profileForm.elements.activo.checked = profile.activo;
        applyRoleAvailability(false);
        const selected = new Set(profile.modulos);
        profileForm.querySelectorAll('[name="modulos[]"]:not(:disabled)').forEach((input) => {
            input.checked = selected.has(input.value);
        });
        profileForm.querySelectorAll('[data-access-macro]').forEach(updateMacroState);
        document.getElementById('cancelAccessProfileEdit').classList.remove('is-hidden');
        profileForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        profileForm.elements.nombre.focus();
    }

    async function loadProfiles() {
        if (!localStorage.getItem(tokenKey)) return;
        const response = await api('/api/administracion/perfiles-acceso');
        state.profiles = response.data || [];
        state.macros = response.catalogo || [];
        state.roles = response.roles_base || [];
        profileForm.elements.rol_base.innerHTML = state.roles.map((role) =>
            `<option value="${escapeHtml(role.clave)}">${escapeHtml(role.nombre)}</option>`,
        ).join('');
        renderSelector();
        renderProfiles();
        resetForm();
        window.dispatchEvent(new CustomEvent('estiba:access-profiles', {
            detail: { profiles: state.profiles },
        }));
    }

    profileForm.elements.codigo.addEventListener('input', (event) => {
        event.target.value = event.target.value.toUpperCase().replace(/[^A-Z0-9_-]/g, '');
    });
    profileForm.elements.rol_base.addEventListener('change', () => applyRoleAvailability(true));
    document.getElementById('cancelAccessProfileEdit').addEventListener('click', resetForm);

    profileTable.addEventListener('click', (event) => {
        const button = event.target.closest('[data-edit-access-profile]');
        if (!button) return;
        const profile = state.profiles.find((candidate) => candidate.id === button.dataset.editAccessProfile);
        if (profile) editProfile(profile);
    });

    profileForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const error = document.getElementById('accessProfileError');
        error.textContent = '';
        const id = profileForm.elements.id.value;
        const payload = {
            codigo: profileForm.elements.codigo.value,
            nombre: profileForm.elements.nombre.value,
            descripcion: profileForm.elements.descripcion.value,
            rol_base: profileForm.elements.rol_base.value,
            modulos: selectedModules(),
            activo: profileForm.elements.activo.checked,
        };
        if (!payload.modulos.length) {
            error.textContent = 'Selecciona al menos un módulo.';
            return;
        }
        try {
            await api(id ? `/api/administracion/perfiles-acceso/${id}` : '/api/administracion/perfiles-acceso', {
                method: id ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });
            await loadProfiles();
            window.dispatchEvent(new CustomEvent('estiba:access-profile-saved'));
        } catch (exception) {
            error.textContent = exception.message;
        }
    });

    window.addEventListener('estiba:office-session', (event) => {
        if (event.detail?.authenticated) void loadProfiles();
    });
    void loadProfiles();
}
