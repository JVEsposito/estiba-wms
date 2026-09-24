import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import test from 'node:test';

// Regresión: Romana y Prefrío consultan anclas ocultas de la navegación heredada
// (officeValidationNav, officeContainerAccountsNav) y llaman classList sin guarda.
// Si el ancla no existe, el arranque de la oficina se interrumpe antes de cargar datos.

const jsDirectory = new URL('../../resources/js/', import.meta.url);
const navigation = readFileSync(
    new URL('../../resources/views/components/office/navigation.blade.php', import.meta.url),
    'utf8',
);

test('toda ancla heredada office*Nav consultada por una oficina existe en la navegación compartida', () => {
    const missing = [];

    for (const file of readdirSync(jsDirectory).filter((name) => /^office-.*\.js$/.test(name))) {
        const source = readFileSync(new URL(file, jsDirectory), 'utf8');
        for (const [, id] of source.matchAll(/(?:byId|getElementById)\('(office[A-Z]\w*Nav)'\)/g)) {
            if (!navigation.includes(`id="${id}"`)) missing.push(`${file}: ${id}`);
        }
    }

    assert.deepEqual(missing, []);
});
