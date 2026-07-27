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
    if (permission === 'ambito_camaras') {
        return Boolean(values.puede_administrar_camaras)
            || (values.ambito_camaras && values.ambito_camaras !== 'ninguno');
    }
    if (permission === 'puede_consultar_existencias') {
        return Boolean(
            values.puede_consultar_despachos_materiales
            || values.puede_consultar_materia_prima
            || values.puede_consultar_cargas
            || values.puede_consultar_panel_gerencial,
        );
    }

    return values[permission] === true;
}

function refreshNavigation() {
    const identity = readIdentity();
    const hasSession = Boolean(localStorage.getItem(tokenKey) && identity);

    document.querySelectorAll('[data-navigation-permission]').forEach((link) => {
        link.classList.toggle('is-hidden', !hasSession || !can(identity, link.dataset.navigationPermission));
    });

    document.querySelectorAll('[data-navigation-permissions]').forEach((link) => {
        const permissions = String(link.dataset.navigationPermissions || '')
            .split(',')
            .map((value) => value.trim())
            .filter(Boolean);
        const visible = hasSession && permissions.some((permission) => can(identity, permission));
        link.classList.toggle('is-hidden', !visible);
    });

    const activeDomain = document.querySelector('.office-domain-topbar')?.dataset.activeDomain;
    if (hasSession && activeDomain) localStorage.setItem(lastDomainKey, activeDomain);
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
window.EstibaOfficeNavigation = { refresh: refreshNavigation };
