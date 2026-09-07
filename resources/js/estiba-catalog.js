// Solo interacción del catálogo estático. No utiliza APIs, sesión ni almacenamiento.
(() => {
    const catalog = document.getElementById('catalog');
    const feedback = document.getElementById('catalogFeedback');
    const densities = ['comfortable', 'compact', 'touch'];

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
