import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

// Regresión: la regla genérica de identidad "Manifiesto" que fija border-top-color en
// los KPIs de Oficina (frost) puede coincidir con los mismos <article> que los KPIs de
// Romana (.weighbridge-kpis), cuyo border-top-color es SIGNIFICADO (warning/naranja/
// success por índice), no identidad. Con igual especificidad y apareciendo después en
// el archivo, la regla genérica le ganaba la cascada — un CSS perfectamente válido
// (csstree-validator no lo detecta) que igual borraba el significado. Verificado
// además con Playwright (getComputedStyle real, ambos temas) antes de escribir este
// test: en main sin ningún parche de esta rama ya perdían frente a una regla previa,
// no relacionada con "Manifiesto" — hallazgo aparte, corregido junto con este.
//
// Este test simula la cascada real (especificidad + orden de aparición, con :is()/:not()
// resueltos según el spec — el máximo de sus alternativas, no la suma) para
// .weighbridge-kpis > article:nth-child(1..4) contra TODAS las reglas del archivo que
// fijan border-top(-color) y podrían aplicarles, y verifica qué color gana.

const css = readFileSync(new URL('../../resources/css/office-corporate.css', import.meta.url), 'utf8')
    .replace(/\/\*[\s\S]*?\*\//g, '');

function countOwn(str) {
    const ids = (str.match(/#[\w-]+/g) || []).length;
    const classes = (str.match(/\.[\w-]+/g) || []).length
        + (str.match(/\[[^\]]+\]/g) || []).length
        + (str.match(/:[\w-]+/g) || []).length;
    const elements = (str.match(/(?:^|[\s>+~])([a-zA-Z][\w-]*)/g) || [])
        .filter((m) => !m.trim().startsWith('.') && !m.trim().startsWith('#')).length;
    return [ids, classes, elements];
}
function addSpec(a, b) { return [a[0] + b[0], a[1] + b[1], a[2] + b[2]]; }
function maxSpec(a, b) {
    for (let i = 0; i < 3; i += 1) { if (a[i] !== b[i]) return a[i] > b[i] ? a : b; }
    return a;
}

// :where() no aporta nada; :not(X) aporta la especificidad de X (se desenvuelve, no se
// descarta); :is(A, B, C) aporta la de su alternativa MÁS específica, no la suma de
// todas — a diferencia del cálculo de EstibaDarkModeSpecificityTest.mjs, aquí sí hace
// falta: los selectores de este archivo combinan :is() de varias alternativas con
// :not() anidado dentro de cada una.
function specificity(selectorRaw) {
    let s = selectorRaw.replace(/:where\([^)]*\)/g, '');
    s = s.replace(/:not\(([^()]*)\)/g, '$1');
    let groupSpec = [0, 0, 0];
    s = s.replace(/:is\(([^()]*)\)/g, (_, inner) => {
        const alternatives = inner.split(',').map((a) => a.trim()).filter(Boolean);
        const best = alternatives.reduce((acc, alt) => maxSpec(acc, countOwn(alt)), [0, 0, 0]);
        groupSpec = addSpec(groupSpec, best);
        return '';
    });
    return addSpec(countOwn(s), groupSpec);
}

function compare(a, b) {
    for (let i = 0; i < 3; i += 1) if (a[i] !== b[i]) return a[i] - b[i];
    return 0;
}

function splitTopLevel(str, sep) {
    const parts = [];
    let depth = 0;
    let current = '';
    for (const ch of str) {
        if (ch === '(') depth += 1;
        if (ch === ')') depth -= 1;
        if (ch === sep && depth === 0) { parts.push(current); current = ''; } else current += ch;
    }
    parts.push(current);
    return parts;
}

const ruleRe = /([^{}]+)\{([^{}]*)\}/g;
const candidates = [];
let match;
let order = 0;
while ((match = ruleRe.exec(css))) {
    order += 1;
    const [, selectorListRaw, body] = match;
    const colorMatch = body.match(/border-top(?:-color)?\s*:\s*([^;]+);/);
    if (!colorMatch) continue;
    const value = colorMatch[1].replace(/!important/, '').trim();
    const color = value.includes(' ') ? value.split(/\s+/).pop() : value; // shorthand: último token = color
    for (const selector of splitTopLevel(selectorListRaw, ',').map((s) => s.trim().replace(/\s+/g, ' '))) {
        if (!selector) continue;
        if (/::(before|after)/.test(selector)) continue; // otra caja, no el <article> mismo
        const mentionsWeighbridge = /\.weighbridge-kpis/.test(selector);
        const isGenericKpi = /\[class[$*]="-(metrics|kpis)/.test(selector);
        const excludesWeighbridge = /:not\(\.weighbridge-kpis\)/.test(selector);
        if (excludesWeighbridge) continue; // no matchea Romana — correcto, no es candidato
        if (!mentionsWeighbridge && !isGenericKpi) continue; // no es una regla de KPIs
        candidates.push({ selector, color, order, specificity: specificity(selector) });
    }
}

assert.ok(candidates.length >= 5, `Se esperaban varias reglas candidatas para los KPIs de Oficina/Romana, se encontraron ${candidates.length} — revisar el parseo de este test si office-corporate.css cambió de estructura.`);

function winnerFor(nth) {
    const applicable = candidates.filter((c) => {
        const nthMatch = c.selector.match(/:nth-child\((\d+)\)/);
        return nthMatch ? Number(nthMatch[1]) === nth : true; // sin nth-child: regla genérica, aplica a los 4
    });
    return applicable.reduce((winner, current) => {
        if (!winner) return current;
        const cmp = compare(current.specificity, winner.specificity);
        if (cmp > 0) return current;
        if (cmp === 0 && current.order > winner.order) return current;
        return winner;
    }, null);
}

test('Romana: el color de identidad (frost) no borra los tonos semánticos de sus KPIs', () => {
    const winners = { 1: winnerFor(1), 2: winnerFor(2), 3: winnerFor(3), 4: winnerFor(4) };
    assert.ok(winners[1] && /--frost\)/.test(winners[1].color), `KPI 1 (neutro) debería resolver a var(--frost); ganó "${winners[1]?.color}" (${winners[1]?.selector.slice(0, 60)})`);
    assert.ok(winners[2] && /--warning-text\)/.test(winners[2].color), `KPI 2 debería resolver a var(--warning-text); ganó "${winners[2]?.color}" (${winners[2]?.selector.slice(0, 60)})`);
    assert.ok(winners[3] && winners[3].color.replace(/"/g, '') === '#d27d49', `KPI 3 debería resolver a #d27d49; ganó "${winners[3]?.color}" (${winners[3]?.selector.slice(0, 60)})`);
    assert.ok(winners[4] && /--success-text\)/.test(winners[4].color), `KPI 4 debería resolver a var(--success-text); ganó "${winners[4]?.color}" (${winners[4]?.selector.slice(0, 60)})`);
});
