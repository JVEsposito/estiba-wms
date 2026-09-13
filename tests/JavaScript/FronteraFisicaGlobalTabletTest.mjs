import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const inbox = await readFile(
    new URL('../../mobile/src/components/OperationalTaskInbox.tsx', import.meta.url),
    'utf8',
);
const api = await readFile(
    new URL('../../mobile/src/services/operationalTasksApi.ts', import.meta.url),
    'utf8',
);
const planner = await readFile(
    new URL('../../mobile/src/domain/rollingPlanner.ts', import.meta.url),
    'utf8',
);

test('la tablet calcula una sola frontera para tareas de planes distintos', () => {
    assert.match(inbox, /physicalFrontierSnapshot\(auth\.token\)/);
    assert.match(inbox, /anchorTask,[\s\S]*\.\.\.mine/);
    assert.doesNotMatch(inbox, /task\.plan\.id === anchorTask\.plan\.id/);
    assert.match(inbox, /item\.id === task\.id && item\.materializable/);
});

test('la tablet materializa contra el snapshot físico global', () => {
    assert.match(api, /TABLET_PLANNER_VERSION = 'rolling-global-2'/);
    assert.match(api, /'\/api\/frontera-fisica\/snapshot'/);
    assert.match(api, /'\/api\/frontera-fisica\/materializar'/);
    assert.match(inbox, /materializePhysicalFrontier\(/);
});

test('cada propuesta conserva la versión de su propio plan', () => {
    assert.match(planner, /taskSnapshot\.plan_version \?\? planVersion\(snapshot\)/);
    assert.match(planner, /item\.id === task\.id\)\?\.materializable !== false/);
});
