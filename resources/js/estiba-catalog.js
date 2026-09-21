// Solo interacción del catálogo estático. No utiliza APIs, sesión ni almacenamiento.
(() => {
    const catalog = document.getElementById('catalog');
    const feedback = document.getElementById('catalogFeedback');
    const densities = ['comfortable', 'compact', 'touch'];

    // Mismo mecanismo que resources/js/office-navigation.js (atributo data-office-theme en
    // <html>, que resources/css/estiba-tokens.css lee para el shell real): sin localStorage,
    // porque este archivo se embebe tal cual en el HTML exportado y no persiste nada.
    const themeToggle = document.getElementById('catalogThemeToggle');
    const colorSchemeMeta = document.querySelector('meta[name="color-scheme"]');
    let officeTheme = 'light-professional';

    function applyCatalogTheme(theme) {
        officeTheme = theme;
        const dark = theme === 'dark-industrial';
        document.documentElement.dataset.officeTheme = theme;
        colorSchemeMeta?.setAttribute('content', dark ? 'dark' : 'light');
        themeToggle.setAttribute('aria-pressed', String(dark));
        themeToggle.textContent = dark ? 'Modo claro' : 'Modo oscuro';
    }

    themeToggle?.addEventListener('click', () => {
        applyCatalogTheme(officeTheme === 'dark-industrial' ? 'light-professional' : 'dark-industrial');
        feedback.textContent = `Vista previa en modo ${officeTheme === 'dark-industrial' ? 'oscuro' : 'claro'}. No se guarda ninguna preferencia.`;
    });

    catalog.querySelectorAll('[data-density-choice]').forEach((button) => {
        button.addEventListener('click', () => {
            const density = button.dataset.densityChoice;
            if (!densities.includes(density)) return;
            catalog.dataset.density = density;
            catalog.querySelectorAll('[data-density-choice]').forEach((choice) => {
                choice.setAttribute('aria-pressed', String(choice === button));
            });
            feedback.textContent = `Densidad ${button.textContent.trim().toLowerCase()} seleccionada. En pantallas táctiles se conserva el tamaño mínimo de 56 px.`;
        });
    });

    catalog.querySelectorAll('[data-example-action]').forEach((button) => {
        button.addEventListener('click', () => {
            feedback.textContent = `Ejemplo: ${button.dataset.exampleAction}. No se ejecutó ninguna operación.`;
        });
    });

    catalog.querySelector('[data-demo-form]').addEventListener('submit', (event) => {
        event.preventDefault();
        const input = document.getElementById('sampleReason');
        const error = document.getElementById('sampleReason-error');
        const invalid = input.value.trim().length < 3;
        error.hidden = !invalid;
        input.setAttribute('aria-invalid', String(invalid));
        input.setAttribute('aria-describedby', `sampleReason-hint${invalid ? ' sampleReason-error' : ''}`);
        if (invalid) {
            input.focus();
        } else {
            feedback.textContent = 'Validación de ejemplo correcta. El texto no se envió ni se guardó.';
        }
    });
})();
