import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const theme = await readFile(
    new URL('../../mobile/src/theme/operatorTheme.ts', import.meta.url),
    'utf8',
);
const header = await readFile(
    new URL('../../mobile/src/components/operator/OperatorHeader.tsx', import.meta.url),
    'utf8',
);
const primitives = await readFile(
    new URL('../../mobile/src/components/operator/OperatorPrimitives.tsx', import.meta.url),
    'utf8',
);
const workspace = await readFile(
    new URL('../../mobile/src/screens/OperationalWorkspaceScreen.tsx', import.meta.url),
    'utf8',
);
const inbox = await readFile(
    new URL('../../mobile/src/components/OperationalTaskInbox.tsx', import.meta.url),
    'utf8',
);

function luminance(hex) {
    const channels = hex.slice(1).match(/../g).map((channel) => parseInt(channel, 16) / 255);
    const [r, g, b] = channels.map((value) => (
        value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
    ));
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(a, b) {
    const values = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (values[0] + 0.05) / (values[1] + 0.05);
}

test('el tema del camarero conserva contraste AA en superficies operativas', () => {
    const pairs = [
        ['#102B3A', '#E7EEF2'],
        ['#526A78', '#E7EEF2'],
        ['#FFFFFF', '#123342'],
        ['#0B5688', '#D9EAF5'],
        ['#147A4D', '#DDF2E7'],
        ['#855600', '#FFF0CF'],
        ['#A92535', '#FBE4E7'],
        ['#FFFFFF', '#147A4D'],
        ['#FFFFFF', '#0F6EAD'],
    ];

    for (const [foreground, background] of pairs) {
        assert.ok(
            contrast(foreground, background) >= 4.5,
            `Contraste insuficiente: ${foreground} sobre ${background}`,
        );
        assert.match(theme, new RegExp(foreground.replace('#', '#')));
        assert.match(theme, new RegExp(background.replace('#', '#')));
    }
});

test('la base visual expone cabecera, estados, rutas y folios reutilizables', () => {
    assert.match(header, /useWindowDimensions/);
    assert.match(header, /Estado de conexión/);
    assert.match(primitives, /export function OperatorStatusBadge/);
    assert.match(primitives, /export function OperatorPriorityBadge/);
    assert.match(primitives, /export function OperatorEntityCode/);
    assert.match(primitives, /export function OperatorRouteLine/);
    assert.match(workspace, /<OperatorHeader/);
    assert.match(inbox, /<OperatorPriorityBadge/);
    assert.match(inbox, /<OperatorRouteLine/);
    assert.match(inbox, /<OperatorEntityCode/);
});

test('la bandeja responde a anchos compactos y mantiene controles táctiles', () => {
    assert.match(theme, /minimum: t\.density\.touch\.control/);
    assert.match(theme, /prominent: 64/);
    assert.match(inbox, /width < o\.breakpoint\.compact/);
    assert.match(inbox, /workspaceCompact/);
    assert.match(inbox, /minHeight: o\.touch\.minimum/);
    assert.doesNotMatch(inbox, /fontSize:\s*[0-9]\b/);
});
