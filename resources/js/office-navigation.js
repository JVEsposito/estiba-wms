const tokenKey = 'estiba_wms_office_token';
const identityKey = 'estiba_wms_office_identity';
const lastDomainKey = 'estiba_wms_last_domain';

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
    return targets.find((target) => hasAnyPermission(identity, target.permissions || [])) || null;
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

    document.querySelectorAll('[data-office-key]').forEach((link) => {
        const permissions = permissionsFrom(link.dataset.navigationPermissions);
        setVisibility(link, hasSession && hasAnyPermission(identity, permissions));
    });

    document.querySelectorAll('[data-navigation-permissions]:not([data-office-key])').forEach((element) => {
        const permissions = permissionsFrom(element.dataset.navigationPermissions);
        setVisibility(element, hasSession && hasAnyPermission(identity, permissions));
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

document.addEventListener('DOMContentLoaded', () => {
    refreshNavigation();
    observeApplication();
    scrollToOfficeTarget();

    document.querySelectorAll('[data-domain-key]').forEach((link) => {
        link.addEventListener('click', () => localStorage.setItem(lastDomainKey, link.dataset.domainKey));
    });
});

window.addEventListener('storage', refreshNavigation);
window.addEventListener('estiba:office-session', refreshNavigation);
window.EstibaOfficeNavigation = { refresh: refreshNavigation };
