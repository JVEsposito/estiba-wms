import { createOperationalPoller } from './shared/operational-poller';
import { buildOperationalAlerts } from './shared/operation-now-alerts';
import { autoLayout, availableCatalog, catalogKey, moveElement, resizeElement } from './shared/plant-layout';

const tokenKey = 'estiba_wms_office_token';
const identityKey = 'estiba_wms_office_identity';
const byId = (id) => document.getElementById(id);

const elements = {
    app: byId('operationNowApp'),
    workspace: byId('operationWorkspace'),
    refresh: byId('operationRefresh'),
    loading: byId('operationLoading'),
    loadingText: byId('operationLoadingText'),
    toasts: byId('operationToasts'),
    connection: byId('operationConnection'),
    connectionTitle: byId('operationConnectionTitle'),
    connectionDetail: byId('operationConnectionDetail'),
    liveSignal: byId('operationLiveSignal'),
    liveText: byId('operationLiveText'),
    season: byId('operationSeason'),
    updatedAt: byId('operationUpdatedAt'),
    date: byId('operationDate'),
    time: byId('operationTime'),
    timezone: byId('operationTimezone'),
    shift: byId('operationShift'),
    cameraRows: byId('operationCameraRows'),
    operatorList: byId('operationOperatorList'),
    tunnelList: byId('operationTunnelList'),
    incidentRows: byId('operationIncidentRows'),
    facilityMap: byId('operationFacilityMap'),
    facilityStatus: byId('operationFacilityStatus'),
    facilitySubtitle: byId('operationFacilitySubtitle'),
    mapZoomLabel: byId('operationMapZoomLabel'),
    mapEdit: byId('operationMapEdit'),
    mapDialog: byId('operationMapDialog'),
    mapDialogTitle: byId('operationMapDialogTitle'),
    mapDialogHint: byId('operationMapDialogHint'),
    mapSave: byId('operationMapSave'),
    mapCatalog: byId('operationMapCatalog'),
    mapCatalogItems: byId('operationMapCatalogItems'),
    mapInspector: byId('operationMapInspector'),
    mapSelectionName: byId('operationMapSelectionName'),
    editorStage: byId('operationEditorStage'),
    editorZoomLabel: byId('operationEditorZoomLabel'),
    alertsPanel: byId('operationAlertsPanel'),
    alertRows: byId('operationAlertRows'),
};

const state = {
    token: localStorage.getItem(tokenKey),
    identity: readJson(identityKey),
    snapshot: null,
    loading: false,
    poller: null,
    clockTimer: null,
    clockOffsetMs: 0,
    mapZoom: 1,
    editorZoom: 1,
    mapEditing: false,
    mapDraft: [],
    mapSelectedId: null,
    mapDrag: null,
};

class ApiError extends Error {
    constructor(message, status = 0) {
        super(message);
        this.status = status;
    }
}

function readJson(key) {
    try {
        return JSON.parse(localStorage.getItem(key) || 'null');
    } catch {
        return null;
    }
}

function capabilities() {
    return { ...(state.identity?.capacidades || {}), ...(state.identity || {}) };
}

function can(permission) {
    return state.identity?.rol === 'administrador' || capabilities()[permission] === true;
}

