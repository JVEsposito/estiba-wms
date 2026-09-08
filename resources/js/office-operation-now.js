import { createOperationalPoller } from './shared/operational-poller';

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
    cameraRows: byId('operationCameraRows'),
    operatorList: byId('operationOperatorList'),
    tunnelList: byId('operationTunnelList'),
    incidentRows: byId('operationIncidentRows'),
    facilityMap: byId('operationFacilityMap'),
};

const state = {
    token: localStorage.getItem(tokenKey),
    identity: readJson(identityKey),
    snapshot: null,
    loading: false,
    poller: null,
    clockTimer: null,
    clockOffsetMs: 0,
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
        && Array.isArray(data.incidencias?.abiertas);

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

    const status = data.sincronizacion?.estado || 'sin_actividad';
    const tone = { aceptada: 'success', procesando: 'info', pendiente: 'warning', rechazada: 'critical', conflicto: 'critical' }[status] || 'neutral';
    elements.liveSignal.dataset.tone = tone;
    elements.liveText.textContent = status === 'sin_actividad' ? 'Sin actividad sincronizada' : `Sincronización ${humanize(status).toLowerCase()}`;
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

    setText('metricCameras', number(cameras.length));
    setText('metricCamerasDetail', `${number(productCameras.length)} de producto terminado`);
    setText('metricOccupancy', percent(occupancy));
    setText('metricOccupancyDetail', `${number(occupied)} de ${number(capacity)} posiciones PT`);
    setText('metricEnvironmental', number(environmentalDue));
    setText('metricEnvironmentalDetail', environmentalDue === 1 ? 'cámara requiere control' : 'cámaras requieren control');
    setText('metricOperators', number(operators.length));
    setText('metricOperatorsDetail', `${number(activeTasks)} con tarea vigente`);
    setText('metricPrecooling', number(precooling.procesos_activos));
    setText('metricPrecoolingDetail', `${number(precooling.procesos_fuera_objetivo)} fuera de objetivo`);
    setText('metricIncidents', number(incidents.total_abiertas));
    setText('metricIncidentsDetail', `${number(incidents.carga)} carga · ${number(incidents.maniobra)} maniobra`);

    byId('metricEnvironmental')?.closest('article')?.setAttribute('data-active', String(environmentalDue > 0));
    byId('metricIncidents')?.closest('article')?.setAttribute('data-active', String(Number(incidents.total_abiertas || 0) > 0));
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

function cameraArea(content) {
    return { productos: 'Producto terminado', materiales: 'Materiales', materia_prima: 'Materia prima' }[content] || humanize(content);
}

function renderCameras(cameras = []) {
    if (!cameras.length) {
        elements.cameraRows.innerHTML = `<tr><td colspan="3">${empty('Sin cámaras activas', 'No existen cámaras disponibles para la temporada actual.')}</td></tr>`;
        return;
    }

    elements.cameraRows.innerHTML = cameras.map((camera) => {
        const control = camera.control_ambiental;
        const occupancyTone = toneForOccupancy(camera.nivel_ocupacion);
        const environmentTone = toneForEnvironment(control?.estado);
        const readings = control?.temperaturas_c;
        const environment = control
            ? `${signal(humanize(control.estado), environmentTone)}${readings ? `<span class="operation-now-environment-readings"><span>I ${escapeHtml(temperature(readings.inicio))}</span><span>M ${escapeHtml(temperature(readings.medio))}</span><span>F ${escapeHtml(temperature(readings.fondo))}</span></span>` : ''}`
            : '<span class="operation-now-subtext">No aplica a esta cámara</span>';
        const lastReading = control?.capturado_at
            ? `<span class="operation-now-subtext">Registro ${escapeHtml(dateTime(control.capturado_at, { timeOnly: true }))} · vigente hasta ${escapeHtml(dateTime(control.vigente_hasta, { timeOnly: true }))}</span>`
            : '<span class="operation-now-subtext">Temperatura: SIN REGISTRO</span>';

        return `<tr>
            <td><span class="operation-now-code">${escapeHtml(camera.codigo)}</span><span class="operation-now-subtext">${escapeHtml(camera.nombre)}</span></td>
            <td>
                ${signal(`${number(camera.ocupadas)} / ${number(camera.capacidad_operativa)} · ${percent(camera.ocupacion_porcentaje)}`, occupancyTone)}
                <span class="operation-now-meter" data-tone="${occupancyTone}" style="--operation-progress:${clampedPercent(camera.ocupacion_porcentaje)}%"><i></i></span>
            </td>
            <td>${environment}${lastReading}<span class="operation-now-subtext">${escapeHtml(cameraArea(camera.contenido))}</span></td>
        </tr>`;
    }).join('');
}

function taskLocation(endpoint) {
    if (!endpoint?.camara) return 'Sin ubicación física';
    return [endpoint.camara.codigo, endpoint.posicion?.etiqueta].filter(Boolean).join(' · ');
}

function renderOperators(operators = []) {
    setText('operatorPanelCount', number(operators.length));
    if (!operators.length) {
        elements.operatorList.innerHTML = empty('Sin camareros activos', 'No existen sesiones de estiba abiertas en este momento.');
        return;
    }

    elements.operatorList.innerHTML = operators.map((operator) => {
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
                <span class="operation-now-operator__meta">${escapeHtml(operator.dispositivo?.codigo || 'Sin dispositivo')} · desde ${escapeHtml(dateTime(operator.sesion?.iniciada_at, { timeOnly: true }))}</span>
                <div class="operation-now-operator__status">${signal('En operación', 'success')}</div>
            </div>
            <div class="operation-now-operator__location"><span class="operation-now-operator__label">UBICACIÓN</span><p><strong>${escapeHtml(currentCamera?.codigo || 'Sin cámara')}</strong><span class="operation-now-subtext">${escapeHtml(currentCamera?.nombre || 'No informada')}</span></p></div>
            ${taskMarkup}
        </article>`;
    }).join('');
}

function renderFacility(data) {
    const cameras = data.camaras || [];
    const tunnels = data.prefrio?.tuneles || [];
    if (!cameras.length && !tunnels.length) {
        elements.facilityMap.innerHTML = empty('Sin infraestructura disponible', 'No existen cámaras ni túneles activos para representar.');
        return;
    }

    const cells = (items, kind) => items.map((item) => {
        const isCamera = kind === 'camera';
        const tone = isCamera ? toneForOccupancy(item.nivel_ocupacion) : toneForTunnel(item);
        const detail = isCamera
            ? percent(item.ocupacion_porcentaje)
            : (item.proceso_activo ? `${number(item.posiciones_ocupadas)} / ${number(item.capacidad_posiciones)}` : humanize(item.estado_operacional));

        return `<div class="operation-now-facility__cell" data-tone="${tone}">
            <strong>${escapeHtml(item.codigo)}</strong><small>${escapeHtml(detail)}</small>
        </div>`;
    }).join('');

    elements.facilityMap.innerHTML = `
        ${cameras.length ? `<div class="operation-now-facility__group"><strong>CÁMARAS</strong><div class="operation-now-facility__grid">${cells(cameras, 'camera')}</div></div>` : ''}
        ${tunnels.length ? `<div class="operation-now-facility__group"><strong>TÚNELES DE PREFRÍO</strong><div class="operation-now-facility__grid">${cells(tunnels, 'tunnel')}</div></div>` : ''}
        <p class="operation-now-facility__note">Esquema de estado; no representa coordenadas ni posición física.</p>`;
}

function tunnelProcess(tunnel) {
    const process = tunnel.proceso_activo;
    if (!process) {
        return `<div class="operation-now-tunnel__process">${signal(tunnel.operable ? 'Disponible para carga' : humanize(tunnel.estado_operacional), toneForTunnel(tunnel))}<small>Sin proceso activo</small></div>`;
    }

    const progress = process.avance_tiempo_objetivo_porcentaje;
    return `<div class="operation-now-tunnel__process">
        <strong>${escapeHtml(process.codigo)} · ${escapeHtml(humanize(process.estado))}</strong>
        <small>${number(process.folios_cargados)} folios · ${process.setpoint_c === null ? 'sin setpoint' : escapeHtml(temperature(process.setpoint_c))}</small>
        <span class="operation-now-meter" data-tone="${process.objetivo_excedido ? 'critical' : 'info'}" style="--operation-progress:${clampedPercent(progress)}%"><i></i></span>
        <small>${escapeHtml(duration(process.transcurridos_minutos))} transcurridos · objetivo ${escapeHtml(duration(process.duracion_objetivo_minutos))}${process.objetivo_excedido ? ` · excedido ${escapeHtml(duration(process.minutos_sobre_objetivo))}` : ''}</small>
    </div>`;
}

function renderTunnels(tunnels = []) {
    if (!tunnels.length) {
        elements.tunnelList.innerHTML = empty('Sin túneles configurados', 'No existe infraestructura de prefrío para mostrar.');
        return;
    }

    elements.tunnelList.innerHTML = tunnels.map((tunnel) => `<article class="operation-now-tunnel">
        <div class="operation-now-tunnel__heading">
            <div><h3>${escapeHtml(tunnel.codigo)}</h3><span class="operation-now-subtext">${escapeHtml(tunnel.nombre)}</span></div>
            ${signal(humanize(tunnel.estado_operacional), toneForTunnel(tunnel))}
        </div>
        <div class="operation-now-tunnel__capacity">
            <span><b>${number(tunnel.posiciones_ocupadas)}</b> ocupadas</span>
            <span><b>${number(tunnel.capacidad_posiciones)}</b> capacidad</span>
            <span><b>${percent(tunnel.ocupacion_porcentaje)}</b> uso</span>
        </div>
        ${tunnelProcess(tunnel)}
    </article>`).join('');
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
