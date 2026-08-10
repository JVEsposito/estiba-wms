import './office-material-inventory-actions.js';

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

if (window.location.pathname.startsWith('/oficina/romana')) {
    import('./office-weighbridge-drawer.js').catch((error) => {
        console.error('No fue posible cargar el panel lateral de Romana.', error);
    });
}

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

const panelStoragePrefix = 'estiba_wms_office_panel:';

function panelStorageKey(group) {
    return `${panelStoragePrefix}${group}`;
}

function storedOfficePanel(group) {
    try {
        return sessionStorage.getItem(panelStorageKey(group));
    } catch {
        return null;
    }
}

function officePanelData(switcher) {
    const group = switcher.dataset.officePanelSwitcher;
    const tabs = [...switcher.querySelectorAll('[data-office-panel-target]')];
    const panels = [...document.querySelectorAll(
        `[data-office-panel-group="${CSS.escape(group)}"][data-office-panel-id]`,
    )];

    return { group, tabs, panels };
}

function selectOfficePanel(switcher, requestedPanel, { focus = false, persist = true } = {}) {
    const { group, tabs, panels } = officePanelData(switcher);
    if (!group || !tabs.length || !panels.length) return null;

    const availableTabs = tabs.filter((tab) => !tab.classList.contains('is-hidden'));
    const defaultPanel = switcher.dataset.defaultPanel;
    const panelIds = new Set(panels.map((panel) => panel.dataset.officePanelId));
    const selectedPanel = [requestedPanel, defaultPanel, availableTabs[0]?.dataset.officePanelTarget]
        .find((panelId) => panelId && panelIds.has(panelId)
            && availableTabs.some((tab) => tab.dataset.officePanelTarget === panelId));

    if (!selectedPanel) return null;

    tabs.forEach((tab) => {
        const selected = tab.dataset.officePanelTarget === selectedPanel;
        tab.classList.toggle('is-active', selected);
        tab.setAttribute('aria-selected', String(selected));
        tab.tabIndex = selected ? 0 : -1;
        if (selected && focus) tab.focus();
    });

    panels.forEach((panel) => {
        panel.hidden = panel.dataset.officePanelId !== selectedPanel;
    });

    if (persist) {
        try {
            sessionStorage.setItem(panelStorageKey(group), selectedPanel);
        } catch {
            // La navegación sigue operativa aunque el navegador no permita persistencia.
        }
    }

    document.dispatchEvent(new CustomEvent('estiba:office-panel-change', {
        detail: { group, panel: selectedPanel },
    }));

    return selectedPanel;
}

function initializeOfficePanelSwitchers() {
    document.querySelectorAll('[data-office-panel-switcher]').forEach((switcher) => {
        const { group, tabs } = officePanelData(switcher);
        selectOfficePanel(switcher, storedOfficePanel(group), { persist: false });

        switcher.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-office-panel-target]');
            if (!tab || !switcher.contains(tab)) return;
            selectOfficePanel(switcher, tab.dataset.officePanelTarget);
        });

        switcher.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

            const availableTabs = tabs.filter((tab) => !tab.classList.contains('is-hidden'));
            const activeIndex = availableTabs.indexOf(document.activeElement);
            if (activeIndex < 0 || !availableTabs.length) return;

            event.preventDefault();
            let nextIndex = activeIndex;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = availableTabs.length - 1;
            if (event.key === 'ArrowLeft') nextIndex = (activeIndex - 1 + availableTabs.length) % availableTabs.length;
            if (event.key === 'ArrowRight') nextIndex = (activeIndex + 1) % availableTabs.length;

            const nextTab = availableTabs[nextIndex];
            selectOfficePanel(switcher, nextTab.dataset.officePanelTarget, { focus: true });
        });
    });
}

function scrollToOfficeTarget() {
    if (!location.hash) return;
    const id = decodeURIComponent(location.hash.slice(1));
    let attempts = 0;
    const timer = window.setInterval(() => {
        attempts += 1;
        const target = document.getElementById(id);
        if (target) {
            const panel = target.closest('[data-office-panel-group][data-office-panel-id]');
            if (panel?.hidden) {
                const switcher = document.querySelector(
                    `[data-office-panel-switcher="${CSS.escape(panel.dataset.officePanelGroup)}"]`,
                );
                if (switcher) selectOfficePanel(switcher, panel.dataset.officePanelId);
            }
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


const officeActionHostSelector = [
    '[data-office-action-menu]',
    '.camera-item__actions',
    '.admin-season-actions',
    '#accessProfilesTableBody td:last-child',
    '.material-reception-actions',
    '#materialsInventoryBody td:last-child',
    '.material-row',
    '.dispatch-row__state',
    '.materials-order-actions',
    '#validationHistoryBody td:last-child',
    '.validation-row',
    '.validation-row__actions',
    '.annulment-card',
    '.repa-history-card',
    '.tunnel-card__footer',
    '.inventory-card__actions',
    '.folio-action-cell',
    '.incident-card',
    '.container-weighing-row',
    '.reservation-item',
    '.table-actions',
    '.guide-actions',
    '.segment-card__actions',
    '.lot-actions',
    '.process-actions',
    '.process-delivery-actions',
    '.bin-card__actions',
    '.legacy-card__actions',
    '.producer-actions',
    '.result-card',
].join(', ');

function officeActionItems(host) {
    return [...host.children].filter((element) => (
        element.matches('button, a[href]')
        && !element.classList.contains('is-hidden')
        && element.getAttribute('aria-hidden') !== 'true'
    ));
}

function actionOptionLabel(action) {
    return action.dataset.actionLabel
        || action.getAttribute('aria-label')
        || action.textContent.replace(/\s+/g, ' ').trim()
        || 'Ejecutar acción';
}

function upgradeOfficeActionHost(host) {
    if (host.dataset.officeActionMenuReady === 'true') return;

    const actions = officeActionItems(host);
    if (!actions.length) return;

    host.dataset.officeActionMenuReady = 'true';

    const select = document.createElement('select');
    select.className = 'office-action-select';
    select.setAttribute('aria-label', 'Seleccionar acción');
    select.innerHTML = '<option value="">Seleccionar acción</option>';

    actions.forEach((action, index) => {
        const option = document.createElement('option');
        option.value = String(index);
        option.textContent = actionOptionLabel(action);
        option.disabled = action.disabled || action.getAttribute('aria-disabled') === 'true';
        select.append(option);
    });

    const sources = document.createElement('span');
    sources.className = 'office-action-sources';
    sources.hidden = true;
    sources.append(...actions);

    select.addEventListener('change', () => {
        const selectedIndex = Number(select.value);
        const action = actions[selectedIndex];
        select.value = '';

        if (!action || action.disabled || action.getAttribute('aria-disabled') === 'true') return;
        action.click();
    });

    host.append(select, sources);
}

function upgradeOfficeActionMenus(root = document) {
    const hosts = [];

    if (root instanceof Element && root.matches(officeActionHostSelector)) hosts.push(root);
    if (root.querySelectorAll) hosts.push(...root.querySelectorAll(officeActionHostSelector));

    [...new Set(hosts)].forEach(upgradeOfficeActionHost);
}

function initializeOfficeActionMenus() {
    upgradeOfficeActionMenus();

    let scheduled = false;
    new MutationObserver(() => {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(() => {
            scheduled = false;
            upgradeOfficeActionMenus();
        });
    }).observe(document.body, { childList: true, subtree: true });
}

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(storedTheme());
    initializeThemeSelector();
    refreshNavigation();
    observeApplication();
    initializeOfficePanelSwitchers();
    initializeOfficeActionMenus();
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
