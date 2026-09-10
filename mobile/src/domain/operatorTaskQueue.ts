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
 * Una tarea físicamente iniciada siempre debe volver a ocupar el primer lugar.
 */
export function buildOperatorTaskHome(
  mine: OperationalTask[],
  available: OperationalTask[],
): OperatorTaskHomeState {
  const mineItems = dedupe(mine).map((task) => ({ task, source: 'mine' as const }));
  const availableItems = dedupe(available)
    .filter((task) => !mineItems.some((item) => item.task.id === task.id))
    .map((task) => ({ task, source: 'available' as const }));
  const inMovement = mineItems.find((item) => item.task.estado === 'en_proceso');
  const next = inMovement ?? mineItems[0] ?? availableItems[0] ?? null;

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
