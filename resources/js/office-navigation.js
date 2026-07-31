const tokenKey = 'estiba_wms_office_token';
const identityKey = 'estiba_wms_office_identity';
const lastDomainKey = 'estiba_wms_last_domain';
const themeKey = 'estiba_wms_office_theme';
const defaultTheme = 'dark-industrial';
const availableThemes = new Set([
    defaultTheme,
    'light-professional',
    'light-natural',
    'light-warm',
]);

function normalizeTheme(value) {
    return availableThemes.has(value) ? value : defaultTheme;
}

function storedTheme() {
    try {
        return normalizeTheme(localStorage.getItem(themeKey));
    } catch {
        return defaultTheme;
    }
}

function applyTheme(theme, { persist = false } = {}) {
    const normalized = normalizeTheme(theme);
    document.documentElement.dataset.officeTheme = normalized;

    if (persist) {
        try {
            localStorage.setItem(themeKey, normalized);
        } catch {
            // El tema sigue aplicado aunque el navegador no permita persistencia.
        }
    }

    const selector = document.getElementById('officeThemeSelector');
    if (selector && selector.value !== normalized) selector.value = normalized;

    document.dispatchEvent(new CustomEvent('estiba:office-theme', {
        detail: { theme: normalized },
    }));

    return normalized;
}

applyTheme(storedTheme());

function readIdentity() {
    try {
        return JSON.parse(localStorage.getItem(identityKey) || 'null');
    } catch {
        return null;
    }
}

function capabilities(identity) {
    return {
        ...(identity?.capacidades || {}),
        ...(identity || {}),
    };
}

function can(identity, permission) {
    if (!identity || !permission) return false;
    if (identity.rol === 'administrador') return true;

    const values = capabilities(identity);
    if (permission === 'ambito_camaras_productos') {
        return ['productos', 'ambos'].includes(values.ambito_camaras);
    }

    return values[permission] === true;
}

function permissionsFrom(value) {
    return String(value || '')
        .split(',')
        .map((permission) => permission.trim())
        .filter(Boolean);
}

function hasModule(identity, module) {
    if (!module) return true;
    const modules = capabilities(identity).modulos_acceso;
    if (!Array.isArray(modules)) return true;

    return modules.includes(module);
}

function hasAnyPermission(identity, permissions) {
    return permissions.some((permission) => can(identity, permission));
}

function setVisibility(element, visible) {
    element.classList.toggle('is-hidden', !visible);
    element.setAttribute('aria-hidden', String(!visible));
    if (visible) element.removeAttribute('tabindex');
    else element.setAttribute('tabindex', '-1');
}

function domainTargets(link) {
    try {
        const targets = JSON.parse(link.dataset.navigationTargets || '[]');

        return Array.isArray(targets) ? targets : [];
    } catch {
        return [];
    }
}

function firstAccessibleTarget(identity, targets) {
    return targets.find((target) => (
        hasModule(identity, target.module)
        && hasAnyPermission(identity, target.permissions || [])
    )) || null;
}

function redirectFromUnavailableOffice(identity) {
    const header = document.querySelector('.office-domain-topbar');
    const activeOffice = header?.dataset.activeOffice;
    const activeDomain = header?.dataset.activeDomain;
    if (!activeOffice || !activeDomain) return;

    const activeLink = document.querySelector(
        `[data-office-domain="${CSS.escape(activeDomain)}"][data-office-key="${CSS.escape(activeOffice)}"]`,
    );
    if (activeLink && !activeLink.classList.contains('is-hidden')) return;

    const activeDomainLink = document.querySelector(
        `[data-domain-key="${CSS.escape(activeDomain)}"]`,
    );
    const activeTarget = activeDomainLink
        ? firstAccessibleTarget(identity, domainTargets(activeDomainLink))
        : null;
    const fallback = activeTarget || [...document.querySelectorAll('[data-domain-key]')]
        .map((link) => firstAccessibleTarget(identity, domainTargets(link)))
        .find(Boolean);
    if (!fallback) return;

    const destination = new URL(fallback.href, window.location.origin);
    const current = new URL(window.location.href);
    if (destination.pathname === current.pathname && destination.hash === current.hash) return;
    window.location.replace(destination.href);
}