function hasModule(module) {
    const modules = capabilities().modulos_acceso;
    return !Array.isArray(modules) || modules.includes(module);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function humanize(value) {
    return String(value || '')
        .replaceAll('_', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}

function number(value, maximumFractionDigits = 0) {
    if (value === null || value === undefined || value === '') return '—';
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return '—';
    return new Intl.NumberFormat('es-CL', { maximumFractionDigits }).format(parsed);
}

function percent(value) {
    return `${number(value, 1)} %`;
}

function temperature(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? `${number(parsed, 1)} °C` : '—';
}

function dateTime(value, options = {}) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';

    try {
        return new Intl.DateTimeFormat('es-CL', {
            dateStyle: options.timeOnly ? undefined : 'short',
            timeStyle: 'short',
            timeZone: state.snapshot?.jornada?.zona_horaria || undefined,
        }).format(date);
    } catch {
        return new Intl.DateTimeFormat('es-CL', {
            dateStyle: options.timeOnly ? undefined : 'short',
            timeStyle: 'short',
        }).format(date);
    }
}

function duration(minutes) {
    if (minutes === null || minutes === undefined) return 'Sin objetivo';
    const total = Math.max(0, Math.round(Number(minutes) || 0));
    const hours = Math.floor(total / 60);
    const remainder = total % 60;
    return hours ? `${hours} h ${String(remainder).padStart(2, '0')} min` : `${remainder} min`;
}

function clampedPercent(value) {
    return Math.max(0, Math.min(100, Number(value) || 0));
}

function toneForOccupancy(level) {
    return { critica: 'critical', advertencia: 'warning', normal: 'success' }[level] || 'neutral';
}

function toneForEnvironment(status) {
    return { vigente: 'success', vencido: 'critical', pendiente: 'warning' }[status] || 'neutral';
}

function toneForPriority(priority) {
    return { critica: 'critical', urgente: 'critical', alta: 'warning', normal: 'info' }[priority] || 'neutral';
}

function toneForTunnel(tunnel) {
    if (!tunnel.operable || ['mantenimiento', 'fuera_servicio', 'inactivo'].includes(tunnel.estado_operacional)) return 'neutral';
    if (tunnel.proceso_activo?.objetivo_excedido) return 'critical';
    if (tunnel.estado_operacional === 'pendiente_verificacion') return 'warning';
    if (tunnel.estado_operacional === 'disponible') return 'success';
    return 'info';
}

function signal(text, tone = 'neutral') {
    return `<span class="operation-now-signal" data-tone="${tone}">${escapeHtml(text)}</span>`;
}

function empty(title, detail) {
    return `<div class="operation-now-empty"><strong>${escapeHtml(title)}</strong><span>${escapeHtml(detail)}</span></div>`;
}

function setText(id, value) {
    const element = byId(id);
    if (element) element.textContent = value;
}

function errorMessage(data, fallback) {
    return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback;
}

function validateSnapshot(data) {
    const valid = data
        && data.jornada
        && data.temporada
        && data.sincronizacion?.operaciones_hoy
        && Array.isArray(data.camaras)
        && Array.isArray(data.camareros)
        && data.prefrio?.resumen
        && Array.isArray(data.prefrio?.tuneles)
        && data.incidencias?.resumen
        && Array.isArray(data.incidencias?.abiertas)
        && data.planta
        && Array.isArray(data.planta?.catalogo)
        && Array.isArray(data.planta?.elementos);

    if (!valid) throw new ApiError('El servidor entregó una lectura operacional incompleta.');
    return data;
}

function clearSession() {
    localStorage.removeItem(tokenKey);
    localStorage.removeItem(identityKey);
    state.poller?.stop();
    window.clearInterval(state.clockTimer);
    window.location.replace('/oficina/accesos');
}

async function api(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    headers.set('Authorization', `Bearer ${state.token}`);
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 12_000);

    let response;
    try {
        response = await fetch(path, { ...options, headers, signal: controller.signal });
    } catch (error) {
        const message = error?.name === 'AbortError'
            ? 'El servidor tardó demasiado en responder.'
            : 'No fue posible conectar con el servidor.';
        throw new ApiError(message);
    } finally {
        window.clearTimeout(timeout);
    }

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401) clearSession();
        throw new ApiError(errorMessage(data, 'No fue posible consultar la operación.'), response.status);
    }

    return data;
}

function setBusy(active, message = 'Consultando la operación…') {
    elements.loadingText.textContent = message;
    elements.loading.classList.toggle('is-hidden', !active);
    elements.loading.setAttribute('aria-hidden', String(!active));
    elements.workspace.setAttribute('aria-busy', String(active));
    elements.refresh.disabled = active;
}

function toast(message, error = false) {
    const node = document.createElement('div');
    node.className = `toast${error ? ' toast--error' : ''}`;
    node.textContent = message;
    elements.toasts.append(node);
    window.setTimeout(() => node.remove(), 5000);
}

function renderClock() {
    if (!state.snapshot) return;
    const now = new Date(Date.now() + state.clockOffsetMs);
    const timeZone = state.snapshot.jornada?.zona_horaria;

    try {
        elements.date.textContent = new Intl.DateTimeFormat('es-CL', {
            weekday: 'short', day: '2-digit', month: 'short', year: 'numeric', timeZone,
        }).format(now);
        elements.time.textContent = new Intl.DateTimeFormat('es-CL', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone,
        }).format(now);
    } catch {
        elements.date.textContent = state.snapshot.jornada?.fecha || '—';
        elements.time.textContent = state.snapshot.jornada?.hora || '--:--:--';
    }
    elements.timezone.textContent = timeZone || 'Hora operacional';
}

function startClock() {
    window.clearInterval(state.clockTimer);
    renderClock();
    state.clockTimer = window.setInterval(renderClock, 1000);
}

function renderHeader(data) {
    const generated = new Date(data.generado_at);
    state.clockOffsetMs = Number.isNaN(generated.getTime()) ? 0 : generated.getTime() - Date.now();
    elements.season.textContent = data.temporada
        ? `${data.temporada.codigo} · ${data.temporada.nombre}`
        : 'Sin temporada activa';
    elements.updatedAt.textContent = `Última lectura: ${dateTime(data.generado_at)}`;
    elements.shift.textContent = data.jornada?.turno
        ? `Turno: ${humanize(data.jornada.turno)}`
        : 'Turno sin configurar';

    const status = data.sincronizacion?.estado || 'sin_actividad';
    const tone = { aceptada: 'success', procesando: 'info', pendiente: 'warning', rechazada: 'critical', conflicto: 'critical' }[status] || 'neutral';
    const statusLabel = {
        aceptada: 'Sincronizado',
        procesando: 'Procesando',
        pendiente: 'Pendiente',
        rechazada: 'Con rechazo',
        conflicto: 'Con conflicto',
    }[status] || 'Sin actividad sincronizada';
    elements.liveSignal.dataset.tone = tone;
    elements.liveText.textContent = statusLabel;
    startClock();
}

