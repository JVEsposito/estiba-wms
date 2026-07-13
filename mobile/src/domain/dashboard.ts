export type CameraStatus = 'available' | 'editing';

export type Position = {
  id: string;
  label: string;
  level: number;
  column: number;
  folio: string | null;
};

export type Camera = {
  id: string;
  name: string;
  occupied: number;
  capacity: number;
  status: CameraStatus;
  editor?: string;
  positions: Position[];
};

export type MovementType = 'ingreso' | 'reubicacion' | 'traslado';

export type RecentMovement = {
  id: string;
  time: string;
  type: MovementType;
  folio: string;
  source: string;
  destination: string;
};

export type ActiveSession = {
  operator: string;
  elapsed: string;
};
