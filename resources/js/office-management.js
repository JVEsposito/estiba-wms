import Chart from 'chart.js/auto';

const byId = (id) => document.getElementById(id);

const elements = {
    access: byId('officeAccess'),
    app: byId('officeApp'),
    login: byId('officeLoginForm'),
    loginError: byId('officeLoginError'),
    userName: byId('officeUserName'),
    userRole: byId('officeUserRole'),
    initials: byId('officeInitials'),
    logout: byId('officeLogoutButton'),
    camerasNav: byId('officeCamerasNav'),
    loadsNav: byId('officeLoadsNav'),
    materialsNav: byId('officeMaterialsNav'),
    prefrioNav: byId('officePrefrioNav'),
    accessesNav: byId('officeAccessesNav'),
    romanaNav: byId('officeRomanaNav'),
    refresh: byId('refreshDashboardButton'),
    lastUpdated: byId('lastUpdatedAt'),
    refreshStatus: byId('refreshStatus'),
    availablePositions: byId('availablePositionsKpi'),
    cameraOccupancyProgress: byId('cameraOccupancyProgress'),
    cameraOccupancyDetail: byId('cameraOccupancyDetail'),
    availableProducts: byId('availableProductsKpi'),
    productAvailabilityProgress: byId('productAvailabilityProgress'),
    productAvailabilityDetail: byId('productAvailabilityDetail'),
    materialItems: byId('materialItemsKpi'),
    materialFolios: byId('materialFoliosKpi'),
    materialUnits: byId('materialUnitsKpi'),
    precoolingAvailable: byId('precoolingAvailableKpi'),
    precoolingOccupancyProgress: byId('precoolingOccupancyProgress'),
    precoolingDetail: byId('precoolingDetail'),
    weighbridgeNetWeight: byId('weighbridgeNetWeightKpi'),
    weighbridgeClosed: byId('weighbridgeClosedKpi'),
    weighbridgePending: byId('weighbridgePendingKpi'),
    weighbridgeDetail: byId('weighbridgeDetail'),
    weighbridgeSummary: byId('weighbridgeChartSummary'),
    materialUnitSelect: byId('materialUnitSelect'),
    materialSummary: byId('materialChartSummary'),
    productSummary: byId('productChartSummary'),
    precoolingSummary: byId('precoolingChartSummary'),
    cameraRows: byId('cameraDetailRows'),
    alerts: byId('managementAlerts'),
    alertCount: byId('alertCount'),
    loading: byId('officeLoading'),
    loadingText: byId('officeLoadingText'),
    toasts: byId('officeToasts'),
};

const keys = {
    token: 'estiba_wms_office_token',
    identity: 'estiba_wms_office_identity',
};

const state = {
    token: localStorage.getItem(keys.token),
    identity: readJson(keys.identity),
    dashboard: null,
    loading: false,
    timer: null,
    charts: new Map(),
};

const palette = {
    cyan: '#16c9c2',
    cyanLight: '#55e5df',
    blue: '#3aa5ff',
    purple: '#9b7bff',
    green: '#55d889',
    amber: '#f3b94f',
    red: '#ff7070',
    quiet: '#294754',
    muted: '#8fa8b3',
    grid: 'rgba(143, 168, 179, .12)',
};

class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.status = status;
    }
}

Chart.defaults.color = palette.muted;
Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui, sans-serif';
Chart.defaults.borderColor = palette.grid;
Chart.register({
    id: 'centerLabel',
    afterDraw(chart, _args, options) {
        if (!options?.text || chart.config.type !== 'doughnut') return;

        const { ctx, chartArea } = chart;
        const centerX = (chartArea.left + chartArea.right) / 2;
        const centerY = (chartArea.top + chartArea.bottom) / 2;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#f4f8fa';
        ctx.font = '800 26px Inter, sans-serif';
        ctx.fillText(options.text, centerX, centerY - 6);
        ctx.fillStyle = palette.muted;
        ctx.font = '700 10px Inter, sans-serif';
        ctx.fillText(options.subtext || '', centerX, centerY + 17);
        ctx.restore();
    },
});