function refreshNavigation() {
    const identity = readIdentity();
    const hasSession = Boolean(localStorage.getItem(tokenKey) && identity);
    const profileName = identity?.perfil_acceso?.nombre
        || identity?.capacidades?.perfil_acceso?.nombre;
    const roleLabel = document.getElementById('officeUserRole');
    if (hasSession && profileName && roleLabel) roleLabel.textContent = profileName;

    document.querySelectorAll('[data-office-key]').forEach((link) => {
        const permissions = permissionsFrom(link.dataset.navigationPermissions);
        setVisibility(
            link,
            hasSession
                && hasModule(identity, link.dataset.navigationModule)
                && hasAnyPermission(identity, permissions),
        );
    });

    document.querySelectorAll('[data-navigation-permissions]:not([data-office-key])').forEach((element) => {
        const permissions = permissionsFrom(element.dataset.navigationPermissions);
        setVisibility(
            element,
            hasSession
                && hasModule(identity, element.dataset.navigationModule)
                && hasAnyPermission(identity, permissions),
        );
    });

    document.querySelectorAll('[data-domain-key]').forEach((link) => {
        const target = hasSession
            ? firstAccessibleTarget(identity, domainTargets(link))
            : null;
        setVisibility(link, Boolean(target));
        if (target) link.href = target.href;
    });

    const activeDomain = document.querySelector('.office-domain-topbar')?.dataset.activeDomain;
    if (hasSession && activeDomain) localStorage.setItem(lastDomainKey, activeDomain);
    if (hasSession) redirectFromUnavailableOffice(identity);
}

function scrollToOfficeTarget() {
    if (!location.hash) return;
    const id = decodeURIComponent(location.hash.slice(1));
    let attempts = 0;
    const timer = window.setInterval(() => {
        attempts += 1;
        const target = document.getElementById(id);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.classList.add('office-navigation-target');
            window.setTimeout(() => target.classList.remove('office-navigation-target'), 1800);
            window.clearInterval(timer);
        } else if (attempts >= 30) {
            window.clearInterval(timer);
        }
    }, 150);
}

function observeApplication() {
    const app = document.getElementById('officeApp');
    if (!app) return;
    new MutationObserver(() => refreshNavigation()).observe(app, {
        attributes: true,
        attributeFilter: ['class'],
    });
}

function ensureThemeSelector() {
    const existing = document.getElementById('officeThemeSelector');
    if (existing) return existing;

    const identity = document.querySelector('.office-domain-topbar .identity');
    if (!identity) return null;

    const control = document.createElement('label');
    control.className = 'office-theme-selector';
    control.title = 'Cambiar apariencia de las oficinas';
    control.innerHTML = `
        <span class="office-visually-hidden">Tema visual</span>
        <select id="officeThemeSelector" aria-label="Tema visual de las oficinas">
            <option value="dark-industrial">Dark Industrial</option>
            <option value="light-professional">Light Profesional</option>
            <option value="light-natural">Light Natural</option>
            <option value="light-warm">Light Cálido</option>
        </select>
    `;

    const logout = document.getElementById('officeLogoutButton');
    identity.insertBefore(control, logout || null);

    return control.querySelector('select');
}

function initializeThemeSelector() {
    const selector = ensureThemeSelector();
    if (!selector) return;

    selector.value = storedTheme();
    selector.addEventListener('change', () => {
        applyTheme(selector.value, { persist: true });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(storedTheme());
    initializeThemeSelector();
    refreshNavigation();
    observeApplication();
    scrollToOfficeTarget();

    document.querySelectorAll('[data-domain-key]').forEach((link) => {
        link.addEventListener('click', () => localStorage.setItem(lastDomainKey, link.dataset.domainKey));
    });
});

window.addEventListener('storage', (event) => {
    if (event.key === themeKey) applyTheme(event.newValue);
    refreshNavigation();
});
window.addEventListener('estiba:office-session', refreshNavigation);

window.EstibaOfficeTheme = {
    apply: (theme) => applyTheme(theme, { persist: true }),
    current: () => normalizeTheme(document.documentElement.dataset.officeTheme),
    themes: [...availableThemes],
};
window.EstibaOfficeNavigation = { refresh: refreshNavigation };
