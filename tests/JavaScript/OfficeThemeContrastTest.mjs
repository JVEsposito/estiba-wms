import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const css = readFileSync(new URL('../../resources/css/office-corporate.css', import.meta.url), 'utf8');

function luminance(hex) {
    const channels = [1, 3, 5]
        .map((index) => Number.parseInt(hex.slice(index, index + 2), 16) / 255)
        .map((channel) => (channel <= 0.04045
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4));

    return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
}

function contrast(foreground, background) {
    const [lighter, darker] = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
    return (lighter + 0.05) / (darker + 0.05);
}

function themeVariables(selector) {
    const start = css.lastIndexOf(selector);
    assert.notEqual(start, -1, `No se encontró ${selector}`);
    const openingBrace = css.indexOf('{', start);
    const closingBrace = css.indexOf('}', openingBrace);
    const block = css.slice(openingBrace + 1, closingBrace);

    return Object.fromEntries(
        [...block.matchAll(/--([\w-]+):\s*(#[\da-fA-F]{6})\s*;/g)]
            .map((match) => [match[1], match[2]]),
    );
}

test('los temas claro y oscuro mantienen contraste AA en contenido y selección', () => {
    const themes = [
        themeVariables(':root[data-office-theme="light-professional"]'),
        themeVariables(':root[data-office-theme="dark-industrial"]'),
    ];

    themes.forEach((variables) => {
        assert.ok(contrast(variables.text, variables['corporate-canvas']) >= 4.5);
        assert.ok(contrast(variables.text, variables['corporate-card']) >= 4.5);
        assert.ok(contrast(variables.muted, variables['corporate-card']) >= 4.5);
        assert.ok(contrast(variables['corporate-blue'], variables['surface-selected']) >= 4.5);
        assert.ok(contrast('#ffffff', variables['corporate-blue-dark']) >= 4.5);
        assert.ok(contrast(variables['text-secondary'], variables['button-secondary']) >= 4.5);
        assert.ok(contrast(variables['text-strong'], variables['surface-muted']) >= 4.5);
        assert.ok(contrast(variables['text-subtle'], variables['kpi-surface']) >= 4.5);
        assert.ok(contrast(variables['warning-text'], variables['warning-bg']) >= 4.5);
        assert.ok(contrast(variables['success-text'], variables['surface-muted']) >= 4.5);
    });
});
