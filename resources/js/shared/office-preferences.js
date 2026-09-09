export const OFFICE_THEME_KEY = 'estiba_wms_office_theme';
export const OFFICE_MENU_KEY = 'estiba_wms_office_menu';
export const OFFICE_THEMES = Object.freeze(['light-professional', 'dark-industrial']);
export const DEFAULT_OFFICE_THEME = OFFICE_THEMES[0];

export function normalizeOfficeTheme(value) {
    return OFFICE_THEMES.includes(value) ? value : DEFAULT_OFFICE_THEME;
}

export function nextOfficeTheme(value) {
    return normalizeOfficeTheme(value) === 'dark-industrial'
        ? 'light-professional'
        : 'dark-industrial';
}

export function isOfficeMenuCollapsed(value) {
    return value === 'collapsed';
}

export function officeMenuPreference(collapsed) {
    return collapsed ? 'collapsed' : 'expanded';
}
