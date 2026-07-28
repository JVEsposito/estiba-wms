const form = document.getElementById('createUserForm');
const tableBody = document.getElementById('usersTableBody');

if (form && tableBody) {
    const tokenKey = 'estiba_wms_office_token';
    const identityKey = 'estiba_wms_office_identity';
    let users = [];
    let profiles = [];
    let decorating = false;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    function readIdentity() {
        try {
            return JSON.parse(localStorage.getItem(identityKey) || 'null');
        } catch {
            return null;
        }
    }

    async function api(path, options = {}) {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        headers.set('Authorization', `Bearer ${localStorage.getItem(tokenKey) || ''}`);
        if (options.body) headers.set('Content-Type', 'application/json');
        const response = await fetch(path, { ...options, headers });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(Object.values(data?.errors || {}).flat()[0] || data?.message || 'No fue posible guardar el usuario.');
        }
        return data;
    }

    function ensureFields() {
        if (!form.elements.id) {
            const id = document.createElement('input');
            id.type = 'hidden';
            id.name = 'id';
            form.prepend(id);
        }

        if (!form.elements.activo) {
            const active = document.createElement('label');
            active.className = 'admin-check';
            active.innerHTML = '<input name="activo" type="checkbox" checked><span>Usuario activo y habilitado para iniciar sesión</span>';
            form.querySelector('.admin-form__grid')?.after(active);
        }

        if (!document.getElementById('userPermissionsHint')) {
            const hint = document.createElement('p');
            hint.className = 'admin-form__hint';
            hint.id = 'userPermissionsHint';
            form.querySelector('.admin-form__hint')?.after(hint);
        }

        const actions = form.querySelector('.admin-form__actions');
        if (actions && !document.getElementById('cancelUserEdit')) {
            const cancel = document.createElement('button');
            cancel.className = 'secondary-button is-hidden';
            cancel.id = 'cancelUserEdit';
            cancel.type = 'button';
            cancel.textContent = 'Nuevo usuario';
            actions.prepend(cancel);
            cancel.addEventListener('click', resetForm);
        }

        form.elements.perfil_acceso_id?.addEventListener('change', updatePermissionsHint);
        updatePermissionsHint();
    }

    function updatePermissionsHint() {
        const profile = profiles.find((candidate) => candidate.id === form.elements.perfil_acceso_id?.value);
        const hint = document.getElementById('userPermissionsHint');
        if (hint) {
            hint.textContent = profile
                ? `Perfil: ${profile.nombre} · nivel ${profile.rol_base_nombre} · ${profile.modulos.length} oficinas PC · ${(profile.modulos_tablet || []).length} módulos PDA/tablet.`
                : 'Selecciona el perfil que definirá los módulos y el nivel operacional del usuario.';
        }
    }

    function resetForm() {
        form.reset();
        form.elements.id.value = '';
        form.elements.activo.checked = true;
        form.elements.perfil_acceso_id.value = profiles.find((profile) => profile.activo)?.id || '';
        form.elements.password.required = true;
        form.elements.password_confirmation.required = true;
        form.elements.password.previousElementSibling.textContent = 'Contraseña temporal *';
        form.elements.password_confirmation.previousElementSibling.textContent = 'Confirmar contraseña *';
        document.getElementById('cancelUserEdit')?.classList.add('is-hidden');
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.innerHTML = 'Crear usuario <span>→</span>';
        updatePermissionsHint();
    }

    function openEdit(user) {
        form.elements.id.value = user.id;
        form.elements.nombre.value = user.nombre;
        form.elements.email.value = user.email;
        form.elements.perfil_acceso_id.value = user.perfil?.id || '';
        form.elements.activo.checked = user.activo;
        form.elements.password.value = '';
        form.elements.password_confirmation.value = '';
        form.elements.password.required = false;
        form.elements.password_confirmation.required = false;
        form.elements.password.previousElementSibling.textContent = 'Nueva contraseña';
        form.elements.password_confirmation.previousElementSibling.textContent = 'Confirmar nueva contraseña';
        document.getElementById('cancelUserEdit')?.classList.remove('is-hidden');
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.innerHTML = 'Guardar cambios <span>→</span>';
        updatePermissionsHint();
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        form.elements.nombre.focus();
    }

    async function refreshUsers() {
        if (!localStorage.getItem(tokenKey)) return;
        try {
            const response = await api('/api/administracion/accesos');
            users = response.usuarios || [];
            decorateRows();
        } catch {
            // La pantalla principal administra el error de sesión o conectividad.
        }
    }

    async function refreshProfiles() {
        if (!localStorage.getItem(tokenKey)) return;
        try {
            const response = await api('/api/administracion/perfiles-acceso');
            setProfiles(response.data || []);
        } catch {
            // La pantalla principal administra el error de sesión o conectividad.
        }
    }

    function setProfiles(items) {
        profiles = items;
        const selected = form.elements.perfil_acceso_id.value;
        form.elements.perfil_acceso_id.innerHTML = profiles
            .filter((profile) => profile.activo || profile.id === selected)
            .map((profile) => `<option value="${escapeHtml(profile.id)}">${escapeHtml(profile.codigo)} · ${escapeHtml(profile.nombre)}</option>`)
            .join('');
        form.elements.perfil_acceso_id.value = profiles.some((profile) => profile.id === selected)
            ? selected
            : profiles.find((profile) => profile.activo)?.id || '';
        updatePermissionsHint();
    }

    function decorateRows() {
        if (decorating) return;
        decorating = true;
        try {
            const header = tableBody.closest('table')?.querySelector('thead tr');
            if (header && !header.querySelector('[data-user-actions-header]')) {
                const th = document.createElement('th');
                th.dataset.userActionsHeader = 'true';
                th.textContent = 'Acciones';
                header.append(th);
            }

            const rows = [...tableBody.querySelectorAll('tr')];
            if (rows.length === 1 && rows[0].classList.contains('admin-empty')) {
                rows[0].querySelector('td')?.setAttribute('colspan', '4');
                return;
            }

            rows.forEach((row) => {
                if (row.querySelector('[data-user-actions]')) return;
                const email = row.querySelector('td:first-child small')?.textContent?.trim().toLowerCase();
                const user = users.find((candidate) => candidate.email.toLowerCase() === email);
                if (!user) return;
                const cell = document.createElement('td');
                cell.dataset.userActions = 'true';
                cell.innerHTML = `<div class="admin-season-actions"><button data-edit-user="${user.id}" type="button">Editar</button></div>`;
                row.append(cell);
            });
        } finally {
            decorating = false;
        }
    }

    tableBody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-edit-user]');
        if (!button) return;
        const user = users.find((candidate) => String(candidate.id) === String(button.dataset.editUser));
        if (user) openEdit(user);
    });

    new MutationObserver(() => decorateRows()).observe(tableBody, { childList: true });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        const error = document.getElementById('createUserError');
        if (error) error.textContent = '';

        const data = Object.fromEntries(new FormData(form));
        const id = data.id;
        delete data.id;
        data.activo = form.elements.activo.checked;

        if (!id && String(data.password || '').length < 10) {
            if (error) error.textContent = 'La contraseña debe tener al menos 10 caracteres.';
            return;
        }
        if (data.password && (!/\p{L}/u.test(data.password) || !/\p{N}/u.test(data.password))) {
            if (error) error.textContent = 'La contraseña debe contener al menos una letra y un número.';
            return;
        }
        if (data.password !== data.password_confirmation) {
            if (error) error.textContent = 'La confirmación de la contraseña no coincide.';
            return;
        }
        if (id && !data.password) {
            delete data.password;
            delete data.password_confirmation;
        }

        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.disabled = true;
        try {
            const response = await api(id ? `/api/administracion/usuarios/${id}` : '/api/administracion/usuarios', {
                method: id ? 'PUT' : 'POST',
                body: JSON.stringify(data),
            });
            if (response.sesion_actual_invalidada) {
                localStorage.removeItem(tokenKey);
                localStorage.removeItem(identityKey);
            } else if (id) {
                const identity = readIdentity();
                if (identity && String(identity.id) === String(id)) {
                    localStorage.setItem(identityKey, JSON.stringify({
                        ...identity,
                        nombre: response.usuario.nombre,
                        email: response.usuario.email,
                        rol: response.usuario.rol,
                        perfil_acceso: response.usuario.perfil,
                        activo: response.usuario.activo,
                    }));
                }
            }
            window.location.reload();
        } catch (exception) {
            if (error) error.textContent = exception.message;
        } finally {
            if (submit) submit.disabled = false;
        }
    }, { capture: true });

    ensureFields();
    window.addEventListener('estiba:access-profiles', (event) => {
        setProfiles(event.detail?.profiles || []);
        void refreshUsers();
    });
    void Promise.all([refreshProfiles(), refreshUsers()]);
}
