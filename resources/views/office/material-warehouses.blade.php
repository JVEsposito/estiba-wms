<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estiba WMS · Custodia de materiales</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/office.css', 'resources/css/office-materials.css'])
    @endif
    <style>
        [hidden], .is-hidden { display: none !important; }
        .custody-shell { max-width: 1500px; margin: 0 auto; padding: 1.25rem; }
        .custody-heading, .custody-card { background: var(--surface, #fff); border: 1px solid var(--border, #d8dee8); border-radius: 14px; padding: 1rem; }
        .custody-heading { display: flex; justify-content: space-between; gap: 1rem; align-items: center; margin-bottom: 1rem; }
        .custody-heading h1, .custody-card h2 { margin: 0; }
        .custody-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1rem; }
        .custody-metrics article { background: var(--surface, #fff); border: 1px solid var(--border, #d8dee8); border-radius: 12px; padding: .9rem; }
        .custody-metrics span { display: block; font-size: .72rem; font-weight: 700; color: var(--muted, #64748b); }
        .custody-metrics strong { display: block; margin-top: .35rem; font-size: 1.5rem; }
        .custody-tabs { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .custody-tabs button { border: 1px solid var(--border, #cbd5e1); background: #fff; border-radius: 999px; padding: .55rem .9rem; cursor: pointer; }
        .custody-tabs button.is-active { background: #173f72; color: #fff; border-color: #173f72; }
        .custody-table-wrap { overflow: auto; }
        .custody-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .custody-table th, .custody-table td { border-bottom: 1px solid var(--border, #e2e8f0); padding: .65rem; text-align: left; vertical-align: top; }
        .custody-table th { font-size: .74rem; text-transform: uppercase; color: var(--muted, #64748b); }
        .custody-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(360px, .55fr); gap: 1rem; margin-top: 1rem; }
        .custody-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
        .custody-form label { display: grid; gap: .25rem; font-size: .82rem; font-weight: 600; }
        .custody-form input, .custody-form select, .custody-form textarea { width: 100%; padding: .65rem; border: 1px solid var(--border, #cbd5e1); border-radius: 8px; background: #fff; }
        .custody-form .wide { grid-column: 1 / -1; }
        .custody-actions { display: flex; justify-content: flex-end; gap: .5rem; grid-column: 1 / -1; }
        .custody-error { color: #b42318; min-height: 1.25rem; grid-column: 1 / -1; }
        .custody-kardex { max-height: 640px; overflow: auto; }
        .custody-event { padding: .7rem 0; border-bottom: 1px solid var(--border, #e2e8f0); }
        .custody-event strong, .custody-event small { display: block; }
        .custody-event small { color: var(--muted, #64748b); margin-top: .2rem; }
        .custody-login { max-width: 520px; margin: 8vh auto; padding: 1.25rem; background: #fff; border: 1px solid #d8dee8; border-radius: 14px; }
        .custody-login form { display: grid; gap: .75rem; }
        .custody-login input { width: 100%; padding: .7rem; border: 1px solid #cbd5e1; border-radius: 8px; }
        @media (max-width: 900px) {
            .custody-grid, .custody-metrics { grid-template-columns: 1fr; }
            .custody-heading { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
<section class="custody-login" id="custodyLogin">
    <p class="eyebrow">ESTIBA WMS · MATERIALES</p>
    <h1>Custodia distribuida</h1>
    <p>Ingresa con una cuenta autorizada para consultar existencias y registrar consumos o devoluciones.</p>
    <form id="custodyLoginForm">
        <label>Correo electrónico<input name="email" type="email" autocomplete="username" required></label>
        <label>Contraseña<input name="password" type="password" autocomplete="current-password" required></label>
        <p class="custody-error" id="custodyLoginError"></p>
        <button class="primary-button" type="submit">Ingresar</button>
    </form>
</section>

<main id="custodyApp" class="is-hidden">
    <x-office.navigation domain="materiales" office="custodia" context="CUSTODIA DE MATERIALES" icon="▦" />

    <div class="custody-shell">
        <header class="custody-heading">
            <div>
                <p class="eyebrow">EXISTENCIAS DE MATERIALES</p>
                <h1>Bodega, centros de costo y total empresa</h1>
                <p>Una entrega cambia la custodia; solo un consumo o ajuste disminuye la existencia total.</p>
            </div>
            <button class="secondary-button" id="custodyReload" type="button">↻ Actualizar</button>
        </header>

        <section class="custody-metrics">
            <article><span>FOLIOS VIGENTES</span><strong id="custodyFolioCount">0</strong></article>
            <article><span>ALMACENES CON SALDO</span><strong id="custodyWarehouseCount">0</strong></article>
            <article><span>ÍTEMS CON EXISTENCIA</span><strong id="custodyItemCount">0</strong></article>
        </section>

        <div class="custody-tabs" role="tablist">
            <button class="is-active" data-tab="bodega" type="button">Existencia en Bodega</button>
            <button data-tab="centros_costo" type="button">Existencia en centros de costo</button>
            <button data-tab="total_empresa" type="button">Existencia total empresa</button>
        </div>

        <section class="custody-card">
            <div class="custody-table-wrap">
                <table class="custody-table">
                    <thead id="custodyTableHead"></thead>
                    <tbody id="custodyTableBody"></tbody>
                </table>
            </div>
        </section>

        <div class="custody-grid">
            <section class="custody-card" id="custodyMovementPanel">
                <p class="eyebrow">OPERACIÓN POSTERIOR A LA ENTREGA</p>
                <h2>Registrar movimiento</h2>
                <p>Consumir descuenta inventario; devolver y transferir conservan el total; ajustar exige supervisión.</p>
                <form class="custody-form" id="custodyMovementForm">
                    <label>Acción
                        <select name="tipo" required>
                            <option value="consumo">Consumir</option>
                            <option value="devolucion">Devolver a Bodega</option>
                            <option value="transferencia">Transferir entre almacenes</option>
                            <option value="ajuste">Ajustar diferencia</option>
                        </select>
                    </label>
                    <label>Folio
                        <select name="folio_id" required></select>
                    </label>
                    <label>Almacén origen
                        <select name="almacen_origen_id"></select>
                    </label>
                    <label>Almacén destino
                        <select name="almacen_destino_id"></select>
                    </label>
                    <label>Cantidad
                        <input name="cantidad" type="number" step="0.001" required>
                    </label>
                    <label>Documento relacionado
                        <input name="documento_relacionado" maxlength="150" placeholder="Orden, turno o referencia">
                    </label>
                    <label>Cámara destino
                        <select name="camara_destino_id"></select>
                    </label>
                    <label>Posición destino
                        <select name="posicion_destino_id"></select>
                    </label>
                    <label class="wide">Motivo / operación
                        <textarea name="motivo" rows="2" maxlength="1000" placeholder="Producción turno noche, merma, devolución de sobrante…"></textarea>
                    </label>
                    <label class="wide">Justificación de excepción FIFO
                        <textarea name="motivo_excepcion_fifo" rows="2" maxlength="1000" placeholder="Solo cuando no se usa el lote más antiguo del almacén"></textarea>
                    </label>
                    <p class="custody-error" id="custodyMovementError"></p>
                    <div class="custody-actions">
                        <button class="primary-button" type="submit">Registrar movimiento</button>
                    </div>
                </form>
            </section>

            <aside class="custody-card" id="custodyKardexPanel">
                <p class="eyebrow">TRAZABILIDAD</p>
                <h2>Kardex por almacén</h2>
                <div class="custody-kardex" id="custodyKardex"></div>
            </aside>
        </div>
    </div>
</main>

<script>
(() => {
    const tokenKey = 'estiba_wms_office_token';
    const identityKey = 'estiba_wms_office_identity';
    const state = { token: localStorage.getItem(tokenKey), identity: null, data: null, kardex: [], tab: 'bodega' };
    const $ = (id) => document.getElementById(id);
    const escapeHtml = (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    const qty = (value) => new Intl.NumberFormat('es-CL', { maximumFractionDigits: 3 }).format(Number(value || 0));
    const dateTime = (value) => value ? new Intl.DateTimeFormat('es-CL', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';
    const uuid = () => crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16);
    });

    try { state.identity = JSON.parse(localStorage.getItem(identityKey) || 'null'); } catch {}

    function capabilities() {
        return {
            ...(state.identity?.capacidades || {}),
            ...(state.identity || {}),
        };
    }

    function can(permission) {
        if (state.identity?.rol === 'administrador') return true;

        return capabilities()[permission] === true;
    }

    async function api(path, options = {}) {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        if (state.token) headers.set('Authorization', `Bearer ${state.token}`);
        if (options.body) headers.set('Content-Type', 'application/json');
        const response = await fetch(path, { ...options, headers });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'No fue posible completar la operación.');
        return data;
    }

    function showApp() {
        $('custodyLogin').classList.add('is-hidden');
        $('custodyApp').classList.remove('is-hidden');
        const name = state.identity?.nombre || state.identity?.name || 'Usuario';
        const userName = $('officeUserName'); if (userName) userName.textContent = name;
        const role = $('officeUserRole'); if (role) role.textContent = String(state.identity?.rol || 'Oficina').replaceAll('_', ' ');
        const initials = $('officeInitials'); if (initials) initials.textContent = name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
        const logout = $('officeLogoutButton');
        if (logout) logout.onclick = () => { localStorage.removeItem(tokenKey); localStorage.removeItem(identityKey); location.reload(); };
    }

    async function load() {
        const puedeConsultarKardex = can('puede_consultar_kardex_materiales');
        const [data, movements] = await Promise.all([
            api('/api/materiales/almacenes'),
            puedeConsultarKardex
                ? api('/api/materiales/almacenes/movimientos?limite=100')
                : Promise.resolve({ data: [] }),
        ]);
        state.data = data;
        state.kardex = movements.data || [];
        render();
    }

    function render() {
        const puedeGestionar = can('puede_gestionar_despachos_materiales');
        const puedeAjustar = can('puede_gestionar_bloqueos_materiales');
        const puedeConsultarKardex = can('puede_consultar_kardex_materiales');

        $('custodyMovementPanel').classList.toggle(
            'is-hidden',
            !puedeGestionar && !puedeAjustar,
        );
        $('custodyKardexPanel').classList.toggle('is-hidden', !puedeConsultarKardex);
        $('custodyFolioCount').textContent = state.data.resumen.folios;
        $('custodyWarehouseCount').textContent = state.data.resumen.almacenes;
        $('custodyItemCount').textContent = state.data.resumen.items;
        configurarAcciones(puedeGestionar, puedeAjustar);
        renderTable();
        if (puedeGestionar || puedeAjustar) renderSelectors();
        if (puedeConsultarKardex) renderKardex();
    }

    function configurarAcciones(puedeGestionar, puedeAjustar) {
        const select = $('custodyMovementForm').elements.tipo;

        [...select.options].forEach((option) => {
            const permitida = option.value === 'ajuste'
                ? puedeAjustar
                : puedeGestionar;
            option.disabled = !permitida;
            option.hidden = !permitida;
        });

        if (select.selectedOptions[0]?.disabled) {
            const primeraPermitida = [...select.options].find((option) => !option.disabled);
            if (primeraPermitida) select.value = primeraPermitida.value;
        }
    }

    function renderTable() {
        const rows = state.data.perspectivas[state.tab] || [];
        if (state.tab === 'total_empresa') {
            $('custodyTableHead').innerHTML = '<tr><th>Cliente</th><th>Ítem</th><th>En Bodega</th><th>En centros de costo</th><th>Total empresa</th><th>Folios</th></tr>';
            $('custodyTableBody').innerHTML = rows.map((row) => `<tr>
                <td>${escapeHtml(row.cliente.codigo)} · ${escapeHtml(row.cliente.nombre)}</td>
                <td><strong>${escapeHtml(row.item.codigo)}</strong><br>${escapeHtml(row.item.nombre)}</td>
                <td>${qty(row.en_bodega)} ${escapeHtml(row.unidad_medida)}</td>
                <td>${qty(row.en_centros_costo)} ${escapeHtml(row.unidad_medida)}</td>
                <td><strong>${qty(row.total_empresa)} ${escapeHtml(row.unidad_medida)}</strong></td>
                <td>${row.folios}</td>
            </tr>`).join('') || '<tr><td colspan="6">Sin existencia vigente.</td></tr>';
            return;
        }

        const virtual = state.tab === 'centros_costo';
        $('custodyTableHead').innerHTML = `<tr><th>${virtual ? 'Centro de costo / almacén' : 'Almacén'}</th><th>Cliente</th><th>Ítem</th><th>Folio / lote</th><th>Cantidad</th><th>Reservada</th><th>Disponible</th>${virtual ? '' : '<th>Cámara / posición</th>'}</tr>`;
        $('custodyTableBody').innerHTML = rows.map((row) => `<tr>
            <td><strong>${escapeHtml(row.almacen.nombre)}</strong><br>${escapeHtml(row.almacen.centro_costo || '—')}</td>
            <td>${escapeHtml(row.cliente.codigo)} · ${escapeHtml(row.cliente.nombre)}</td>
            <td><strong>${escapeHtml(row.item.codigo)}</strong><br>${escapeHtml(row.item.nombre)}</td>
            <td>${escapeHtml(row.numero_folio)}<br>${escapeHtml(row.lote || 'Sin lote')}</td>
            <td>${qty(row.cantidad_actual)} ${escapeHtml(row.unidad_medida)}</td>
            <td>${qty(row.cantidad_reservada)}</td>
            <td><strong>${qty(row.cantidad_disponible)}</strong></td>
            ${virtual ? '' : `<td>${escapeHtml(row.camara?.codigo || 'Pendiente')}<br>${escapeHtml(row.posicion?.etiqueta || 'Sin posición')}</td>`}
        </tr>`).join('') || `<tr><td colspan="${virtual ? 7 : 8}">Sin existencia vigente.</td></tr>`;
    }

    function renderSelectors() {
        const rows = [...state.data.perspectivas.bodega, ...state.data.perspectivas.centros_costo];
        const folios = [...new Map(rows.map((row) => [row.folio_id, row])).values()];
        const warehouses = state.data.almacenes || [];
        const form = $('custodyMovementForm');
        form.elements.folio_id.innerHTML = folios.map((row) => `<option value="${row.folio_id}">${escapeHtml(row.numero_folio)} · ${escapeHtml(row.item.codigo)} · ${escapeHtml(row.item.nombre)}</option>`).join('');
        const warehouseOptions = '<option value="">Seleccionar</option>' + warehouses.map((row) => `<option value="${row.id}">${escapeHtml(row.codigo || '')} · ${escapeHtml(row.nombre)}</option>`).join('');
        form.elements.almacen_origen_id.innerHTML = warehouseOptions;
        form.elements.almacen_destino_id.innerHTML = warehouseOptions;
        form.elements.camara_destino_id.innerHTML = '<option value="">No aplica / conservar</option>' + (state.data.camaras || []).map((row) => `<option value="${row.id}">${escapeHtml(row.codigo)} · ${escapeHtml(row.nombre)}</option>`).join('');
        renderPositions();
        inferOrigin();
    }

    function inferOrigin() {
        const form = $('custodyMovementForm');
        const folioId = form.elements.folio_id.value;
        const rows = [...state.data.perspectivas.centros_costo, ...state.data.perspectivas.bodega];
        const row = rows.find((candidate) => candidate.folio_id === folioId && Number(candidate.cantidad_disponible) > 0);
        if (row) form.elements.almacen_origen_id.value = row.almacen.id;
    }

    function renderPositions() {
        const form = $('custodyMovementForm');
        const camera = (state.data?.camaras || []).find((row) => row.id === form.elements.camara_destino_id.value);
        form.elements.posicion_destino_id.innerHTML = '<option value="">Sin posición exacta</option>' + (camera?.posiciones || []).map((row) => `<option value="${row.id}">${escapeHtml(row.etiqueta)}</option>`).join('');
    }

    function renderKardex() {
        $('custodyKardex').innerHTML = state.kardex.map((row) => `<article class="custody-event">
            <strong>${escapeHtml(row.tipo.replaceAll('_', ' '))} · ${escapeHtml(row.folio.numero_folio)} · ${qty(row.cantidad)}</strong>
            <span>${escapeHtml(row.almacen_origen?.nombre || 'Empresa')} → ${escapeHtml(row.almacen_destino?.nombre || 'Consumo / ajuste')}</span>
            <small>${escapeHtml(row.item.codigo)} · ${escapeHtml(row.centro_costo || 'Sin centro de costo')} · ${dateTime(row.ocurrido_at)}</small>
            <small>${escapeHtml(row.motivo || '')}</small>
        </article>`).join('') || '<p>Sin movimientos distribuidos registrados.</p>';
    }

    $('custodyLoginForm').addEventListener('submit', async (event) => {
        event.preventDefault(); $('custodyLoginError').textContent = '';
        const form = new FormData(event.currentTarget);
        try {
            const response = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify({ email: form.get('email'), password: form.get('password') }) });
            state.token = response.token; state.identity = response.usuario;
            localStorage.setItem(tokenKey, response.token); localStorage.setItem(identityKey, JSON.stringify(response.usuario));
            showApp(); await load();
        } catch (error) { $('custodyLoginError').textContent = error.message; }
    });

    document.querySelectorAll('[data-tab]').forEach((button) => button.addEventListener('click', () => {
        state.tab = button.dataset.tab;
        document.querySelectorAll('[data-tab]').forEach((candidate) => candidate.classList.toggle('is-active', candidate === button));
        renderTable();
    }));
    $('custodyReload').addEventListener('click', () => load().catch((error) => alert(error.message)));
    $('custodyMovementForm').elements.folio_id.addEventListener('change', inferOrigin);
    $('custodyMovementForm').elements.camara_destino_id.addEventListener('change', renderPositions);
    $('custodyMovementForm').addEventListener('submit', async (event) => {
        event.preventDefault(); $('custodyMovementError').textContent = '';
        const movementForm = event.currentTarget;
        const submitButton = event.submitter ?? movementForm.querySelector('button[type="submit"]');
        const form = new FormData(movementForm);
        const payload = Object.fromEntries(form.entries());
        payload.operacion_id = uuid();
        Object.keys(payload).forEach((key) => { if (payload[key] === '') delete payload[key]; });
        if (submitButton) submitButton.disabled = true;
        try {
            await api('/api/materiales/almacenes/movimientos', { method: 'POST', body: JSON.stringify(payload) });
            movementForm.elements.cantidad.value = '';
            movementForm.elements.motivo.value = '';
            await load();
        } catch (error) { $('custodyMovementError').textContent = error.message; }
        finally { if (submitButton) submitButton.disabled = false; }
    });

    if (state.token) {
        showApp();
        load().catch(() => { localStorage.removeItem(tokenKey); localStorage.removeItem(identityKey); location.reload(); });
    }
})();
</script>
</body>
</html>