function renderMetrics(data) {
    const cameras = data.camaras || [];
    const productCameras = cameras.filter((camera) => camera.contenido === 'productos');
    const capacity = productCameras.reduce((sum, camera) => sum + Number(camera.capacidad_operativa || 0), 0);
    const occupied = productCameras.reduce((sum, camera) => sum + Number(camera.ocupadas || 0), 0);
    const occupancy = capacity ? (occupied / capacity) * 100 : 0;
    const environmentalDue = productCameras.filter((camera) => camera.control_ambiental?.requiere_control).length;
    const operators = data.camareros || [];
    const activeTasks = operators.filter((operator) => operator.tarea_actual).length;
    const precooling = data.prefrio?.resumen || {};
    const incidents = data.incidencias?.resumen || {};

    setText('cameraPanelSummary', `${number(productCameras.length)} · ${percent(occupancy)} · ${number(environmentalDue)} control`);
    setText('operatorPanelCount', number(operators.length));
    setText('precoolingPanelSummary', `${number(precooling.procesos_activos)} activos · ${number(precooling.procesos_fuera_objetivo)} fuera`);
    setText('incidentPanelSummary', `${number(incidents.total_abiertas)} abiertas`);

    const cameraSummary = byId('cameraPanelSummary');
    if (cameraSummary) {
        cameraSummary.title = `${number(occupied)} de ${number(capacity)} posiciones PT; ${number(environmentalDue)} ${environmentalDue === 1 ? 'cámara requiere control' : 'cámaras requieren control'}`;
    }

    const operatorCount = byId('operatorPanelCount');
    if (operatorCount) operatorCount.title = `${number(activeTasks)} con tarea vigente`;
}

function renderSync(sync = {}) {
    const latest = sync.ultima_operacion;
    setText('syncLatestOperation', latest ? `${humanize(latest.tipo)} · ${humanize(latest.estado)}` : 'Sin actividad registrada');
    setText('syncLatestContext', latest
        ? `${latest.usuario?.nombre || 'Usuario no informado'} · ${latest.dispositivo?.codigo || 'Sin dispositivo'} · ${dateTime(latest.recibida_servidor_at)}`
        : 'Esperando evidencia de dispositivos');
    const counts = sync.operaciones_hoy || {};
    setText('syncAccepted', number(counts.aceptada));
    setText('syncPending', number(counts.pendiente));
    setText('syncProcessing', number(counts.procesando));
    setText('syncRejected', number(counts.rechazada));
    setText('syncConflict', number(counts.conflicto));
}

function renderCameras(cameras = []) {
    const productCameras = cameras.filter((camera) => camera.contenido === 'productos');
    if (!productCameras.length) {
        elements.cameraRows.innerHTML = `<tr><td colspan="4">${empty('Sin cámaras PT activas', 'No existen cámaras de producto terminado disponibles.')}</td></tr>`;
        return;
    }

    elements.cameraRows.innerHTML = productCameras.map((camera) => {
        const control = camera.control_ambiental;
        const occupancyTone = toneForOccupancy(camera.nivel_ocupacion);
        const environmentTone = toneForEnvironment(control?.estado);
        const readings = control?.temperaturas_c;
        const readingDetail = readings
            ? `Inicio ${temperature(readings.inicio)} · Medio ${temperature(readings.medio)} · Fondo ${temperature(readings.fondo)}`
            : '';
        const currentTemperature = readings
            ? `<span class="operation-now-temperature" title="${escapeHtml(readingDetail)}">${escapeHtml(temperature(readings.promedio))}</span>`
            : '<strong class="operation-now-no-reading">SIN REGISTRO</strong>';
        const controlStatus = control
            ? `${signal(humanize(control.estado), environmentTone)}<span class="operation-now-subtext"${control.vigente_hasta ? ` title="Vigente hasta ${escapeHtml(dateTime(control.vigente_hasta, { timeOnly: true }))}"` : ''}>${control.capturado_at ? `Registro ${escapeHtml(dateTime(control.capturado_at, { timeOnly: true }))}` : 'Sin captura informada'}</span>`
            : '<span class="operation-now-subtext">Sin control configurado</span>';

        return `<tr>
            <td><span class="operation-now-code">${escapeHtml(camera.codigo)}</span><span class="operation-now-subtext">${escapeHtml(camera.nombre)}</span></td>
            <td>
                ${signal(`${number(camera.ocupadas)} / ${number(camera.capacidad_operativa)} · ${percent(camera.ocupacion_porcentaje)}`, occupancyTone)}
                <span class="operation-now-meter" data-tone="${occupancyTone}" style="--operation-progress:${clampedPercent(camera.ocupacion_porcentaje)}%"><i></i></span>
            </td>
            <td>${currentTemperature}</td>
            <td>${controlStatus}</td>
        </tr>`;
    }).join('');
}

