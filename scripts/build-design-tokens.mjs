import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const root = new URL('../', import.meta.url);
const tokens = JSON.parse(await readFile(new URL('design/estiba.tokens.json', root), 'utf8'));
const kebab = (value) => value.replace(/[A-Z]/g, (letter) => `-${letter.toLowerCase()}`);
const entries = [];

function flatten(value, path = []) {
    for (const [key, item] of Object.entries(value)) {
        const next = [...path, key];
        if (typeof item === 'object' && item !== null) {
            flatten(item, next);
            continue;
        }
        const unit = ['fontWeight', 'lineHeight'].includes(next[0]) ? '' : 'px';
        const rendered = next[0] === 'fontSize'
            ? `${item / 16}rem`
            : typeof item === 'number' ? `${item}${unit}` : item;
        entries.push(`    --eui-${next.map(kebab).join('-')}: ${rendered};`);
    }
}

flatten(tokens);

const outputs = new Map([
    ['resources/css/estiba-tokens.css', [
        '/* Generado desde design/estiba.tokens.json. Ejecutar npm run design:tokens. */',
        '.estiba-ui {', ...entries, '}', '',
    ].join('\n')],
    ['mobile/src/theme/estibaTokens.ts', [
        '// Generado desde design/estiba.tokens.json. Ejecutar npm run design:tokens.',
        `export const estibaTokens = ${JSON.stringify(tokens, null, 2)} as const;`,
        '',
        'export type EstibaTone = keyof typeof estibaTokens.signal;',
        '',
    ].join('\n')],
]);

for (const [path, expected] of outputs) {
    const url = new URL(path, root);
    if (process.argv.includes('--check')) {
        const current = await readFile(url, 'utf8').catch(() => '');
        if (current !== expected) {
            process.stderr.write(`Tokens desactualizados: ${path}. Ejecutar npm run design:tokens.\n`);
            process.exitCode = 1;
        }
    } else {
        await writeFile(url, expected);
        process.stdout.write(`Generado ${fileURLToPath(url)}\n`);
    }
}
