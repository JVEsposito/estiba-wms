import { ActiveSession, Camera, Position, RecentMovement } from '../domain/dashboard';

const foliosByPosition: Record<string, string> = {
  '01-03': 'FOL-1188',
  '01-06': 'FOL-2299',
  '02-02': 'FOL-4455',
  '02-05': 'FOL-7788',
  '02-07': 'FOL-3322',
  '03-01': 'FOL-5544',
  '03-03': 'FOL-2211',
  '03-05': 'FOL-9900',
  '03-08': 'FOL-1133',
  '04-03': 'FOL-7721',
  '04-05': 'FOL-8812',
  '04-07': 'FOL-6630',
};

function buildPositions(seed = 0): Position[] {
  return Array.from({ length: 4 }, (_, levelIndex) => {
    const level = levelIndex + 1;

    return Array.from({ length: 8 }, (_, columnIndex) => {
      const column = columnIndex + 1;
      const label = `${String(level).padStart(2, '0')}-${String(column).padStart(2, '0')}`;
      const mockedFolio = foliosByPosition[label];
      const generatedFolio = (level * 7 + column + seed) % 5 === 0
        ? `FOL-${seed + level}${column}0${level}`
        : null;

      return {
        id: `${seed}-${label}`,
        label,
        level,
        column,
        folio: mockedFolio ?? generatedFolio,
      };
    });
  }).flat();
}

export const cameras: Camera[] = [
  buildCamera('camara-01', 'Cámara 01', 100),
  buildCamera('camara-02', 'Cámara 02', 200, 'María P.'),
  buildCamera('camara-03', 'Cámara 03', 0),
];

function buildCamera(id: string, name: string, seed: number, editor?: string): Camera {
  const positions = buildPositions(seed);

  return {
    id,
    name,
    occupied: positions.filter((position) => position.folio !== null).length,
    capacity: positions.length,
    status: editor ? 'editing' : 'available',
    editor,
    positions,
  };
}

export const recentMovements: RecentMovement[] = [
  {
    id: 'movement-1',
    time: '10:21',
    type: 'ingreso',
    folio: 'FOL-1188',
    source: 'Recepción',
    destination: 'Cámara 03 · 01-03',
  },
  {
    id: 'movement-2',
    time: '10:14',
    type: 'traslado',
    folio: 'FOL-4455',
    source: 'Cámara 02 · 02-02',
    destination: 'Cámara 03 · 02-05',
  },
  {
    id: 'movement-3',
    time: '10:07',
    type: 'ingreso',
    folio: 'FOL-7721',
    source: 'Recepción',
    destination: 'Cámara 03 · 04-03',
  },
];

export const activeSession: ActiveSession = {
  operator: 'Juan R.',
  elapsed: '02:15:47',
};