function taskLocation(endpoint) {
    if (!endpoint?.camara) return 'Sin ubicación física';
    return [endpoint.camara.codigo, endpoint.posicion?.etiqueta].filter(Boolean).join(' · ');
}

function renderOperators(operators = []) {
    const orderedOperators = [...operators].sort((left, right) => {
        const weight = { critica: 0, urgente: 0, alta: 1, normal: 2 };
        const leftTask = left.tarea_actual;
        const rightTask = right.tarea_actual;
        return Number(!leftTask) - Number(!rightTask)
            || (weight[leftTask?.prioridad] ?? 9) - (weight[rightTask?.prioridad] ?? 9)
            || String(left.usuario?.nombre || '').localeCompare(String(right.usuario?.nombre || ''), 'es');
    });
    setText('operatorPanelCount', number(orderedOperators.length));
    if (!orderedOperators.length) {
        elements.operatorList.innerHTML = empty('Sin camareros activos', 'No existen sesiones de estiba abiertas en este momento.');
        return;
    }

    elements.operatorList.innerHTML = orderedOperators.map((operator) => {
        const task = operator.tarea_actual;
        const currentCamera = operator.ubicacion_actual?.camara;
        const taskMarkup = task ? `<div class="operation-now-operator__task">
            <div class="operation-now-operator__task-head">
                <div><span class="operation-now-operator__label">TAREA ${escapeHtml(humanize(task.estado).toUpperCase())}</span><p><span class="operation-now-code">${escapeHtml(task.folio?.numero_folio || 'Sin folio')}</span></p></div>
                ${signal(humanize(task.prioridad), toneForPriority(task.prioridad))}
            </div>
            <p>${escapeHtml(task.instruccion || task.plan?.titulo || humanize(task.tipo_movimiento))}</p>
            <div class="operation-now-route"><span>${escapeHtml(taskLocation(task.origen))}</span><b aria-label="hacia">→</b><span>${escapeHtml(task.destino_logico?.nombre || taskLocation(task.destino))}</span></div>
        </div>` : `<div class="operation-now-operator__task">${signal('Disponible, sin tarea tomada', 'success')}</div>`;

        return `<article class="operation-now-operator">
            <div class="operation-now-operator__identity">
                <h3>${escapeHtml(operator.usuario?.nombre || 'Camarero')}</h3>
                <span class="operation-now-operator__meta">${escapeHtml(operator.dispositivo?.codigo || 'Sin dispositivo')} · actividad ${escapeHtml(dateTime(operator.sesion?.ultima_actividad_at, { timeOnly: true }))}</span>
                <div class="operation-now-operator__status">${signal('En operación', 'success')}</div>
            </div>
            <div class="operation-now-operator__location"><span class="operation-now-operator__label">UBICACIÓN</span><p><strong>${escapeHtml(currentCamera?.codigo || 'Sin cámara')}</strong><span class="operation-now-subtext">${escapeHtml(currentCamera?.nombre || 'No informada')}</span></p></div>
            ${taskMarkup}
        </article>`;
    }).join('');
}

function currentMapElements(plant = state.snapshot?.planta) {
    return plant?.configurado ? plant.elementos : autoLayout(plant?.catalogo || []);
}

function mapCatalogIndex() {
    return new Map((state.snapshot?.planta?.catalogo || [])
        .map((item) => [catalogKey(item.tipo, item.id), item]));
}

function mapTypeLabel(type) {
    return { camara: 'Cámara', tunel: 'Túnel', anden: 'Andén', almacen: 'Bodega', zona: 'Área' }[type] || 'Recinto';
}

function mapNodeMarkup(item, editable = false) {
    const catalog = item.tipo === 'zona' ? null : mapCatalogIndex().get(catalogKey(item.tipo, item.referencia_id));
    const code = catalog?.codigo || mapTypeLabel(item.tipo);
    const name = catalog?.nombre || item.nombre;
    const detail = catalog?.detalle || (item.tipo === 'zona' ? humanize(item.categoria || 'otro') : 'Referencia no disponible');
    const tone = catalog?.tono || (item.tipo === 'zona' ? 'neutral' : 'warning');
    const selected = editable && state.mapSelectedId === item.id;

    return `<article class="operation-map-node${selected ? ' is-selected' : ''}" data-id="${escapeHtml(item.id)}" data-type="${escapeHtml(item.tipo)}" data-tone="${escapeHtml(tone)}" data-rotation="${Number(item.rotacion) || 0}" style="left:${item.x / 100}%;top:${item.y / 100}%;width:${item.ancho / 100}%;height:${item.alto / 100}%" aria-label="${escapeHtml(`${mapTypeLabel(item.tipo)} ${code}, ${name}: ${detail}`)}">
        <span class="operation-map-node__type">${escapeHtml(item.tipo === 'zona' ? humanize(item.categoria || 'Área') : `${mapTypeLabel(item.tipo)} · ${code}`)}</span>
        <strong title="${escapeHtml(name)}">${escapeHtml(name)}</strong>
        <small>${escapeHtml(detail)}</small>
        ${editable ? '<button class="operation-map-node__resize" type="button" aria-label="Redimensionar"></button>' : ''}
    </article>`;
}

