import type {
  OperationalManeuverStep,
  OperationalTask,
  OperationalTaskEndpoint,
} from './operationalTasks';

export type OperatorPalletFact = {
  key: string;
  label: string;
  value: string;
};

export type OperatorPhysicalItemState = 'complete' | 'current' | 'pending';

export type OperatorPhysicalItem = {
  id: string;
  number: number;
  folio: string;
  origin: string;
  destination: string;
  state: OperatorPhysicalItemState;
};

export type OperatorPhysicalContext = {
  camera: string | null;
  band: number | null;
  position: number | null;
  level: number | null;
  currentPosition: string | null;
  resultingDepth: number | null;
  blockers: OperatorPhysicalItem[];
  target: OperatorPhysicalItem | null;
  returns: OperatorPhysicalItem[];
};

export function buildOperatorPalletFacts(task: OperationalTask): OperatorPalletFact[] {
  const candidates: OperatorPalletFact[] = [
    fact('cliente', 'Cliente', contextText(task.contexto, 'cliente')),
    fact('exportadora', 'Exportadora', cleanText(task.folio.exportadora) ?? contextText(task.contexto, 'exportadora')),
    fact('producto', 'Producto', contextText(task.contexto, 'producto')),
    fact('especie', 'Especie', contextText(task.contexto, 'especie')),
    fact('variedad', 'Variedad', cleanText(task.folio.variedad) ?? contextText(task.contexto, 'variedad')),
    fact('calibre', 'Calibre', cleanText(task.folio.calibre) ?? contextText(task.contexto, 'calibre')),
    fact('marca', 'Marca', cleanText(task.folio.marca) ?? contextText(task.contexto, 'marca')),
    fact('lote', 'Lote', contextText(task.contexto, 'lote')),
    fact('fecha_ingreso', 'Ingreso', formatDate(task.folio.fecha_ingreso)),
  ].filter((item): item is OperatorPalletFact => item !== null);

  const facts: OperatorPalletFact[] = [];
  const seenValues = new Set<string>();
  for (const candidate of candidates) {
    const normalized = candidate.value.toLocaleLowerCase();
    if (candidate.key === 'exportadora' && seenValues.has(normalized)) continue;
    facts.push(candidate);
    seenValues.add(normalized);
  }

  return facts;
}

export function buildOperatorPhysicalContext(task: OperationalTask): OperatorPhysicalContext {
  const current = task.secuencia_maniobra ?? task.maniobra?.secuencia_actual ?? 1;
  const steps = [...(task.maniobra?.pasos ?? [])]
    .sort((left, right) => left.secuencia - right.secuencia);
  const blockers = steps
    .filter((step) => step.tipo_paso === 'extraccion_temporal')
    .map((step) => physicalItem(step, current));
  const targetStep = steps.find((step) => step.tipo_paso === 'movimiento_permanente')
    ?? steps.find((step) => step.tipo_paso !== 'extraccion_temporal' && step.tipo_paso !== 'retorno_banda')
    ?? null;
  const returns = steps
    .filter((step) => step.tipo_paso === 'retorno_banda')
    .map((step) => physicalItem(step, current));
  const target = targetStep ? physicalItem(targetStep, current) : null;
  const endpoint = physicalAnchor(task, targetStep, blockers.length > 0 ? steps[0] : null);
  const position = endpoint?.posicion ?? null;

  return {
    camera: endpoint?.camara.nombre ?? null,
    band: position?.banda ?? null,
    position: position?.posicion ?? null,
    level: position?.nivel ?? null,
    currentPosition: positionLabel(endpoint),
    resultingDepth: contextNumber(task.contexto, 'profundidad_resultante'),
    blockers,
    target,
    returns,
  };
}

function physicalAnchor(
  task: OperationalTask,
  target: OperationalManeuverStep | null,
  firstBlocker: OperationalManeuverStep | null,
): OperationalTaskEndpoint {
  if (task.origen?.posicion) return task.origen;
  if (firstBlocker?.origen?.posicion) return firstBlocker.origen;
  if (target?.origen?.posicion) return target.origen;
  if (task.destino?.posicion) return task.destino;
  if (target?.destino?.posicion) return target.destino;
  return task.origen ?? task.destino ?? target?.origen ?? target?.destino ?? null;
}

function physicalItem(step: OperationalManeuverStep, current: number): OperatorPhysicalItem {
  return {
    id: step.id,
    number: step.secuencia,
    folio: step.folio?.numero_folio ?? 'Folio protegido',
    origin: positionLabel(step.origen) ?? 'Origen externo',
    destination: stepDestination(step),
    state: step.estado === 'completada' || step.secuencia < current
      ? 'complete'
      : step.secuencia === current
        ? 'current'
        : 'pending',
  };
}

function stepDestination(step: OperationalManeuverStep) {
  if (step.destino_logico?.tipo === 'anden') return step.destino_logico.nombre;
  if (step.tipo_paso === 'extraccion_temporal') return 'Bajo maniobra';
  return positionLabel(step.destino) ?? 'Destino por confirmar';
}

function positionLabel(endpoint: OperationalTaskEndpoint): string | null {
  if (!endpoint) return null;
  if (!endpoint.posicion) return endpoint.camara.nombre;
  const position = endpoint.posicion;
  const physical = position.etiqueta
    ?? `B${String(position.banda).padStart(2, '0')}-P${String(position.posicion).padStart(2, '0')}-N${position.nivel}`;
  return `${endpoint.camara.nombre} · ${physical}`;
}

function fact(key: string, label: string, value: string | null): OperatorPalletFact | null {
  return value ? { key, label, value } : null;
}

function cleanText(value: string | null | undefined): string | null {
  if (typeof value !== 'string') return null;
  const normalized = value.trim();
  return normalized === '' ? null : normalized;
}

function contextText(context: Record<string, unknown>, key: string): string | null {
  const value = context[key];
  if (typeof value === 'string') return cleanText(value);
  if (typeof value === 'number' && Number.isFinite(value)) return String(value);
  return null;
}

function contextNumber(context: Record<string, unknown>, key: string): number | null {
  const value = context[key];
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

function formatDate(value: string | null | undefined): string | null {
  if (!value) return null;
  const [date] = value.split('T');
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);
  return match ? `${match[3]}-${match[2]}-${match[1]}` : date;
}