function readJson(key) {
    try {
        return JSON.parse(localStorage.getItem(key) || 'null');
    } catch {
        return null;
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function errorMessage(data, fallback) {
    return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback;
}

async function api(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    if (state.token) headers.set('Authorization', `Bearer ${state.token}`);
    if (options.body) headers.set('Content-Type', 'application/json');

    let response;
    try {
        response = await fetch(path, { ...options, headers });
    } catch {
        throw new ApiError('No fue posible conectar con el servidor.', 0);
    }

    const data = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401 && path !== '/api/acceso-oficina') clearSession();
        throw new ApiError(errorMessage(data, 'No fue posible completar la consulta.'), response.status);
    }

    return data;
}

function persistSession(payload) {
    state.token = payload.token;
    state.identity = payload.usuario;
    localStorage.setItem(keys.token, payload.token);
    localStorage.setItem(keys.identity, JSON.stringify(payload.usuario));
}

function clearSession() {
    state.token = null;
    state.identity = null;
    state.dashboard = null;
    localStorage.removeItem(keys.token);
    localStorage.removeItem(keys.identity);
    window.clearInterval(state.timer);
    state.timer = null;
    state.charts.forEach((chart) => chart.destroy());
    state.charts.clear();
    elements.app.classList.add('is-hidden');
    elements.access.classList.remove('is-hidden');
}

function showApp() {
    if (state.identity?.puede_consultar_panel_gerencial !== true) return false;

    elements.access.classList.add('is-hidden');
    elements.app.classList.remove('is-hidden');
    const name = state.identity?.nombre || 'Gerencia';
    elements.userName.textContent = name;
    elements.userRole.textContent = humanize(state.identity?.rol || 'consulta');
    elements.initials.textContent = name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
    elements.camerasNav.classList.toggle('is-hidden', state.identity?.ambito_camaras === 'ninguno');
    elements.loadsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_cargas !== true);
    elements.materialsNav.classList.toggle('is-hidden', state.identity?.puede_consultar_despachos_materiales !== true);
    elements.prefrioNav.classList.toggle('is-hidden', state.identity?.puede_consultar_prefrio !== true);
    elements.accessesNav.classList.toggle('is-hidden', state.identity?.puede_administrar_accesos !== true);
    elements.romanaNav.classList.toggle('is-hidden', state.identity?.puede_consultar_romana !== true);

    return true;
}

function humanize(value) {
    return String(value || '')
        .replaceAll('_', ' ')
        .replace(/^./, (character) => character.toUpperCase());
}

function setBusy(active, message = 'Actualizando indicadores…') {
    elements.loadingText.textContent = message;
    elements.loading.classList.toggle('is-hidden', !active);
    elements.loading.setAttribute('aria-hidden', String(!active));
    elements.refresh.disabled = active;
}

function toast(message, error = false) {
    const item = document.createElement('div');
    item.className = `toast${error ? ' toast--error' : ''}`;
    item.textContent = message;
    elements.toasts.append(item);
    window.setTimeout(() => item.remove(), 4500);
}

function formatInteger(value) {
    return new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(Number(value || 0));
}

function formatQuantity(value) {
    return new Intl.NumberFormat('es-CL', { maximumFractionDigits: 3 }).format(Number(value || 0));
}

function formatWeight(value) {
    return new Intl.NumberFormat('es-CL', { maximumFractionDigits: 1 }).format(Number(value || 0));
}

function formatDate(value) {
    if (!value) return 'Sin actualizar';

    return new Intl.DateTimeFormat('es-CL', {
        dateStyle: 'short',
        timeStyle: 'medium',
    }).format(new Date(value));
}

function clampPercentage(value) {
    return Math.max(0, Math.min(100, Number(value || 0)));
}

