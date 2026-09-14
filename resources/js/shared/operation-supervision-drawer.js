const DECISION_LABELS = {
    en_ejecucion: 'En ejecución',
    seleccionada: 'Seleccionada',
    alternativa: 'Alternativa',
    excluida_conflicto: 'Excluida por conflicto',
    fuera_frontera: 'Fuera de frontera',
    fuera_rollout: 'Fuera de rollout',
    fuera_planificador: 'Fuera del planificador',
};

const STATUS_LABELS = {
    pendiente: 'Pendiente',
    asumida: 'Asumida',
    en_proceso: 'En proceso',
    bloqueada: 'Bloqueada',
    completada: 'Completada',
    cancelada: 'Cancelada',
    en_ejecucion: 'En ejecución',
    pausada_discrepancia: 'Pausada por discrepancia',
};

const CONFLICT_LABELS = {
    folio: 'Pallet compartido con otra maniobra',
    posicion: 'Posición física compartida con otra maniobra',
    banda: 'Banda reservada por otra maniobra',
};

function text(value, fallback = 'Sin información') {
    const normalized = String(value ?? '').trim();
    return normalized || fallback;
}

function humanize(value, fallback = 'Sin información') {
    const normalized = text(value, fallback);
    return normalized
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);
}

function number(value) {
    return new Intl.NumberFormat('es-CL').format(Number(value || 0));
}

function conflictLabel(conflict) {
    const type = String(conflict?.recurso || '').split(':')[0];
    return CONFLICT_LABELS[type] || 'Recurso operacional compartido con otra maniobra';
}

export function operationLocationLabel(location, fallback = 'Sin ubicación') {
    const camera = location?.camara || location;
    const cameraName = text(camera?.nombre || camera?.codigo, '');
    const position = text(location?.posicion?.etiqueta, '');
    const parts = [cameraName, position].filter(Boolean);

    return parts.length ? parts.join(' · ') : fallback;
}

export function buildManeuverSupervisionModel(decision = {}) {
    const steps = Array.isArray(decision.pasos) && decision.pasos.length
        ? decision.pasos
        : (decision.paso_actual ? [decision.paso_actual] : []);
    const current = decision.paso_actual || steps.find((step) => !['completada', 'cancelada'].includes(step.estado)) || null;
    const conflicts = [...new Set((Array.isArray(decision.conflictos) ? decision.conflictos : []).map(conflictLabel))];

    return {
        title: text(decision.titulo, 'Maniobra sin título'),
        decision: DECISION_LABELS[decision.decision] || humanize(decision.decision),
        decisionTone: {
            en_ejecucion: 'success',
            seleccionada: 'info',
            excluida_conflicto: 'critical',
            fuera_frontera: 'warning',
            fuera_rollout: 'warning',
        }[decision.decision] || 'neutral',
        status: STATUS_LABELS[decision.estado] || humanize(decision.estado),
        priority: humanize(decision.prioridad),
        reason: text(decision.motivo, 'El ciclo no informó una razón adicional.'),
        objective: text(decision.objetivo?.titulo || humanize(decision.objetivo?.tipo, ''), 'Sin objetivo informado'),
        score: number(decision.puntaje),
        netBenefit: number(decision.beneficio_neto),
        movementCost: number(decision.costo_movimientos),
        operationalRisk: number(decision.riesgo_operacional),
        progress: {
            completed: number(decision.progreso?.pasos_completados),
            total: number(decision.progreso?.pasos_total ?? steps.length),
            percent: Math.max(0, Math.min(100, Number(decision.progreso?.porcentaje || 0))),
        },
        assignment: {
            responsible: text(decision.responsable?.nombre, 'Sin camarero asignado'),
            device: text(decision.dispositivo?.nombre || decision.dispositivo?.codigo, 'Sin tablet asignada'),
        },
        currentStep: current ? {
            sequence: number(current.secuencia),
            status: STATUS_LABELS[current.estado] || humanize(current.estado),
            instruction: text(current.instruccion || humanize(current.tipo_movimiento, ''), 'Sin instrucción'),
            folio: text(current.folio?.numero_folio, 'Sin folio'),
            route: `${operationLocationLabel(current.origen, 'Inicio')} → ${operationLocationLabel(current.destino, 'Sin destino')}`,
        } : null,
        conflicts,
        steps: steps.map((step, index) => ({
            sequence: number(step.secuencia || index + 1),
            status: STATUS_LABELS[step.estado] || humanize(step.estado),
            instruction: text(step.instruccion || humanize(step.tipo_movimiento, ''), 'Sin instrucción'),
            folio: text(step.folio?.numero_folio, 'Sin folio'),
            route: `${operationLocationLabel(step.origen, 'Inicio')} → ${operationLocationLabel(step.destino, 'Sin destino')}`,
            current: Boolean(current && step.id === current.id),
        })),
    };
}

