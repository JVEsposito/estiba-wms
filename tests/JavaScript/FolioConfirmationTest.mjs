import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

import {
    expectedFolioDigits,
    folioConfirmationLength,
    matchesFolioConfirmation,
    operatorPinProblem,
    splitFolioForConfirmation,
} from '../../mobile/src/domain/folioConfirmation.ts';

const confirmation = await readFile(
    new URL('../../mobile/src/components/operator/OperatorFolioConfirmation.tsx', import.meta.url),
    'utf8',
);
const inbox = await readFile(
    new URL('../../mobile/src/components/OperationalTaskInbox.tsx', import.meta.url),
    'utf8',
);
const server = await readFile(
    new URL('../../app/Services/Estiba/ServicioConfirmacionInicioTarea.php', import.meta.url),
    'utf8',
);

test('los dígitos esperados son los últimos cuatro del folio, igual que en el servidor', () => {
    assert.equal(expectedFolioDigits('PT-2026-0045871'), '5871');
    assert.equal(expectedFolioDigits('ROLL-001'), '001');
    assert.equal(expectedFolioDigits('xyzabcd'), 'ABCD');
    assert.equal(folioConfirmationLength('ROLL-001'), 3);
    assert.match(server, /substr\(\$base, -4\)/);
});

test('se aceptan los últimos dígitos o el folio completo del escáner', () => {
    assert.ok(matchesFolioConfirmation('PT-2026-0045871', '5871'));
    assert.ok(matchesFolioConfirmation('PT-2026-0045871', ' pt-2026-0045871 '));
    assert.ok(!matchesFolioConfirmation('PT-2026-0045871', '5872'));
    assert.ok(!matchesFolioConfirmation('PT-2026-0045871', ''));
});

test('el folio se divide para ocultar en pantalla la parte que debe leerse en la etiqueta', () => {
    assert.deepEqual(splitFolioForConfirmation('PT-2026-0045871'), { lead: 'PT-2026-004', tail: '5871' });
    assert.match(confirmation, /'•'\.repeat\(tail\.length\)/);
});

test('el PIN rechaza formatos inválidos y secuencias predecibles', () => {
    assert.equal(operatorPinProblem('2580'), null);
    for (const pin of ['1234', '4321', '0000', '7777', '123', '12a4', '25801']) {
        assert.notEqual(operatorPinProblem(pin), null, pin);
    }
});

test('iniciar una tarea abre la confirmación y envía folio y PIN al servidor', () => {
    assert.match(inbox, /onStart=\{openStartConfirmation\}/);
    assert.match(inbox, /taskApi!\.start\(auth\.token, activeTask\.id, folioConfirmation\)/);
    assert.match(inbox, /reason\.status === 422/);
});