function renderDashboard(data) {
    state.dashboard = data;
    const capacity = data.camaras.resumen;
    const products = data.productos;
    const materials = data.materiales;
    const precooling = data.prefrio;
    const weighbridge = data.romana;

    elements.lastUpdated.textContent = formatDate(data.generado_at);
    elements.refreshStatus.textContent = `Actualiza automáticamente cada ${data.actualizacion_segundos} segundos`;

    elements.availablePositions.textContent = formatInteger(capacity.disponibles);
    elements.cameraOccupancyProgress.style.width = `${clampPercentage(capacity.ocupacion_porcentaje)}%`;
    elements.cameraOccupancyDetail.textContent = `${formatInteger(capacity.ocupadas)} ocupadas de ${formatInteger(capacity.operativas)} operativas · ${capacity.ocupacion_porcentaje}% de uso`;

    elements.availableProducts.textContent = formatInteger(products.disponibles_despacho);
    elements.productAvailabilityProgress.style.width = `${clampPercentage(products.disponibilidad_porcentaje)}%`;
    elements.productAvailabilityDetail.textContent = `${formatInteger(products.total_activos)} folios activos · ${products.disponibilidad_porcentaje}% disponibles`;

    elements.materialItems.textContent = formatInteger(materials.items_con_stock);
    elements.materialFolios.textContent = formatInteger(materials.folios_con_stock);
    elements.materialUnits.textContent = formatInteger(materials.unidades_medida.length);

    elements.precoolingAvailable.textContent = formatInteger(precooling.disponibles);
    elements.precoolingOccupancyProgress.style.width = `${clampPercentage(precooling.ocupacion_porcentaje)}%`;
    elements.precoolingDetail.textContent = `${formatInteger(precooling.ocupadas)} ocupadas · ${formatInteger(precooling.folios_pendientes)} folios en espera · ${precooling.tuneles_operativos}/${precooling.tuneles_totales} túneles operativos`;

    elements.weighbridgeNetWeight.textContent = formatWeight(weighbridge.peso_neto_hoy);
    elements.weighbridgeClosed.textContent = formatInteger(weighbridge.cerradas_hoy);
    elements.weighbridgePending.textContent = formatInteger(weighbridge.pendientes_destare);
    elements.weighbridgeDetail.textContent = `${formatInteger(weighbridge.en_bascula_ingreso)} en ingreso · ${formatInteger(weighbridge.envases_hoy)} envases · ${formatInteger(weighbridge.clientes_hoy)} clientes hoy`;

    renderCameraChart(data.camaras.detalle);
    renderProductChart(products);
    renderMaterialUnitOptions(materials.unidades_medida);
    renderMaterialChart();
    renderPrecoolingChart(precooling);
    renderWeighbridgeChart(weighbridge);
    renderCameraTable(data.camaras.detalle);
    renderAlerts(data.alertas);
}

function replaceChart(name, canvasId, configuration) {
    state.charts.get(name)?.destroy();
    const canvas = byId(canvasId);
    state.charts.set(name, new Chart(canvas, configuration));
}

function stackedBarOptions(horizontal = true) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: horizontal ? 'y' : 'x',
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#071820',
                borderColor: '#1c3845',
                borderWidth: 1,
                padding: 11,
            },
        },
        scales: {
            x: { stacked: true, beginAtZero: true, grid: { color: palette.grid }, ticks: { precision: 0 } },
            y: { stacked: true, grid: { display: false } },
        },
    };
}

function renderCameraChart(cameras) {
    replaceChart('cameras', 'cameraOccupancyChart', {
        type: 'bar',
        data: {
            labels: cameras.map((camera) => camera.codigo),
            datasets: [
                { label: 'Ocupadas', data: cameras.map((camera) => camera.ocupadas), backgroundColor: palette.cyan, borderRadius: 5, borderSkipped: false },
                { label: 'Disponibles', data: cameras.map((camera) => camera.disponibles), backgroundColor: palette.quiet, borderRadius: 5, borderSkipped: false },
            ],
        },
        options: stackedBarOptions(true),
    });
}

function renderProductChart(products) {
    const labels = ['Disponibles', 'Comprometidos', 'Pendientes prefrío', 'Bloqueados', 'Otros'];
    const values = [
        products.disponibles_despacho,
        products.comprometidos_carga,
        products.pendientes_prefrio,
        products.bloqueados,
        products.otros,
    ];
    const colors = [palette.green, palette.blue, palette.amber, palette.red, palette.quiet];

    replaceChart('products', 'productAvailabilityChart', {
        type: 'doughnut',
        data: { labels, datasets: [{ data: values, backgroundColor: colors, borderColor: '#0a1b25', borderWidth: 4, hoverOffset: 5 }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false },
                centerLabel: { text: formatInteger(products.total_activos), subtext: 'FOLIOS ACTIVOS' },
            },
        },
    });

    elements.productSummary.innerHTML = labels
        .map((label, index) => `<span><b style="color:${colors[index]}">${formatInteger(values[index])}</b>${escapeHtml(label)}</span>`)
        .join('');
}

