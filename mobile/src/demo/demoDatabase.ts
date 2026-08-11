import * as Crypto from 'expo-crypto';
import type { SQLiteDatabase } from 'expo-sqlite';

import {
  createInitialOperationalState,
  DEMO_OPERATIONAL_STATE_KEY,
  DemoOperationalState,
} from './demoOperationalSeed';
import {
  DEMO_MASTER_CATALOG_VERSION,
  DEMO_MASTER_SEED,
  DemoMasterCategory,
} from './demoMasterCatalog';

const DATABASE_NAME = 'estiba-wms-demo.db';
const SEED_VERSION = '3';

export type DemoDatabaseExecutor = Pick<
  SQLiteDatabase,
  'execAsync' | 'getAllAsync' | 'getFirstAsync' | 'runAsync'
>;

export type DemoClient = {
  id: string;
  code: string;
  name: string;
  folioPrefix: string;
  active: boolean;
  createdAt: string;
};

export type DemoFolio = {
  id: string;
  number: string;
  clientId: string;
  clientCode: string;
  clientName: string;
  species: string;
  variety: string;
  boxes: number;
  status: string;
  createdAt: string;
};

export type DemoMasterRecord = {
  id: string;
  category: DemoMasterCategory;
  code: string;
  name: string;
  detail: string;
  active: boolean;
  source: 'preloaded' | 'local';
  createdAt: string;
};

export type DemoDataset = {
  clients: DemoClient[];
  folios: DemoFolio[];
  masters: DemoMasterRecord[];
  activeMasters: number;
  localMasters: number;
  activeLoads: number;
  auditEntries: number;
  operationalMovements: number;
};

export type CreateDemoClientInput = {
  code: string;
  name: string;
  folioPrefix: string;
};

export type CreateDemoFolioInput = {
  number: string;
  clientId: string;
  species: string;
  variety: string;
  boxes: number;
};

export type CreateDemoMasterInput = {
  category: DemoMasterCategory;
  code: string;
  name: string;
  detail: string;
};

type ClientRow = {
  id: string;
  code: string;
  name: string;
  folio_prefix: string;
  active: number;
  created_at: string;
};

type FolioRow = {
  id: string;
  number: string;
  client_id: string;
  client_code: string;
  client_name: string;
  species: string;
  variety: string;
  boxes: number;
  status: string;
  created_at: string;
};

type MasterRow = {
  id: string;
  category: DemoMasterCategory;
  code: string;
  name: string;
  detail: string;
  active: number;
  source: 'preloaded' | 'local';
  created_at: string;
};

let databasePromise: Promise<SQLiteDatabase> | null = null;
let initializationPromise: Promise<void> | null = null;

function database(): Promise<SQLiteDatabase> {
  databasePromise ??= import('expo-sqlite')
    .then(({ openDatabaseAsync }) => openDatabaseAsync(DATABASE_NAME));
  return databasePromise;
}

function nowIso(): string {
  return new Date().toISOString();
}

function required(value: string, label: string): string {
  const normalized = value.trim();
  if (!normalized) throw new Error(`${label} es obligatorio.`);
  return normalized;
}

function normalizedCode(value: string, label: string): string {
  return required(value, label).toUpperCase().replace(/\s+/g, '-');
}

export async function writeDemoAudit(
  executor: DemoDatabaseExecutor,
  action: string,
  entityType: string,
  entityId: string,
  detail: string,
): Promise<void> {
  await executor.runAsync(
    `INSERT INTO demo_audit (id, action, entity_type, entity_id, detail, created_at)
     VALUES (?, ?, ?, ?, ?, ?)`,
    Crypto.randomUUID(),
    action,
    entityType,
    entityId,
    detail,
    nowIso(),
  );
}

