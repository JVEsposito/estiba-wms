import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const tokens = JSON.parse(await readFile(new URL('../../design/estiba.tokens.json', import.meta.url), 'utf8'));

function luminance(hex) {
    const channels = hex.slice(1).match(/../g).map((channel) => parseInt(channel, 16) / 255);
    const [r, g, b] = channels.map((c) => c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(a, b) {
    const values = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (values[0] + 0.05) / (values[1] + 0.05);
}

test('los textos de estados son legibles en su superficie y en filas blancas', () => {
    for (const [name, signal] of Object.entries(tokens.signal)) {
        for (const surface of [signal.surface, tokens.color.surface, tokens.color.canvas]) {
            assert.ok(contrast(signal.text, surface) >= 4.5, `Contraste insuficiente: ${name} / ${surface}`);
        }
    }
});

test('texto general, acciones y foco conservan contraste suficiente', () => {
    for (const foreground of [tokens.color.text, tokens.color.muted]) {
        for (const background of [tokens.color.surface, tokens.color.canvas, tokens.color.subtle]) {
            assert.ok(contrast(foreground, background) >= 4.5);
        }
    }
    for (const background of [tokens.color.primary, tokens.color.primaryHover, tokens.signal.success.text]) {
        assert.ok(contrast(tokens.color.onPrimary, background) >= 4.5);
    }
    assert.ok(contrast(tokens.color.onNavy, tokens.color.navy) >= 4.5);
    assert.ok(contrast(tokens.color.inputBorder, tokens.color.surface) >= 3);
    assert.ok(contrast(tokens.color.focus, tokens.color.canvas) >= 3);
});

test('el tamaño táctil y la escala de lectura no se reducen al compactar oficina', () => {
    assert.ok(tokens.density.touch.control >= 56);
    assert.ok(tokens.density.touch.row >= 56);
    assert.ok(tokens.density.compact.control < tokens.density.comfortable.control);
    assert.ok(tokens.fontSize.body >= 16);
    assert.ok(tokens.fontSize.small >= 14);
});