function renderMaterialUnitOptions(units) {
    const previous = elements.materialUnitSelect.value;
    elements.materialUnitSelect.innerHTML = units.length
        ? units.map((unit) => `<option value="${escapeHtml(unit.unidad_medida)}">${escapeHtml(unit.unidad_medida)}</option>`).join('')
        : '<option value="">Sin stock</option>';

    if (units.some((unit) => unit.unidad_medida === previous)) {
        elements.materialUnitSelect.value = previous;
    }
}

function renderMaterialChart() {
    const units = state.dashboard?.materiales?.unidades_medida || [];
    const selected = units.find((unit) => unit.unidad_medida === elements.materialUnitSelect.value) || units[0];
    const items = (selected?.items || []).slice(0, 8);

    replaceChart('materials', 'materialStockChart', {
        type: 'bar',
        data: {
            labels: items.map((item) => `${item.cliente.codigo} · ${item.codigo}`),
            datasets: [
                { label: 'Disponible', data: items.map((item) => item.cantidad_disponible), backgroundColor: palette.purple, borderRadius: 5, borderSkipped: false },
                { label: 'Reservado', data: items.map((item) => item.cantidad_reservada), backgroundColor: palette.amber, borderRadius: 5, borderSkipped: false },
            ],
        },
        options: {
            ...stackedBarOptions(true),
            plugins: {
                ...stackedBarOptions(true).plugins,
                tooltip: {
                    callbacks: {
                        title: (contexts) => {
                            const item = items[contexts[0]?.dataIndex];
                            return item ? `${item.cliente.nombre} · ${item.nombre}` : '';
                        },
                        label: (context) => `${context.dataset.label}: ${formatQuantity(context.raw)} ${selected?.unidad_medida || ''}`,
                    },
                },
            },
            scales: {
                ...stackedBarOptions(true).scales,
                x: { ...stackedBarOptions(true).scales.x, ticks: { callback: (value) => formatQuantity(value) } },
            },
        },
    });

    elements.materialSummary.innerHTML = selected ? `
        <span><b>${formatQuantity(selected.cantidad_actual)} ${escapeHtml(selected.unidad_medida)}</b>stock actual</span>
        <span><b>${formatQuantity(selected.cantidad_reservada)} ${escapeHtml(selected.unidad_medida)}</b>reservado</span>
        <span><b>${formatQuantity(selected.cantidad_disponible)} ${escapeHtml(selected.unidad_medida)}</b>disponible</span>
    ` : '<span>Sin materiales con stock</span>';
}

function renderPrecoolingChart(precooling) {
    const tunnels = precooling.tuneles;
    replaceChart('precooling', 'precoolingChart', {
        type: 'bar',
        data: {
            labels: tunnels.map((tunnel) => tunnel.codigo),
            datasets: [
                { label: 'Ocupadas', data: tunnels.map((tunnel) => tunnel.ocupadas), backgroundColor: palette.blue, borderRadius: 5, borderSkipped: false },
                { label: 'Disponibles', data: tunnels.map((tunnel) => tunnel.disponibles), backgroundColor: palette.quiet, borderRadius: 5, borderSkipped: false },
            ],
        },
        options: stackedBarOptions(true),
    });

    elements.precoolingSummary.innerHTML = `
        <span><b>${formatInteger(precooling.procesos_activos)}</b>procesos activos</span>
        <span><b>${formatInteger(precooling.folios_pendientes)}</b>folios pendientes</span>
    `;
}

function renderWeighbridgeChart(weighbridge) {
    const days = weighbridge.tendencia_diaria || [];
    replaceChart('weighbridge', 'weighbridgeReceptionChart', {
        type: 'bar',
        data: {
            labels: days.map((day) => day.etiqueta),
            datasets: [{
                label: 'Peso neto',
                data: days.map((day) => day.peso_neto),
                backgroundColor: palette.amber,
                borderRadius: 5,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => `${formatWeight(context.raw)} kg netos`,
                        afterLabel: (context) => `${formatInteger(days[context.dataIndex]?.recepciones)} recepciones`,
                    },
                },
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: palette.grid }, ticks: { callback: (value) => `${formatWeight(value)} kg` } },
            },
        },
    });

    elements.weighbridgeSummary.innerHTML = `
        <span><b>${formatInteger(weighbridge.cerradas_hoy)}</b>recepciones cerradas hoy</span>
        <span><b>${formatWeight(weighbridge.peso_neto_hoy)} kg</b>peso neto hoy</span>
    `;
}