function renderMapStage(target, items, editable = false) {
    if (!items.length) {
        target.innerHTML = empty('Plano sin recintos', editable ? 'Agrega recintos desde el catálogo o dibuja una nueva área.' : 'Administración aún no ha configurado la planta.');
        return;
    }
    target.innerHTML = items.map((item) => mapNodeMarkup(item, editable)).join('');
}

function setMapZoom(kind, value) {
    const normalized = Math.max(.6, Math.min(1.8, Math.round(value * 10) / 10));
    const stage = kind === 'editor' ? elements.editorStage : elements.facilityMap;
    const label = kind === 'editor' ? elements.editorZoomLabel : elements.mapZoomLabel;
    state[kind === 'editor' ? 'editorZoom' : 'mapZoom'] = normalized;
    stage.style.width = `${normalized * 100}%`;
    label.textContent = `${Math.round(normalized * 100)} %`;
}

function renderFacility(data) {
    const plant = data.planta;
    const items = currentMapElements(plant);
    renderMapStage(elements.facilityMap, items);
    elements.facilityStatus.textContent = plant.configurado ? `Plano v${plant.version}` : 'Distribución automática';
    elements.facilityStatus.dataset.tone = plant.configurado ? 'success' : 'warning';
    elements.facilitySubtitle.textContent = plant.configurado
        ? `${plant.nombre} · estados actualizados en vivo`
        : 'Borrador automático: falta guardar la distribución física';
    elements.mapEdit.hidden = !plant.puede_editar;
    setMapZoom('map', state.mapZoom);
}

function renderAlerts(data) {
    const alerts = buildOperationalAlerts(data);
    setText('operationAlertCount', number(alerts.length));
    elements.alertsPanel.dataset.tone = alerts.some((alert) => alert.severity === 'critical')
        ? 'critical'
        : (alerts.length ? 'warning' : 'neutral');

    if (!alerts.length) {
        elements.alertRows.innerHTML = empty('Sin alertas operacionales', 'Ocupación, ambiente, prefrío y sincronización no presentan condiciones de alerta.');
        return;
    }

    elements.alertRows.innerHTML = alerts.map((alert) => `<article class="operation-now-alert">
        <div class="operation-now-alert__heading"><span class="operation-now-code">${escapeHtml(alert.area)}</span>${signal(alert.severity === 'critical' ? 'Alta' : 'Media', alert.severity)}</div>
        <strong>${escapeHtml(alert.condition)}</strong>
        <p>${escapeHtml(alert.evidence)}</p>
        <a class="operation-now-action" href="${escapeHtml(alert.href)}">${escapeHtml(alert.action)} <span aria-hidden="true">→</span></a>
    </article>`).join('');
}

function tunnelStateLabel(tunnel) {
    return {
        borrador: 'Borrador',
        cargando: 'Cargando',
        listo_para_iniciar: 'Listo',
        en_proceso: 'En ciclo',
        pendiente_verificacion: 'Verificación',
        disponible: 'En espera',
        mantenimiento: 'Mantención',
        fuera_servicio: 'Fuera de servicio',
        inactivo: 'Inactivo',
    }[tunnel.estado_operacional] || humanize(tunnel.estado_operacional);
}

function tunnelStateTone(tunnel) {
    if (!tunnel.operable || ['mantenimiento', 'fuera_servicio', 'inactivo'].includes(tunnel.estado_operacional)) return 'neutral';
    if (tunnel.proceso_activo?.objetivo_excedido) return 'critical';
    if (tunnel.estado_operacional === 'pendiente_verificacion') return 'warning';
    if (tunnel.estado_operacional === 'en_proceso') return 'success';
    if (tunnel.estado_operacional === 'disponible') return 'neutral';
    return 'info';
}

function tunnelProgress(tunnel) {
    const process = tunnel.proceso_activo;
    if (!process) {
        return `<div class="operation-now-tunnel-progress">
            <strong>0 %</strong>
            <span class="operation-now-meter" data-tone="neutral" style="--operation-progress:0%"><i></i></span>
            <small>Sin proceso activo</small>
        </div>`;
    }

    const progress = process.avance_tiempo_objetivo_porcentaje;
    const hasProgress = progress !== null && progress !== undefined;
    const tone = process.objetivo_excedido
        ? 'critical'
        : (tunnel.estado_operacional === 'pendiente_verificacion' ? 'warning' : 'success');
    const detail = hasProgress
        ? `${duration(process.transcurridos_minutos)} de ${duration(process.duracion_objetivo_minutos)}${process.objetivo_excedido ? ` · excedido ${duration(process.minutos_sobre_objetivo)}` : ''}`
        : 'Sin inicio registrado';

    return `<div class="operation-now-tunnel-progress">
        <strong>${hasProgress ? escapeHtml(percent(progress)) : '0 %'}</strong>
        <span class="operation-now-meter" data-tone="${tone}" style="--operation-progress:${clampedPercent(progress)}%"><i></i></span>
        <small>${escapeHtml(detail)}</small>
    </div>`;
}