function renderMetric(label, value) {
    return `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`;
}

function renderDrawer(model) {
    const current = model.currentStep
        ? `<section class="operation-supervision__current">
            <p>PASO ACTUAL · ${escapeHtml(model.currentStep.sequence)} DE ${escapeHtml(model.progress.total)}</p>
            <strong>${escapeHtml(model.currentStep.instruction)}</strong>
            <span>${escapeHtml(model.currentStep.folio)} · ${escapeHtml(model.currentStep.route)}</span>
            <small>${escapeHtml(model.currentStep.status)}</small>
        </section>`
        : `<section class="operation-supervision__current" data-empty="true">
            <p>PASO ACTUAL</p><strong>Sin movimiento activo</strong>
            <span>La maniobra no posee un paso pendiente de ejecución.</span>
        </section>`;

    const conflicts = model.conflicts.length
        ? `<ul>${model.conflicts.map((conflict) => `<li>${escapeHtml(conflict)}</li>`).join('')}</ul>`
        : '<p class="operation-supervision__empty">No se informan conflictos para esta decisión.</p>';

    const steps = model.steps.length
        ? `<ol class="operation-supervision__steps">${model.steps.map((step) => `<li data-current="${step.current ? 'true' : 'false'}">
            <span>${escapeHtml(step.sequence)}</span>
            <div><strong>${escapeHtml(step.instruction)}</strong><small>${escapeHtml(step.folio)} · ${escapeHtml(step.route)}</small></div>
            <em>${escapeHtml(step.status)}</em>
        </li>`).join('')}</ol>`
        : '<p class="operation-supervision__empty">No existen pasos publicados para esta maniobra.</p>';

    return `<div class="operation-supervision__identity">
        <span data-tone="${escapeHtml(model.decisionTone)}">${escapeHtml(model.decision)}</span>
        <h3>${escapeHtml(model.title)}</h3>
        <p>${escapeHtml(model.objective)}</p>
    </div>
    <dl class="operation-supervision__metrics">
        ${renderMetric('Estado', model.status)}
        ${renderMetric('Prioridad', model.priority)}
        ${renderMetric('Progreso', `${model.progress.completed}/${model.progress.total} · ${model.progress.percent}%`)}
        ${renderMetric('Beneficio neto', model.netBenefit)}
    </dl>
    ${current}
    <div class="operation-supervision__grid">
        <section><h4>Razón operacional</h4><p>${escapeHtml(model.reason)}</p></section>
        <section><h4>Asignación vigente</h4><p><strong>${escapeHtml(model.assignment.responsible)}</strong><br>${escapeHtml(model.assignment.device)}</p></section>
        <section><h4>Componentes del cálculo</h4><dl>
            ${renderMetric('Puntaje', model.score)}
            ${renderMetric('Movimientos', model.movementCost)}
            ${renderMetric('Riesgo', model.operationalRisk)}
        </dl></section>
        <section><h4>Conflictos informados</h4>${conflicts}</section>
    </div>
    <section class="operation-supervision__sequence"><h4>Secuencia física completa</h4>${steps}</section>
    <footer><strong>Consulta de supervisión</strong><span>Este detalle es de solo lectura. Las intervenciones seguras se habilitarán en el siguiente PR.</span></footer>`;
}

export function createManeuverSupervisionDrawer({
    dialog,
    content,
    closeButton,
    onClosed = () => {},
}) {
    if (!dialog || !content || !closeButton) {
        return { open() {}, update() {}, close() {} };
    }

    const update = (decision) => {
        content.innerHTML = renderDrawer(buildManeuverSupervisionModel(decision));
    };
    const close = () => {
        if (dialog.open) dialog.close();
    };

    closeButton.addEventListener('click', close);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });
    dialog.addEventListener('close', onClosed);

    return {
        open(decision) {
            update(decision);
            if (!dialog.open) dialog.showModal();
        },
        update,
        close,
    };
}