function renderCameraTable(cameras) {
    elements.cameraRows.innerHTML = cameras.length
        ? cameras.map((camera) => `
            <tr>
                <td><strong>${escapeHtml(camera.codigo)}</strong><small>${escapeHtml(camera.nombre)}</small></td>
                <td><span class="area-chip${camera.contenido === 'materiales' ? ' area-chip--materials' : ''}">${camera.contenido === 'materiales' ? 'Materiales' : 'Productos'}</span></td>
                <td>${formatInteger(camera.ocupadas)}</td>
                <td>${formatInteger(camera.disponibles)}</td>
                <td>${formatInteger(camera.no_operativas)}</td>
                <td><span class="usage-chip${camera.ocupacion_porcentaje >= 90 ? ' usage-chip--warning' : ''}">${camera.ocupacion_porcentaje}%</span></td>
            </tr>
        `).join('')
        : '<tr><td colspan="6"><div class="management-empty">No hay cámaras activas configuradas.</div></td></tr>';
}

function renderAlerts(alerts) {
    elements.alertCount.textContent = formatInteger(alerts.length);
    elements.alerts.innerHTML = alerts.length
        ? alerts.map((alert) => `
            <div class="management-alert${alert.nivel === 'critica' ? ' management-alert--critical' : ''}">
                <i aria-hidden="true"></i>
                <div><strong>${escapeHtml(alert.titulo)}</strong><span>${escapeHtml(alert.detalle)}</span></div>
            </div>
        `).join('')
        : '<div class="management-empty">Sin focos críticos en la instantánea actual.</div>';
}

async function loadDashboard({ blocking = false, silent = false } = {}) {
    if (state.loading) return;

    state.loading = true;
    setBusy(blocking, 'Actualizando indicadores…');
    elements.refresh.disabled = true;
    elements.refreshStatus.textContent = 'Consultando la operación actual…';

    try {
        const payload = await api('/api/gerencia/resumen');
        renderDashboard(payload.data);
        if (!silent) toast('Panel actualizado con la información actual.');
    } catch (error) {
        elements.refreshStatus.textContent = 'No fue posible actualizar; se conserva la última lectura.';
        if (error.status === 403) {
            clearSession();
            elements.loginError.textContent = 'Tu perfil no posee acceso al panel gerencial.';
        } else if (!silent || !state.dashboard) {
            toast(error.message, true);
        }
    } finally {
        state.loading = false;
        setBusy(false);
        elements.refresh.disabled = false;
    }
}

function startAutoRefresh() {
    window.clearInterval(state.timer);
    const seconds = Number(state.dashboard?.actualizacion_segundos || 30);
    state.timer = window.setInterval(() => {
        if (!document.hidden) void loadDashboard({ silent: true });
    }, seconds * 1000);
}

elements.login.addEventListener('submit', async (event) => {
    event.preventDefault();
    elements.loginError.textContent = '';
    setBusy(true, 'Validando acceso…');

    try {
        const payload = await api('/api/acceso-oficina', {
            method: 'POST',
            body: JSON.stringify(Object.fromEntries(new FormData(elements.login))),
        });
        if (payload.usuario.puede_consultar_panel_gerencial !== true) {
            throw new ApiError('Tu perfil no posee acceso al panel gerencial.', 403);
        }
        persistSession(payload);
        showApp();
        await loadDashboard({ blocking: true, silent: true });
        if (state.dashboard) startAutoRefresh();
    } catch (error) {
        elements.loginError.textContent = error.message;
    } finally {
        setBusy(false);
    }
});

elements.logout.addEventListener('click', async () => {
    try {
        await api('/api/acceso-oficina', { method: 'DELETE' });
    } catch {
        // La sesión local igualmente debe cerrarse.
    }
    clearSession();
});

elements.refresh.addEventListener('click', () => void loadDashboard());
elements.materialUnitSelect.addEventListener('change', renderMaterialChart);

async function boot() {
    if (!state.token || state.identity?.puede_consultar_panel_gerencial !== true) return;
    if (!showApp()) return;

    await loadDashboard({ blocking: true, silent: true });
    if (state.dashboard) startAutoRefresh();
}

void boot();
