// Cabecera y menú compartidos; no intervienen en las operaciones de cada módulo.
const tokenKey = 'estiba_wms_office_token';
let contextToken = null;
let controller = null;
let generation = 0;
let initialized = false;

const shell = () => document.querySelector('[data-office-shell]');
const field = (name) => shell()?.querySelector(`[data-office-${name}]`);
function token() {
    try { return localStorage.getItem(tokenKey); } catch { return null; }
}
function text(name, value) {
    const element = field(name);
    if (element) element.textContent = value;
}

async function loadContext() {
    if (!shell()) return;
    const currentToken = token();
    if (!currentToken) return;
    controller?.abort();
    controller = new AbortController();
    const requestController = controller;
    const currentGeneration = ++generation;
    const button = field('context-refresh');
    button.disabled = true;
    text('context-status', 'Consultando contexto…');
    const timeout = setTimeout(() => requestController.abort(), 10000);
    try {
        const response = await fetch('/api/oficina/contexto', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${currentToken}` },
            cache: 'no-store', signal: requestController.signal,
        });
        if (!response.ok) throw new Error(response.status === 401 ? 'Sesión vencida. Vuelve a ingresar.' : 'No se pudo verificar el contexto.');
        const { data } = await response.json();
        if (!data || !Object.hasOwn(data, 'temporada') || !Object.hasOwn(data, 'planta')
            || (data.temporada !== null && typeof data.temporada?.codigo !== 'string')
            || (data.planta !== null && typeof data.planta !== 'string')) throw new Error('Respuesta de contexto incompleta.');
        if (currentGeneration !== generation || currentToken !== token()) return;
        text('plant', data.planta || 'Sin configurar');
        text('season', data.temporada?.codigo || 'Sin temporada activa');
        text('context-status', `Contexto consultado a las ${new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })}`);
    } catch (error) {
        if (currentGeneration !== generation || currentToken !== token()) return;
        text('plant', 'Sin verificar');
        text('season', 'Sin verificar');
        text('context-status', error.name === 'AbortError' ? 'La consulta tardó demasiado. Reintenta.' : `${error.message} Reintenta la consulta.`);
    } finally {
        clearTimeout(timeout);
        if (currentGeneration === generation) button.disabled = !token();
    }
}

export function refreshOfficeShell(identity, hasSession) {
    if (!shell()) return;
    const readonly = field('readonly');
    if (readonly) readonly.hidden = !hasSession || !(identity?.solo_consulta || identity?.capacidades?.solo_consulta);
    const currentToken = hasSession ? token() : null;
    if (!currentToken) {
        contextToken = null;
        generation += 1;
        controller?.abort();
        text('plant', 'Sin consultar');
        text('season', 'Sin consultar');
        text('context-status', 'Contexto sin consultar');
        field('context-refresh').disabled = true;
    } else if (contextToken !== currentToken) {
        contextToken = currentToken;
        void loadContext();
    }
}

export function initializeOfficeShell() {
    const root = shell();
    if (!root || initialized) return;
    initialized = true;
    const header = root.querySelector('[data-office-shell-header]');
    const menu = field('menu');
    const desktop = window.matchMedia('(min-width: 1200px)');
    root.dataset.menuReady = '';
    function syncMenu() {
        menu.setAttribute('aria-expanded', String(desktop.matches || root.hasAttribute('data-menu-open')));
    }
    function closeMenu() {
        root.removeAttribute('data-menu-open');
        syncMenu();
    }
    menu.addEventListener('click', () => {
        root.toggleAttribute('data-menu-open');
        syncMenu();
    });
    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !desktop.matches && root.hasAttribute('data-menu-open')) {
            event.preventDefault();
            closeMenu();
            menu.focus();
        }
    });
    desktop.addEventListener('change', closeMenu);
    syncMenu();
    const content = [...root.parentElement.children].find(element => element !== root && !['LINK', 'SCRIPT', 'STYLE'].includes(element.tagName));
    if (content) {
        if (!content.id) content.id = 'officeContent';
        const skip = field('skip');
        skip.href = `#${content.id}`;
        skip.addEventListener('click', () => {
            closeMenu();
            if (!content.hasAttribute('tabindex')) content.setAttribute('tabindex', '-1');
            content.focus({ preventScroll: true });
        });
    }
    const size = () => {
        const height = header.getBoundingClientRect().height;
        if (height > 0) root.parentElement.style.setProperty('--office-header-height', `${Math.ceil(height)}px`);
    };
    new ResizeObserver(size).observe(header);
    size();
    field('context-refresh').addEventListener('click', () => void loadContext());
    window.addEventListener('offline', () => text('context-status', 'Sin conexión. El contexto mostrado puede estar desactualizado.'));
    window.addEventListener('online', () => text('context-status', 'Revisa el contexto con Actualizar contexto.'));
}
