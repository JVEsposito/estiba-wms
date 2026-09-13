import type { OperationalTask } from './operationalTasks';

export type OperatorQueueSource = 'mine' | 'available';

export type OperatorQueueItem = {
  source: OperatorQueueSource;
  task: OperationalTask;
};

export type OperatorTaskHomeState = {
  next: OperatorQueueItem | null;
  mine: OperatorQueueItem[];
  available: OperatorQueueItem[];
};

/**
 * Construye la portada sin reordenar las prioridades entregadas por la API.
 * Una maniobra pausada conserva el primer lugar porque sigue bajo propiedad y,
 * si ya hubo movimiento físico, también mantiene custodia operacional.
 */
export function buildOperatorTaskHome(
  mine: OperationalTask[],
  available: OperationalTask[],
): OperatorTaskHomeState {
  const mineItems = dedupe(mine)
    .map((task) => ({ task, source: 'mine' as const }));
  const availableItems = dedupe(available)
    .filter((task) => task.maniobra?.estado !== 'pausada_discrepancia')
    .filter((task) => !mineItems.some((item) => item.task.id === task.id))
    .map((task) => ({ task, source: 'available' as const }));
  const paused = mineItems.find((item) => item.task.maniobra?.estado === 'pausada_discrepancia');
  const inMovement = mineItems.find((item) => item.task.estado === 'en_proceso');
  const next = paused ?? inMovement ?? mineItems[0] ?? availableItems[0] ?? null;

  return {
    next,
    mine: withoutCurrent(mineItems, next),
    available: withoutCurrent(availableItems, next),
  };
}

function dedupe(tasks: OperationalTask[]) {
  return [...new Map(tasks.map((task) => [task.id, task])).values()];
}

function withoutCurrent(items: OperatorQueueItem[], current: OperatorQueueItem | null) {
  return current ? items.filter((item) => item.task.id !== current.task.id) : items;
}
