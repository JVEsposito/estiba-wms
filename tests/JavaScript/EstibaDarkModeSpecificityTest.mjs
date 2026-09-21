import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

// Regresión: la sobreescritura de modo oscuro en estiba-tokens.css (generada por
// scripts/build-design-tokens.mjs) tenía más especificidad que los puentes
// .operation-now / .office-app .discrepancies-shell que remapean --eui-color-* a los
// tokens de Oficina (office-corporate.css / office-operation-now.css). Eso hacía que en
// modo oscuro esos puentes perdieran la cascada sin importar el orden de carga, y esas
// pantallas mostraban la paleta oscura genérica de estiba-tokens.css en vez de la del
// tema de Oficina seleccionado. Este test calcula especificidad real (conteo de
// selectores por nivel), no solo busca ":where(" como texto.

const tokensCss = readFileSync(new URL('../../resources/css/estiba-tokens.css', import.meta.url), 'utf8');

// Calculadora de especificidad simplificada (suficiente para los selectores de este
// archivo: sin pseudo-elementos, sin :not()/:is() anidados con contenido propio).
function specificity(selector) {
    let ids = 0;
    let classes = 0;
    let elements = 0;

    // :where(...) no aporta especificidad — se descarta su contenido antes de contar.
    const withoutWhere = selector.replace(/:where\([^)]*\)/g, '');

    ids += (withoutWhere.match(/#[\w-]+/g) || []).length;
    classes += (withoutWhere.match(/\.[\w-]+/g) || []).length;
    classes += (withoutWhere.match(/\[[^\]]+\]/g) || []).length; // atributos
    classes += (withoutWhere.match(/:(?!where)[\w-]+/g) || []).length; // pseudo-clases (incluye :root)
    elements += (withoutWhere.match(/(?:^|[\s>+~])([a-zA-Z][\w-]*)/g) || [])
        .filter((m) => !m.trim().startsWith('.') && !m.trim().startsWith('#')).length;

    return [ids, classes, elements];
}

// Compara especificidades [ids, classes, elements]: negativo si a < b, 0 si son iguales,
// positivo si a > b.
function compare(a, b) {
    for (let i = 0; i < 3; i += 1) {
        if (a[i] !== b[i]) return a[i] - b[i];
    }
    return 0;
}

test('la sobreescritura de modo oscuro de estiba-tokens.css no le gana en especificidad a los puentes de Oficina', () => {
    const darkSelectorMatch = tokensCss.match(/\n([^\n{]*\.estiba-ui)\s*\{/g);
    assert.ok(darkSelectorMatch, 'No se encontró ninguna regla .estiba-ui en estiba-tokens.css');

    const darkRule = darkSelectorMatch.find((rule) => /data-office-theme="dark-industrial"/.test(rule));
    assert.ok(darkRule, 'No se encontró la sobreescritura de modo oscuro (.estiba-ui bajo data-office-theme dark-industrial)');

    const darkSelector = darkRule.trim().replace(/\s*\{$/, '');
    const darkSpec = specificity(darkSelector);

    // Los puentes reales que remapean --eui-color-* a los tokens de Oficina.
    const bridgeSelectors = ['.operation-now', '.office-app .discrepancies-shell'];
    for (const bridge of bridgeSelectors) {
        const bridgeSpec = specificity(bridge);
        assert.ok(
            compare(darkSpec, bridgeSpec) <= 0,
            `La sobreescritura oscura (${darkSelector} = ${JSON.stringify(darkSpec)}) le gana en especificidad a ${bridge} (${JSON.stringify(bridgeSpec)}); debería usar :where() sobre la parte de atributo para no superarla.`,
        );
    }
});