async function seedClients(executor: DemoDatabaseExecutor, createdAt: string): Promise<void> {
  const clients = [
    ['client-demo-01', 'AGRO-SUR', 'Agrícola Sur Demo', 'AS'],
    ['client-demo-02', 'FRUTOS-ANDINOS', 'Frutos Andinos Demo', 'FA'],
    ['client-demo-03', 'PACKING-CENTRAL', 'Packing Central Demo', 'PC'],
  ] as const;

  for (const [id, code, name, prefix] of clients) {
    await executor.runAsync(
      `INSERT OR IGNORE INTO demo_clients (id, code, name, folio_prefix, active, created_at)
       VALUES (?, ?, ?, ?, 1, ?)`,
      id,
      code,
      name,
      prefix,
      createdAt,
    );
  }
}

async function seedFolios(executor: DemoDatabaseExecutor, createdAt: string): Promise<void> {
  const folios = [
    ['folio-demo-01', 'DEMO-000001', 'client-demo-01', 'Cereza', 'Lapins', 120, 'pendiente'],
    ['folio-demo-02', 'DEMO-000002', 'client-demo-01', 'Cereza', 'Santina', 96, 'validado'],
    ['folio-demo-03', 'DEMO-000003', 'client-demo-02', 'Arándano', 'Legacy', 80, 'pendiente'],
  ] as const;

  for (const [id, number, clientId, species, variety, boxes, status] of folios) {
    await executor.runAsync(
      `INSERT INTO demo_folios
        (id, number, client_id, species, variety, boxes, status, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      id,
      number,
      clientId,
      species,
      variety,
      boxes,
      status,
      createdAt,
    );
  }
}

async function seedMasterCatalog(executor: DemoDatabaseExecutor, createdAt: string): Promise<void> {
  for (const record of DEMO_MASTER_SEED) {
    await executor.runAsync(
      `INSERT OR IGNORE INTO demo_master_records
        (id, category, code, name, detail, active, source, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, 1, 'preloaded', ?, ?)`,
      record.id,
      record.category,
      record.code,
      record.name,
      record.detail,
      createdAt,
      createdAt,
    );
  }

  await executor.runAsync(
    `INSERT OR REPLACE INTO demo_meta (key, value) VALUES ('master_catalog_version', ?)`,
    DEMO_MASTER_CATALOG_VERSION,
  );
}

async function seedOperationalScenario(
  executor: DemoDatabaseExecutor,
  createdAt: string,
): Promise<void> {
  await seedClients(executor, createdAt);
  await seedFolios(executor, createdAt);

  const operationalState = createInitialOperationalState();
  await executor.runAsync(
    `INSERT INTO demo_operational_state (key, state_json, updated_at)
     VALUES (?, ?, ?)`,
    DEMO_OPERATIONAL_STATE_KEY,
    JSON.stringify(operationalState),
    createdAt,
  );
  await seedLoads(executor, operationalState, createdAt);
}

async function seed(executor: DemoDatabaseExecutor): Promise<void> {
  const createdAt = nowIso();
  await seedMasterCatalog(executor, createdAt);
  await seedOperationalScenario(executor, createdAt);
  await executor.runAsync(
    'INSERT INTO demo_meta (key, value) VALUES (?, ?)',
    'seed_version',
    SEED_VERSION,
  );
}

async function seedLoads(
  executor: DemoDatabaseExecutor,
  state: DemoOperationalState,
  createdAt: string,
): Promise<void> {
  const existing = await executor.getFirstAsync<{ total: number }>(
    'SELECT COUNT(*) AS total FROM demo_loads',
  );
  if ((existing?.total ?? 0) > 0) return;

  const selected = state.plans
    .filter((plan) => plan.acceso.modo !== 'solo_lectura')
    .slice(0, 2)
    .flatMap((plan) => plan.posiciones
      .filter((position) => position.folio)
      .slice(0, 2)
      .map((position) => position.folio!));
  if (!selected.length) return;

  const loadId = 'load-demo-01';
  await executor.runAsync(
    `INSERT INTO demo_loads
      (id, code, external_order, status, priority, observation, version,
       published_at, cancelled_at, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)`,
    loadId,
    'CAR-000001',
    'OC-DEMO-001',
    'published',
    'alta',
    'Carga ficticia inicial para demostración',
    1,
    createdAt,
    createdAt,
    createdAt,
  );

  for (const folio of selected) {
    await executor.runAsync(
      `INSERT INTO demo_load_assignments
        (id, load_id, folio_id, folio_number, status, dock_id,
         incident_type, incident_description, assigned_at, sent_at)
       VALUES (?, ?, ?, ?, 'pending', NULL, NULL, NULL, ?, NULL)`,
      `assignment-${loadId}-${folio.id}`,
      loadId,
      folio.id,
      folio.numero_folio,
      createdAt,
    );
  }

  await executor.runAsync(
    `INSERT INTO demo_notifications
      (id, type, severity, title, message, load_id, folio_id, data_json,
       read_at, confirmed_at, created_at, updated_at)
     VALUES (?, 'carga_publicada', 'advertencia', ?, ?, ?, NULL, ?, NULL, NULL, ?, ?)`,
    'notification-demo-load-01',
    'Nueva carga publicada',
    `CAR-000001 está disponible con ${selected.length} folios.`,
    loadId,
    JSON.stringify({ priority: 'alta', total: selected.length }),
    createdAt,
    createdAt,
  );
  await executor.runAsync(
    `INSERT OR REPLACE INTO demo_meta (key, value) VALUES ('load_sequence', '1')`,
  );
}

export async function initializeDemoDatabase(): Promise<void> {
  if (initializationPromise) return initializationPromise;

  initializationPromise = (async () => {
    const db = await database();
    await db.execAsync(`
      PRAGMA journal_mode = WAL;
      PRAGMA foreign_keys = ON;

      CREATE TABLE IF NOT EXISTS demo_meta (
        key TEXT PRIMARY KEY NOT NULL,
        value TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS demo_clients (
        id TEXT PRIMARY KEY NOT NULL,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        folio_prefix TEXT NOT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS demo_folios (
        id TEXT PRIMARY KEY NOT NULL,
        number TEXT NOT NULL UNIQUE,
        client_id TEXT NOT NULL,
        species TEXT NOT NULL,
        variety TEXT NOT NULL,
        boxes INTEGER NOT NULL CHECK (boxes > 0),
        status TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY (client_id) REFERENCES demo_clients(id) ON DELETE CASCADE
      );

      CREATE TABLE IF NOT EXISTS demo_master_records (
        id TEXT PRIMARY KEY NOT NULL,
        category TEXT NOT NULL,
        code TEXT NOT NULL,
        name TEXT NOT NULL,
        detail TEXT NOT NULL DEFAULT '',
        active INTEGER NOT NULL DEFAULT 1,
        source TEXT NOT NULL DEFAULT 'local',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE (category, code)
      );

      CREATE TABLE IF NOT EXISTS demo_audit (
        id TEXT PRIMARY KEY NOT NULL,
        action TEXT NOT NULL,
        entity_type TEXT NOT NULL,
        entity_id TEXT NOT NULL,
        detail TEXT NOT NULL,
        created_at TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS demo_operational_state (
        key TEXT PRIMARY KEY NOT NULL,
        state_json TEXT NOT NULL,
        updated_at TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS demo_loads (
        id TEXT PRIMARY KEY NOT NULL,
        code TEXT NOT NULL UNIQUE,
        external_order TEXT,
        status TEXT NOT NULL,
        priority TEXT NOT NULL,
        observation TEXT,
        version INTEGER NOT NULL DEFAULT 1,
        published_at TEXT,
        cancelled_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS demo_load_assignments (
        id TEXT PRIMARY KEY NOT NULL,
        load_id TEXT NOT NULL,
        folio_id TEXT NOT NULL,
        folio_number TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        dock_id TEXT,
        incident_type TEXT,
        incident_description TEXT,
        assigned_at TEXT NOT NULL,
        sent_at TEXT,
        FOREIGN KEY (load_id) REFERENCES demo_loads(id) ON DELETE CASCADE
      );

      CREATE TABLE IF NOT EXISTS demo_load_operations (
        operation_id TEXT PRIMARY KEY NOT NULL,
        fingerprint TEXT NOT NULL,
        created_at TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS demo_notifications (
        id TEXT PRIMARY KEY NOT NULL,
        type TEXT NOT NULL,
        severity TEXT NOT NULL,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        load_id TEXT,
        folio_id TEXT,
        data_json TEXT,
        read_at TEXT,
        confirmed_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY (load_id) REFERENCES demo_loads(id) ON DELETE CASCADE
      );

      CREATE INDEX IF NOT EXISTS demo_folios_client_idx ON demo_folios(client_id);
      CREATE INDEX IF NOT EXISTS demo_master_records_category_idx
        ON demo_master_records(category, active, name);
      CREATE INDEX IF NOT EXISTS demo_audit_created_idx ON demo_audit(created_at);
      CREATE INDEX IF NOT EXISTS demo_loads_status_idx ON demo_loads(status, updated_at);
      CREATE INDEX IF NOT EXISTS demo_load_assignments_load_idx ON demo_load_assignments(load_id);
      CREATE UNIQUE INDEX IF NOT EXISTS demo_load_assignments_active_folio_unique
        ON demo_load_assignments(folio_id) WHERE status != 'cancelled';
      CREATE INDEX IF NOT EXISTS demo_notifications_created_idx ON demo_notifications(created_at);
    `);

    const version = await db.getFirstAsync<{ value: string }>(
      'SELECT value FROM demo_meta WHERE key = ?',
      'seed_version',
    );

    if (!version) {
      await db.withExclusiveTransactionAsync(async (transaction) => {
        await seed(transaction);
      });
    } else if (version.value !== SEED_VERSION) {
      await db.withExclusiveTransactionAsync(async (transaction) => {
        await seedMasterCatalog(transaction, nowIso());
        const operational = await transaction.getFirstAsync<{ state_json: string }>(
          'SELECT state_json FROM demo_operational_state WHERE key = ?',
          DEMO_OPERATIONAL_STATE_KEY,
        );
        if (operational?.state_json) {
          try {
            await seedLoads(
              transaction,
              JSON.parse(operational.state_json) as DemoOperationalState,
              nowIso(),
            );
          } catch {
            // Los datos maestros existentes se conservan aunque el ejemplo de cargas no pueda sembrarse.
          }
        }
        await transaction.runAsync(
          `INSERT OR REPLACE INTO demo_meta (key, value) VALUES ('seed_version', ?)`,
          SEED_VERSION,
        );
      });
    }

    await db.runAsync(
      `INSERT OR IGNORE INTO demo_operational_state (key, state_json, updated_at)
       VALUES (?, ?, ?)`,
      DEMO_OPERATIONAL_STATE_KEY,
      JSON.stringify(createInitialOperationalState()),
      nowIso(),
    );
  })().catch((error) => {
    initializationPromise = null;
    throw error;
  });

  return initializationPromise;
}

export async function loadDemoDataset(): Promise<DemoDataset> {
  await initializeDemoDatabase();
  const db = await database();

  const [clientRows, folioRows, masterRows, loadRow, auditRow, operationalRow] = await Promise.all([
    db.getAllAsync<ClientRow>('SELECT * FROM demo_clients ORDER BY name ASC'),
    db.getAllAsync<FolioRow>(`
      SELECT
        f.id,
        f.number,
        f.client_id,
        c.code AS client_code,
        c.name AS client_name,
        f.species,
        f.variety,
        f.boxes,
        f.status,
        f.created_at
      FROM demo_folios f
      INNER JOIN demo_clients c ON c.id = f.client_id
      ORDER BY f.created_at DESC, f.number DESC
    `),
    db.getAllAsync<MasterRow>(
      'SELECT * FROM demo_master_records ORDER BY category ASC, active DESC, name ASC',
    ),
    db.getFirstAsync<{ total: number }>(
      `SELECT COUNT(*) AS total FROM demo_loads WHERE status != 'cancelled'`,
    ),
    db.getFirstAsync<{ total: number }>('SELECT COUNT(*) AS total FROM demo_audit'),
    db.getFirstAsync<{ state_json: string }>(
      'SELECT state_json FROM demo_operational_state WHERE key = ?',
      DEMO_OPERATIONAL_STATE_KEY,
    ),
  ]);

  return {
    clients: clientRows.map((row) => ({
      id: row.id,
      code: row.code,
      name: row.name,
      folioPrefix: row.folio_prefix,
      active: row.active === 1,
      createdAt: row.created_at,
    })),
    folios: folioRows.map((row) => ({
      id: row.id,
      number: row.number,
      clientId: row.client_id,
      clientCode: row.client_code,
      clientName: row.client_name,
      species: row.species,
      variety: row.variety,
      boxes: row.boxes,
      status: row.status,
      createdAt: row.created_at,
    })),
    masters: masterRows.map((row) => ({
      id: row.id,
      category: row.category,
      code: row.code,
      name: row.name,
      detail: row.detail,
      active: row.active === 1,
      source: row.source,
      createdAt: row.created_at,
    })),
    activeMasters: masterRows.filter((row) => row.active === 1).length,
    localMasters: masterRows.filter((row) => row.source === 'local').length,
    activeLoads: loadRow?.total ?? 0,
    auditEntries: auditRow?.total ?? 0,
    operationalMovements: operationalMovementCount(operationalRow?.state_json),
  };
}

export async function createDemoMaster(input: CreateDemoMasterInput): Promise<void> {
  await initializeDemoDatabase();
  const db = await database();
  const category = required(input.category, 'La categoría') as DemoMasterCategory;
  const code = normalizedCode(input.code, 'El código');
  const name = required(input.name, 'El nombre');
  const detail = input.detail.trim();
  const existing = await db.getFirstAsync<{ id: string }>(
    'SELECT id FROM demo_master_records WHERE category = ? AND code = ?',
    category,
    code,
  );
  if (existing) throw new Error(`Ya existe ${code} en este maestro.`);

  const id = Crypto.randomUUID();
  const createdAt = nowIso();
  await db.withExclusiveTransactionAsync(async (transaction) => {
    await transaction.runAsync(
      `INSERT INTO demo_master_records
        (id, category, code, name, detail, active, source, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, 1, 'local', ?, ?)`,
      id,
      category,
      code,
      name,
      detail,
      createdAt,
      createdAt,
    );
    await writeDemoAudit(transaction, 'crear', `maestro:${category}`, id, `${code} · ${name}`);
  });
}

export async function setDemoMasterActive(id: string, active: boolean): Promise<void> {
  await initializeDemoDatabase();
  const db = await database();
  await db.withExclusiveTransactionAsync(async (transaction) => {
    const record = await transaction.getFirstAsync<Pick<MasterRow, 'category' | 'code' | 'name'>>(
      'SELECT category, code, name FROM demo_master_records WHERE id = ?',
      id,
    );
    if (!record) throw new Error('El registro maestro ya no existe.');
    await transaction.runAsync(
      'UPDATE demo_master_records SET active = ?, updated_at = ? WHERE id = ?',
      active ? 1 : 0,
      nowIso(),
      id,
    );
    await writeDemoAudit(
      transaction,
      active ? 'activar' : 'desactivar',
      `maestro:${record.category}`,
      id,
      `${record.code} · ${record.name}`,
    );
  });
}

export async function deleteDemoMaster(id: string): Promise<void> {
  await initializeDemoDatabase();
  const db = await database();
  await db.withExclusiveTransactionAsync(async (transaction) => {
    const record = await transaction.getFirstAsync<Pick<MasterRow, 'source' | 'category' | 'code' | 'name'>>(
      'SELECT source, category, code, name FROM demo_master_records WHERE id = ?',
      id,
    );
    if (!record) throw new Error('El registro maestro ya no existe.');
    if (record.source === 'preloaded') {
      throw new Error('Los registros precargados se desactivan; solo los creados en la tablet se pueden eliminar.');
    }
    await transaction.runAsync('DELETE FROM demo_master_records WHERE id = ?', id);
    await writeDemoAudit(
      transaction,
      'eliminar',
      `maestro:${record.category}`,
      id,
      `${record.code} · ${record.name}`,
    );
  });
}

export async function openDemoDatabase(): Promise<SQLiteDatabase> {
  await initializeDemoDatabase();
  return database();
}

function operationalMovementCount(serialized: string | undefined): number {
  if (!serialized) return 0;
  try {
    const state = JSON.parse(serialized) as Partial<DemoOperationalState>;
    return Array.isArray(state.movements) ? state.movements.length : 0;
  } catch {
    return 0;
  }
}

function locatedFolioNumbers(serialized: string | undefined): Set<string> {
  if (!serialized) return new Set();
  try {
    const state = JSON.parse(serialized) as Partial<DemoOperationalState>;
    return new Set((state.plans ?? []).flatMap((plan) => (
      plan.posiciones
        .map((position) => position.folio?.numero_folio)
        .filter((number): number is string => Boolean(number))
    )));
  } catch {
    return new Set();
  }
}

export async function createDemoClient(input: CreateDemoClientInput): Promise<void> {
  await initializeDemoDatabase();
  const db = await database();
  const code = normalizedCode(input.code, 'El código');
  const name = required(input.name, 'El nombre');
  const folioPrefix = normalizedCode(input.folioPrefix, 'El prefijo').slice(0, 6);
  const existing = await db.getFirstAsync<{ id: string }>(
    'SELECT id FROM demo_clients WHERE code = ?',
    code,
  );
  if (existing) throw new Error(`Ya existe un cliente con el código ${code}.`);

  const id = Crypto.randomUUID();
  await db.withExclusiveTransactionAsync(async (transaction) => {
    await transaction.runAsync(
      `INSERT INTO demo_clients (id, code, name, folio_prefix, active, created_at)
       VALUES (?, ?, ?, ?, 1, ?)`,
      id,
      code,
      name,
      folioPrefix,
      nowIso(),
    );
    await writeDemoAudit(transaction, 'crear', 'cliente', id, `${code} · ${name}`);
  });
}

export async function createDemoFolio(input: CreateDemoFolioInput): Promise<void> {
  await initializeDemoDatabase();
  const db = await database();
  const number = normalizedCode(input.number, 'El folio');
  const clientId = required(input.clientId, 'El cliente');
  const species = required(input.species, 'La especie');
  const variety = required(input.variety, 'La variedad');
  const boxes = Number(input.boxes);
  if (!Number.isInteger(boxes) || boxes <= 0) {
    throw new Error('Las cajas deben ser un número entero mayor que cero.');
  }

  const [client, existing, operational] = await Promise.all([
    db.getFirstAsync<{ id: string }>('SELECT id FROM demo_clients WHERE id = ?', clientId),
    db.getFirstAsync<{ id: string }>('SELECT id FROM demo_folios WHERE number = ?', number),
    db.getFirstAsync<{ state_json: string }>(
      'SELECT state_json FROM demo_operational_state WHERE key = ?',
      DEMO_OPERATIONAL_STATE_KEY,
    ),
  ]);
  if (!client) throw new Error('Selecciona un cliente existente.');
  if (existing) throw new Error(`El folio ${number} ya existe en esta demo.`);
  if (locatedFolioNumbers(operational?.state_json).has(number)) {
    throw new Error(`El folio ${number} ya existe en la operación de cámaras Demo.`);
  }

  const id = Crypto.randomUUID();
  await db.withExclusiveTransactionAsync(async (transaction) => {
    await transaction.runAsync(
      `INSERT INTO demo_folios
        (id, number, client_id, species, variety, boxes, status, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      id,
      number,
      clientId,
      species,
      variety,
      boxes,
      'pendiente',
      nowIso(),
    );
    await writeDemoAudit(transaction, 'crear', 'folio', id, `${number} · ${boxes} cajas`);
  });
}

export async function deleteDemoClient(id: string): Promise<void> {
  await initializeDemoDatabase();
  const db = await database();
  await db.withExclusiveTransactionAsync(async (transaction) => {
    const [folios, assigned, operational] = await Promise.all([
      transaction.getAllAsync<{ number: string }>(
        'SELECT number FROM demo_folios WHERE client_id = ?',
        id,
      ),
      transaction.getFirstAsync<{ id: string }>(
        `SELECT a.id
         FROM demo_load_assignments a
         INNER JOIN demo_loads l ON l.id = a.load_id
         INNER JOIN demo_folios f ON f.id = a.folio_id
         WHERE f.client_id = ? AND l.status != 'cancelled' AND a.status != 'cancelled'
         LIMIT 1`,
        id,
      ),
      transaction.getFirstAsync<{ state_json: string }>(
        'SELECT state_json FROM demo_operational_state WHERE key = ?',
        DEMO_OPERATIONAL_STATE_KEY,
      ),
    ]);
    const located = locatedFolioNumbers(operational?.state_json);
    if (folios.some((folio) => located.has(folio.number))) {
      throw new Error('No puedes eliminar un cliente que posee folios ubicados en cámaras Demo.');
    }
    if (assigned) {
      throw new Error('No puedes eliminar un cliente que posee folios asignados a una carga Demo.');
    }
    const result = await transaction.runAsync('DELETE FROM demo_clients WHERE id = ?', id);
    if (!result.changes) throw new Error('El cliente ya no existe.');
    await writeDemoAudit(transaction, 'eliminar', 'cliente', id, 'Cliente y sus folios asociados');
  });
}

export async function deleteDemoFolio(id: string): Promise<void> {
  await initializeDemoDatabase();
  const db = await database();
  await db.withExclusiveTransactionAsync(async (transaction) => {
    const [folio, assigned, operational] = await Promise.all([
      transaction.getFirstAsync<{ number: string }>('SELECT number FROM demo_folios WHERE id = ?', id),
      transaction.getFirstAsync<{ id: string }>(
        `SELECT a.id
         FROM demo_load_assignments a
         INNER JOIN demo_loads l ON l.id = a.load_id
         WHERE a.folio_id = ? AND l.status != 'cancelled' AND a.status != 'cancelled'
         LIMIT 1`,
        id,
      ),
      transaction.getFirstAsync<{ state_json: string }>(
        'SELECT state_json FROM demo_operational_state WHERE key = ?',
        DEMO_OPERATIONAL_STATE_KEY,
      ),
    ]);
    if (folio && locatedFolioNumbers(operational?.state_json).has(folio.number)) {
      throw new Error('No puedes eliminar un folio mientras permanezca ubicado en una cámara Demo.');
    }
    if (assigned) {
      throw new Error('No puedes eliminar un folio mientras esté asignado a una carga Demo.');
    }
    const result = await transaction.runAsync('DELETE FROM demo_folios WHERE id = ?', id);
    if (!result.changes) throw new Error('El folio ya no existe.');
    await writeDemoAudit(transaction, 'eliminar', 'folio', id, 'Folio demo eliminado');
  });
}

export async function resetDemoDatabase(): Promise<void> {
  await initializeDemoDatabase();
  const db = await database();
  await db.withExclusiveTransactionAsync(async (transaction) => {
    await transaction.execAsync(`
      DELETE FROM demo_audit;
      DELETE FROM demo_notifications;
      DELETE FROM demo_load_operations;
      DELETE FROM demo_load_assignments;
      DELETE FROM demo_loads;
      DELETE FROM demo_operational_state;
      DELETE FROM demo_folios;
      DELETE FROM demo_clients;
      DELETE FROM demo_master_records;
      DELETE FROM demo_meta;
    `);
    await seed(transaction);
    await writeDemoAudit(transaction, 'reiniciar', 'demo', 'local', 'Escenario inicial restaurado');
  });
}

export async function resetDemoOperationalData(): Promise<void> {
  await initializeDemoDatabase();
  const db = await database();
  await db.withExclusiveTransactionAsync(async (transaction) => {
    await transaction.execAsync(`
      DELETE FROM demo_audit;
      DELETE FROM demo_notifications;
      DELETE FROM demo_load_operations;
      DELETE FROM demo_load_assignments;
      DELETE FROM demo_loads;
      DELETE FROM demo_operational_state;
      DELETE FROM demo_folios;
      DELETE FROM demo_meta WHERE key = 'load_sequence';
    `);
    await seedOperationalScenario(transaction, nowIso());
    await writeDemoAudit(
      transaction,
      'reiniciar_operacion',
      'demo',
      'local',
      'Escenario operativo restaurado; maestros y clientes conservados',
    );
  });
}
