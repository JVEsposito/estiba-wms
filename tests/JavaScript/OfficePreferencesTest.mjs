import assert from 'node:assert/strict';
import test from 'node:test';

import {
    DEFAULT_OFFICE_THEME,
    isOfficeMenuCollapsed,
    nextOfficeTheme,
    normalizeOfficeTheme,
    officeMenuPreference,
} from '../../resources/js/shared/office-preferences.js';

test('normaliza y alterna únicamente los dos temas operacionales', () => {
    assert.equal(normalizeOfficeTheme('dark-industrial'), 'dark-industrial');
    assert.equal(normalizeOfficeTheme('light-professional'), 'light-professional');
    assert.equal(normalizeOfficeTheme('light-warm'), DEFAULT_OFFICE_THEME);
    assert.equal(normalizeOfficeTheme(null), DEFAULT_OFFICE_THEME);
    assert.equal(nextOfficeTheme('light-professional'), 'dark-industrial');
    assert.equal(nextOfficeTheme('dark-industrial'), 'light-professional');
});

test('serializa el estado persistente del menú lateral', () => {
    assert.equal(officeMenuPreference(true), 'collapsed');
    assert.equal(officeMenuPreference(false), 'expanded');
    assert.equal(isOfficeMenuCollapsed('collapsed'), true);
    assert.equal(isOfficeMenuCollapsed('expanded'), false);
});