function tunnelProduct(process) {
    const products = Array.isArray(process?.productos) ? process.productos : [];
    if (!products.length) {
        return '<span class="operation-now-no-product">—</span><span class="operation-now-subtext">Sin informar</span>';
    }

    const labels = products.map((product) => product.etiqueta).filter(Boolean);
    const folios = products.reduce((total, product) => total + (Number(product.folios) || 0), 0);
    const main = products.length === 1 ? labels[0] : `Mixto · ${number(products.length)} productos`;
    const detail = products.length === 1
        ? `${number(folios)} ${folios === 1 ? 'folio' : 'folios'}`
        : labels.join(' · ');

    return `<strong class="operation-now-product" title="${escapeHtml(labels.join(' · '))}">${escapeHtml(main)}</strong><span class="operation-now-subtext">${escapeHtml(detail)}</span>`;
}

function renderTunnels(tunnels = []) {
    if (!tunnels.length) {
        elements.tunnelList.innerHTML = `<tr><td colspan="4">${empty('Sin túneles configurados', 'No existe infraestructura de prefrío para mostrar.')}</td></tr>`;
        return;
    }

    elements.tunnelList.innerHTML = tunnels.map((tunnel) => `<tr>
        <td><span class="operation-now-code">${escapeHtml(tunnel.codigo)}</span><span class="operation-now-subtext">${number(tunnel.posiciones_ocupadas)} de ${number(tunnel.capacidad_posiciones)} posiciones</span></td>
        <td>${signal(tunnelStateLabel(tunnel), tunnelStateTone(tunnel))}</td>
        <td>${tunnelProgress(tunnel)}</td>
        <td>${tunnelProduct(tunnel.proceso_activo)}</td>
    </tr>`).join('');
}

function incidentContext(incident) {
    if (incident.origen === 'carga') {
        const load = incident.contexto?.carga;
        const location = incident.contexto?.ubicacion_reportada;
        return [load?.codigo, location?.camara?.codigo, location?.posicion?.etiqueta].filter(Boolean).join(' · ');
    }
    const maneuver = incident.contexto?.maniobra;
    const task = incident.contexto?.tarea;
    return [maneuver?.titulo, taskLocation(task?.origen), taskLocation(task?.destino)].filter(Boolean).join(' · ');
}

function renderIncidents(incidents = []) {
    if (!incidents.length) {
        elements.incidentRows.innerHTML = `<tr><td colspan="5">${empty('Sin incidencias abiertas', 'No existen excepciones vigentes en la temporada activa.')}</td></tr>`;
        return;
    }

    elements.incidentRows.innerHTML = incidents.map((incident) => `<tr>
        <td><span class="operation-now-code">${escapeHtml(duration(incident.antiguedad_minutos))}</span><span class="operation-now-subtext">${escapeHtml(dateTime(incident.reportada_at))}</span></td>
        <td>${signal(incident.origen === 'maniobra' ? 'Maniobra' : 'Carga', incident.origen === 'maniobra' ? 'warning' : 'info')}</td>
        <td>${signal(humanize(incident.prioridad), toneForPriority(incident.prioridad))}</td>
        <td><span class="operation-now-code">${escapeHtml(incident.folio?.numero_folio || 'Sin folio')}</span><span class="operation-now-subtext">${escapeHtml(incidentContext(incident) || 'Sin contexto adicional')}</span></td>
        <td><strong>${escapeHtml(humanize(incident.tipo))}</strong><span class="operation-now-subtext">${escapeHtml(incident.detalle || 'Sin detalle')} · ${escapeHtml(incident.reportado_por?.nombre || 'Sin reportante')} · ${escapeHtml(incident.dispositivo?.codigo || 'Sin dispositivo')}</span></td>
    </tr>`).join('');
}

function render(data) {
    state.snapshot = data;
    renderHeader(data);
    renderMetrics(data);
    renderSync(data.sincronizacion);
    renderCameras(data.camaras);
    renderOperators(data.camareros);
    renderTunnels(data.prefrio?.tuneles);
    renderIncidents(data.incidencias?.abiertas);
    renderFacility(data);
    renderAlerts(data);
    elements.workspace.setAttribute('aria-busy', 'false');
}

function showConnectionIssue(error) {
    elements.connection.hidden = false;
    elements.connection.dataset.tone = state.snapshot ? 'warning' : 'critical';
    elements.connectionTitle.textContent = state.snapshot ? 'Última lectura conservada' : 'Operación no disponible';
    elements.connectionDetail.textContent = state.snapshot
        ? `${error.message} Los datos visibles corresponden a ${dateTime(state.snapshot.generado_at)}.`
        : error.message;
    elements.liveSignal.dataset.tone = 'critical';
    elements.liveText.textContent = navigator.onLine ? 'Servidor sin respuesta' : 'Equipo sin conexión';
}

async function load({ blocking = false, silent = false, propagate = false } = {}) {
    if (state.loading) return false;
    state.loading = true;
    setBusy(blocking, 'Consultando la operación…');

    try {
        const payload = await api('/api/operacion-ahora');
        render(validateSnapshot(payload.data));
        elements.connection.hidden = true;
        if (!silent) toast('Operación actualizada.');
        return true;
    } catch (error) {
        showConnectionIssue(error);
        if (!silent || !state.snapshot) toast(error.message, true);
        if (error.status === 403) window.location.replace('/oficina/administracion');
        if (propagate) throw error;
        return false;
    } finally {
        state.loading = false;
        setBusy(false);
    }
}

function startPolling() {
    state.poller?.stop();
    const seconds = Math.max(10, Number(state.snapshot?.actualizacion_sugerida_segundos || 30));
    state.poller = createOperationalPoller(
        () => load({ silent: true, propagate: true }),
        {
            intervalMs: seconds * 1000,
            canRun: () => Boolean(state.token) && !state.loading,
            onError: (error) => showConnectionIssue(error),
        },
    );
    state.poller.start();
}

async function logout() {
    try {
        await fetch('/api/acceso-oficina', {
            method: 'DELETE',
            headers: { Accept: 'application/json', Authorization: `Bearer ${state.token}` },
        });
    } catch {
        // La sesión local se cierra aunque el servidor no responda.
    }
    clearSession();
}

function renderMapCatalog() {
    const available = availableCatalog(state.snapshot?.planta?.catalogo || [], state.mapDraft);
    elements.mapCatalogItems.innerHTML = available.length
        ? available.map((item) => `<button type="button" data-map-add="${escapeHtml(catalogKey(item.tipo, item.id))}">
            <span data-type="${escapeHtml(item.tipo)}">${escapeHtml(mapTypeLabel(item.tipo))}</span>
            <strong>${escapeHtml(item.codigo)}</strong><small>${escapeHtml(item.nombre)}</small>
        </button>`).join('')
        : '<p>Todos los recintos existentes ya están incorporados.</p>';
}

function renderEditor() {
    renderMapStage(elements.editorStage, state.mapDraft, state.mapEditing);
    renderMapCatalog();
    const selected = state.mapDraft.find((item) => item.id === state.mapSelectedId);
    elements.mapInspector.hidden = !state.mapEditing || !selected;
    elements.mapSelectionName.textContent = selected?.nombre || 'Ninguno';
    setMapZoom('editor', state.editorZoom);
}

function openMapDialog(editing) {
    const plant = state.snapshot?.planta;
    if (!plant) return;
    state.mapEditing = Boolean(editing && plant.puede_editar);
    state.mapDraft = JSON.parse(JSON.stringify(currentMapElements(plant)));
    state.mapSelectedId = null;
    elements.mapDialogTitle.textContent = state.mapEditing ? 'Editar plano operacional' : plant.nombre;
    elements.mapDialogHint.textContent = state.mapEditing
        ? 'Mueve, redimensiona y organiza la representación real de la planta'
        : 'Vista ampliada con estados operacionales en vivo';
    elements.mapCatalog.hidden = !state.mapEditing;
    elements.mapSave.hidden = !state.mapEditing;
    elements.mapDialog.classList.toggle('is-editing', state.mapEditing);
    renderEditor();
    elements.mapDialog.showModal();
}

function addCatalogItem(key) {
    const item = (state.snapshot?.planta?.catalogo || [])
        .find((candidate) => catalogKey(candidate.tipo, candidate.id) === key);
    if (!item) return;
    const sequence = state.mapDraft.length;
    const x = 400 + (sequence % 5) * 1800;
    const y = 400 + (Math.floor(sequence / 5) % 5) * 1500;
    state.mapDraft.push({
        id: crypto.randomUUID(),
        tipo: item.tipo,
        referencia_id: item.id,
        nombre: item.nombre || item.codigo,
        categoria: null,
        x,
        y,
        ancho: 1500,
        alto: 1100,
        rotacion: 0,
    });
    state.mapSelectedId = state.mapDraft.at(-1).id;
    renderEditor();
}

function selectedMapElement() {
    return state.mapDraft.find((item) => item.id === state.mapSelectedId);
}

async function saveMap() {
    elements.mapSave.disabled = true;
    elements.mapSave.textContent = 'Guardando…';
    try {
        const response = await api('/api/administracion/operacion-ahora/plano', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                version_esperada: state.snapshot.planta.version,
                nombre: state.snapshot.planta.nombre || 'Planta principal',
                elementos: state.mapDraft,
            }),
        });
        state.snapshot.planta = {
            ...state.snapshot.planta,
            ...response.data,
            configurado: true,
        };
        renderFacility(state.snapshot);
        elements.mapDialog.close();
        toast(`Plano guardado como versión ${response.data.version}.`);
    } catch (error) {
        toast(error.status === 409 ? `${error.message} Se conservaron tus cambios en pantalla.` : error.message, true);
    } finally {
        elements.mapSave.disabled = false;
        elements.mapSave.textContent = 'Guardar plano';
    }
}

function beginMapPointer(event) {
    if (!state.mapEditing || event.button !== 0) return;
    const node = event.target.closest('.operation-map-node');
    if (!node) return;
    const item = state.mapDraft.find((candidate) => candidate.id === node.dataset.id);
    if (!item) return;
    event.preventDefault();
    state.mapSelectedId = item.id;
    state.mapDrag = {
        id: item.id,
        mode: event.target.closest('.operation-map-node__resize') ? 'resize' : 'move',
        startX: event.clientX,
        startY: event.clientY,
        initial: { ...item },
    };
    renderEditor();
}

function moveMapPointer(event) {
    if (!state.mapDrag) return;
    const rect = elements.editorStage.getBoundingClientRect();
    const deltaX = (event.clientX - state.mapDrag.startX) / rect.width * 10_000;
    const deltaY = (event.clientY - state.mapDrag.startY) / rect.height * 10_000;
    const index = state.mapDraft.findIndex((item) => item.id === state.mapDrag.id);
    if (index < 0) return;
    state.mapDraft[index] = state.mapDrag.mode === 'resize'
        ? resizeElement(state.mapDrag.initial, deltaX, deltaY)
        : moveElement(state.mapDrag.initial, deltaX, deltaY);
    renderEditor();
}

function endMapPointer() {
    state.mapDrag = null;
}

byId('operationMapZoomOut').addEventListener('click', () => setMapZoom('map', state.mapZoom - .1));
byId('operationMapZoomIn').addEventListener('click', () => setMapZoom('map', state.mapZoom + .1));
byId('operationEditorZoomOut').addEventListener('click', () => setMapZoom('editor', state.editorZoom - .1));
byId('operationEditorZoomIn').addEventListener('click', () => setMapZoom('editor', state.editorZoom + .1));
byId('operationMapExpand').addEventListener('click', () => openMapDialog(false));
elements.mapEdit.addEventListener('click', () => openMapDialog(true));
byId('operationMapClose').addEventListener('click', () => elements.mapDialog.close());
elements.mapSave.addEventListener('click', () => void saveMap());
elements.mapCatalogItems.addEventListener('click', (event) => {
    const button = event.target.closest('[data-map-add]');
    if (button) addCatalogItem(button.dataset.mapAdd);
});
byId('operationMapZoneForm').addEventListener('submit', (event) => {
    event.preventDefault();
    const name = byId('operationMapZoneName').value.trim();
    if (!name) return;
    state.mapDraft.push({
        id: crypto.randomUUID(), tipo: 'zona', referencia_id: null, nombre: name,
        categoria: byId('operationMapZoneType').value, x: 500, y: 500,
        ancho: 2200, alto: 1400, rotacion: 0,
    });
    state.mapSelectedId = state.mapDraft.at(-1).id;
    event.target.reset();
    renderEditor();
});
byId('operationMapRotate').addEventListener('click', () => {
    const selected = selectedMapElement();
    if (!selected) return;
    const availableWidth = 10_000 - selected.x;
    const availableHeight = 10_000 - selected.y;
    [selected.ancho, selected.alto] = [Math.min(selected.alto, availableWidth), Math.min(selected.ancho, availableHeight)];
    selected.rotacion = (selected.rotacion + 90) % 360;
    renderEditor();
});
byId('operationMapRemove').addEventListener('click', () => {
    state.mapDraft = state.mapDraft.filter((item) => item.id !== state.mapSelectedId);
    state.mapSelectedId = null;
    renderEditor();
});
elements.editorStage.addEventListener('pointerdown', beginMapPointer);
window.addEventListener('pointermove', moveMapPointer);
window.addEventListener('pointerup', endMapPointer);
elements.mapDialog.addEventListener('click', (event) => {
    if (event.target === elements.mapDialog) elements.mapDialog.close();
});

elements.refresh.addEventListener('click', async () => {
    const success = await load();
    if (success && !state.poller) startPolling();
});

async function boot() {
    if (!state.token || !state.identity || !can('puede_consultar_panel_gerencial') || !hasModule('gerencia.panel')) {
        window.location.replace('/oficina/accesos');
        return;
    }

    const name = state.identity.nombre || state.identity.name || 'Usuario';
    setText('officeUserName', name);
    setText('officeUserRole', state.identity.perfil_acceso?.nombre || humanize(state.identity.rol || 'consulta'));
    setText('officeInitials', name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase());
    byId('officeLogoutButton')?.addEventListener('click', () => void logout());
    elements.app.classList.remove('is-hidden');

    if (!navigator.onLine) showConnectionIssue(new ApiError('El equipo no tiene conexión de red.'));
    const success = await load({ blocking: true, silent: true });
    if (success) startPolling();
}

void boot();
