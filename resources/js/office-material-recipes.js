const recipeTokenKey = 'estiba_wms_office_token';
const recipeIdentityKey = 'estiba_wms_office_identity';

const recipeState = {
    token: null,
    identity: null,
    catalog: { temporada: null, clientes: [], items: [] },
    recipes: [],
    editingRecipeId: null,
    loadedToken: null,
};

const recipeElements = {};

class RecipeApiError extends Error {
    constructor(message, status = 0) {
        super(message);
        this.status = status;
    }
}

function recipeReadJson(key) {
    try {
        return JSON.parse(localStorage.getItem(key) || 'null');
    } catch {
        return null;
    }
}

function recipeEscape(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function recipeQuantity(value, maximumFractionDigits = 3) {
    return new Intl.NumberFormat('es-CL', { maximumFractionDigits }).format(Number(value || 0));
}

function recipeStatus(value) {
    return String(value || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
}

function recipeErrorMessage(data, fallback) {
    return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback;
}

async function recipeApi(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    if (recipeState.token) headers.set('Authorization', `Bearer ${recipeState.token}`);
    if (options.body) headers.set('Content-Type', 'application/json');

    let response;
    try {
        response = await fetch(path, { ...options, headers });
    } catch {
        throw new RecipeApiError('No fue posible conectar con Laravel.');
    }

    const data = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new RecipeApiError(
            recipeErrorMessage(data, 'No fue posible completar la operación de recetas.'),
            response.status,
        );
    }

    return data;
}

function injectRecipeStyles() {
    if (document.getElementById('materialsRecipeStyles')) return;
    const style = document.createElement('style');
    style.id = 'materialsRecipeStyles';
    style.textContent = `
        .materials-recipes-panel { margin-top: 1.25rem; }
        .materials-recipes-layout { display: grid; grid-template-columns: minmax(320px, .9fr) minmax(360px, 1.1fr); gap: 1rem; align-items: start; }
        .materials-recipe-form { border: 1px solid var(--line, rgba(255,255,255,.12)); border-radius: 14px; padding: 1rem; background: rgba(255,255,255,.025); }
        .materials-recipe-form.is-hidden { display: none; }
        .materials-recipe-form__heading { display: flex; justify-content: space-between; align-items: start; gap: .75rem; margin-bottom: .85rem; }
        .materials-recipe-form__heading h3 { margin: .15rem 0; }
        .materials-recipe-components { display: grid; gap: .75rem; margin: .85rem 0; }
        .materials-recipe-component { display: grid; grid-template-columns: minmax(180px, 1.6fr) repeat(4, minmax(92px, .7fr)) auto; gap: .55rem; align-items: end; border: 1px solid var(--line, rgba(255,255,255,.12)); border-radius: 12px; padding: .75rem; }
        .materials-recipe-component label { display: grid; gap: .3rem; font-size: .82rem; }
        .materials-recipe-component .recipe-principal { align-self: center; display: flex; align-items: center; gap: .4rem; white-space: nowrap; }
        .materials-recipe-list { display: grid; gap: .75rem; }
        .materials-recipe-card { border: 1px solid var(--line, rgba(255,255,255,.12)); border-radius: 14px; padding: 1rem; background: rgba(255,255,255,.025); }
        .materials-recipe-card__header { display: flex; justify-content: space-between; gap: 1rem; align-items: start; }
        .materials-recipe-card__header h3 { margin: 0 0 .25rem; }
        .materials-recipe-card__meta { display: flex; flex-wrap: wrap; gap: .45rem; margin: .75rem 0; }
        .materials-recipe-card__meta span { border: 1px solid var(--line, rgba(255,255,255,.12)); border-radius: 999px; padding: .25rem .55rem; font-size: .78rem; }
        .materials-recipe-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .materials-recipe-table th, .materials-recipe-table td { padding: .45rem .35rem; text-align: left; border-top: 1px solid var(--line, rgba(255,255,255,.1)); }
        .materials-recipe-empty { margin: 0; padding: 1rem; border: 1px dashed var(--line, rgba(255,255,255,.18)); border-radius: 12px; }
        @media (max-width: 1050px) {
            .materials-recipes-layout { grid-template-columns: 1fr; }
            .materials-recipe-component { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .materials-recipe-component > label:first-child { grid-column: 1 / -1; }
        }
    `;
    document.head.append(style);
}

function injectRecipePanel() {
    if (document.getElementById('materialsRecipesPanel')) return;
    const workspace = document.querySelector('.materials-workspace');
    const operations = document.querySelector('.materials-operation-grid');
    if (!workspace || !operations) return;

    const section = document.createElement('section');
    section.className = 'panel materials-panel materials-recipes-panel is-hidden';
    section.id = 'materialsRecipesPanel';
    section.innerHTML = `
        <div class="materials-panel__heading">
            <div>
                <p class="eyebrow">TRANSFORMACIÓN INTERNA</p>
                <h2>Recetas de materiales</h2>
                <p class="materials-help">Define qué insumos y materiales sin preparar se consumen para obtener un material preparado para línea.</p>
            </div>
            <div class="materials-panel__tools">
                <span id="materialsRecipesSummary">0 recetas</span>
                <button class="secondary-button" id="reloadMaterialRecipes" type="button">↻ Actualizar recetas</button>
            </div>
        </div>
        <div class="materials-recipes-layout">
            <form class="materials-form materials-recipe-form is-hidden" id="materialRecipeForm" novalidate>
                <input name="recipe_id" type="hidden">
                <div class="materials-recipe-form__heading">
                    <div><p class="eyebrow">CONFIGURACIÓN</p><h3 id="materialRecipeFormTitle">Nueva receta</h3></div>
                    <button class="secondary-button is-hidden" id="cancelMaterialRecipeVersion" type="button">Cancelar versión</button>
                </div>
                <div class="materials-form__grid">
                    <label><span>Cliente *</span><select name="cliente_id" required></select></label>
                    <label><span>Producto de salida *</span><select name="item_salida_id" required></select></label>
                    <label class="materials-wide"><span>Nombre de receta *</span><input name="nombre" minlength="3" maxlength="180" placeholder="Caja 10 kg preparada para línea" required></label>
                    <label><span>Cantidad base de salida *</span><input name="cantidad_base_salida" type="number" min="0.001" step="0.001" value="1" required></label>
                </div>
                <div class="materials-panel__heading">
                    <div><p class="eyebrow">COMPONENTES</p><h3>Entradas de la receta</h3></div>
                    <button class="secondary-button" id="addMaterialRecipeComponent" type="button">+ Agregar componente</button>
                </div>
                <p class="materials-help">Debe existir exactamente un componente principal. La merma se calcula sobre ese componente; los demás registran variación de consumo.</p>
                <div class="materials-recipe-components" id="materialRecipeComponents"></div>
                <p class="form-error" id="materialRecipeError" role="alert"></p>
                <div class="materials-actions"><button class="primary-button" type="submit" id="saveMaterialRecipe">Crear receta</button></div>
            </form>
            <div>
                <label class="materials-season-selector"><span>Filtrar por cliente</span><select id="materialRecipesClientFilter"><option value="">Todos los clientes</option></select></label>
                <div class="materials-recipe-list" id="materialRecipesList"></div>
            </div>
        </div>
    `;
    workspace.insertBefore(section, operations);

    Object.assign(recipeElements, {
        panel: section,
        summary: document.getElementById('materialsRecipesSummary'),
        reload: document.getElementById('reloadMaterialRecipes'),
        form: document.getElementById('materialRecipeForm'),
        formTitle: document.getElementById('materialRecipeFormTitle'),
        cancelVersion: document.getElementById('cancelMaterialRecipeVersion'),
        components: document.getElementById('materialRecipeComponents'),
        addComponent: document.getElementById('addMaterialRecipeComponent'),
        error: document.getElementById('materialRecipeError'),
        save: document.getElementById('saveMaterialRecipe'),
        filter: document.getElementById('materialRecipesClientFilter'),
        list: document.getElementById('materialRecipesList'),
    });

    recipeElements.reload.addEventListener('click', () => loadRecipesOffice(true));
    recipeElements.filter.addEventListener('change', renderRecipes);
    recipeElements.form.elements.cliente_id.addEventListener('change', () => {
        recipeState.editingRecipeId = null;
        populateRecipeItems();
        resetRecipeComponents();
    });
    recipeElements.addComponent.addEventListener('click', () => addRecipeComponent());
    recipeElements.components.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-recipe-component]');
        if (!remove || recipeElements.components.children.length <= 1) return;
        remove.closest('.materials-recipe-component').remove();
    });
    recipeElements.cancelVersion.addEventListener('click', resetRecipeForm);
    recipeElements.list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-new-recipe-version]');
        if (!button) return;
        openRecipeVersion(button.dataset.newRecipeVersion);
    });
    recipeElements.form.addEventListener('submit', submitRecipeForm);
}

function activeCatalogClientByGlobalId(globalClientId) {
    return recipeState.catalog.clientes.find((client) => client.cliente_id === globalClientId) || null;
}

function recipeItemsForGlobalClient(globalClientId) {
    const client = activeCatalogClientByGlobalId(globalClientId);
    if (!client) return [];
    return recipeState.catalog.items.filter((item) => item.cliente?.id === client.id && item.activo !== false);
}

function populateRecipeSelectors() {
    const clients = recipeState.catalog.clientes || [];
    recipeElements.form.elements.cliente_id.innerHTML = clients
        .map((client) => `<option value="${recipeEscape(client.cliente_id)}">${recipeEscape(client.codigo)} · ${recipeEscape(client.nombre)}</option>`)
        .join('') || '<option value="">Sin clientes activos</option>';
    recipeElements.filter.innerHTML = '<option value="">Todos los clientes</option>' + clients
        .map((client) => `<option value="${recipeEscape(client.cliente_id)}">${recipeEscape(client.codigo)} · ${recipeEscape(client.nombre)}</option>`)
        .join('');
    populateRecipeItems();
}

function populateRecipeItems() {
    const globalClientId = recipeElements.form.elements.cliente_id.value;
    const items = recipeItemsForGlobalClient(globalClientId);
    const outputs = items.filter((item) => item.categoria_operacional === 'material_pt');
    recipeElements.form.elements.item_salida_id.innerHTML = outputs
        .map((item) => `<option value="${recipeEscape(item.id)}">${recipeEscape(item.codigo)} · ${recipeEscape(item.nombre)} · ${recipeEscape(item.unidad_medida)}</option>`)
        .join('') || '<option value="">El cliente no tiene ítems Material PT activos</option>';
    recipeElements.components.querySelectorAll('[name="item_entrada_id"]').forEach((select) => {
        const previous = select.value;
        select.innerHTML = recipeInputOptions(globalClientId);
        select.value = previous;
    });
}

function recipeInputOptions(globalClientId, selected = '') {
    const inputs = recipeItemsForGlobalClient(globalClientId)
        .filter((item) => ['insumo', 'material_mp'].includes(item.categoria_operacional));
    return inputs.map((item) => `<option value="${recipeEscape(item.id)}"${item.id === selected ? ' selected' : ''}>${recipeEscape(item.codigo)} · ${recipeEscape(item.nombre)} · ${recipeEscape(item.categoria_operacional_etiqueta || recipeStatus(item.categoria_operacional))}</option>`).join('')
        || '<option value="">Sin insumos o Material MP activos</option>';
}

function addRecipeComponent(component = {}) {
    const globalClientId = recipeElements.form.elements.cliente_id.value;
    const rowId = `recipe-component-${crypto.randomUUID?.() || Math.random().toString(36).slice(2)}`;
    const row = document.createElement('div');
    row.className = 'materials-recipe-component';
    row.innerHTML = `
        <label><span>Ítem de entrada *</span><select name="item_entrada_id" required>${recipeInputOptions(globalClientId, component.item?.id || component.item_entrada_id || '')}</select></label>
        <label><span>Cantidad *</span><input name="cantidad_estandar" type="number" min="0.001" step="0.001" value="${recipeEscape(component.cantidad_estandar ?? 1)}" required></label>
        <label><span>Factor</span><input name="factor_conversion" type="number" min="0.000001" step="0.000001" value="${recipeEscape(component.factor_conversion ?? 1)}"></label>
        <label><span>Merma %</span><input name="merma_estandar_porcentaje" type="number" min="0" max="100" step="0.0001" value="${recipeEscape(component.merma_estandar_porcentaje ?? 0)}"></label>
        <label><span>Tolerancia %</span><input name="tolerancia_porcentaje" type="number" min="0" max="100" step="0.0001" value="${recipeEscape(component.tolerancia_porcentaje ?? 0)}"></label>
        <div><label class="recipe-principal"><input name="recipe_principal" type="radio" value="${recipeEscape(rowId)}"${component.es_componente_principal ? ' checked' : ''}><span>Principal</span></label><button class="secondary-button" data-remove-recipe-component type="button" aria-label="Quitar componente">×</button></div>
    `;
    row.dataset.componentRow = rowId;
    recipeElements.components.append(row);
}

function resetRecipeComponents(components = []) {
    recipeElements.components.innerHTML = '';
    if (components.length) {
        components.forEach((component) => addRecipeComponent(component));
    } else {
        addRecipeComponent({ es_componente_principal: true });
    }
}

function resetRecipeForm() {
    recipeState.editingRecipeId = null;
    recipeElements.form.reset();
    recipeElements.form.elements.recipe_id.value = '';
    recipeElements.form.elements.cliente_id.disabled = false;
    recipeElements.form.elements.item_salida_id.disabled = false;
    recipeElements.form.elements.nombre.disabled = false;
    recipeElements.formTitle.textContent = 'Nueva receta';
    recipeElements.save.textContent = 'Crear receta';
    recipeElements.cancelVersion.classList.add('is-hidden');
    recipeElements.error.textContent = '';
    populateRecipeSelectors();
    resetRecipeComponents();
}

function latestRecipeVersion(recipe) {
    return [...(recipe.versiones || [])].sort((left, right) => Number(right.numero_version) - Number(left.numero_version))[0] || null;
}

function openRecipeVersion(recipeId) {
    const recipe = recipeState.recipes.find((candidate) => candidate.id === recipeId);
    const version = latestRecipeVersion(recipe);
    if (!recipe || !version) return;

    recipeState.editingRecipeId = recipe.id;
    recipeElements.form.elements.recipe_id.value = recipe.id;
    recipeElements.form.elements.cliente_id.value = recipe.cliente.id;
    populateRecipeItems();
    recipeElements.form.elements.item_salida_id.value = recipe.item_salida.id;
    recipeElements.form.elements.nombre.value = recipe.nombre;
    recipeElements.form.elements.cantidad_base_salida.value = version.cantidad_base_salida;
    recipeElements.form.elements.cliente_id.disabled = true;
    recipeElements.form.elements.item_salida_id.disabled = true;
    recipeElements.form.elements.nombre.disabled = true;
    recipeElements.formTitle.textContent = `${recipe.nombre} · nueva versión`;
    recipeElements.save.textContent = `Crear versión ${Number(version.numero_version) + 1}`;
    recipeElements.cancelVersion.classList.remove('is-hidden');
    recipeElements.error.textContent = '';
    resetRecipeComponents(version.componentes || []);
    recipeElements.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function recipeComponentsPayload() {
    const rows = [...recipeElements.components.querySelectorAll('.materials-recipe-component')];
    const principal = recipeElements.components.querySelector('[name="recipe_principal"]:checked')?.value;
    if (!principal) throw new RecipeApiError('Selecciona exactamente un componente principal.', 422);

    const seen = new Set();
    return rows.map((row, index) => {
        const itemId = row.querySelector('[name="item_entrada_id"]').value;
        if (!itemId) throw new RecipeApiError(`Selecciona el ítem de entrada de la línea ${index + 1}.`, 422);
        if (seen.has(itemId)) throw new RecipeApiError('Un mismo ítem no puede repetirse dentro de la receta.', 422);
        seen.add(itemId);
        return {
            item_entrada_id: itemId,
            cantidad_estandar: Number(row.querySelector('[name="cantidad_estandar"]').value),
            es_componente_principal: row.dataset.componentRow === principal,
            factor_conversion: Number(row.querySelector('[name="factor_conversion"]').value || 1),
            merma_estandar_porcentaje: Number(row.querySelector('[name="merma_estandar_porcentaje"]').value || 0),
            tolerancia_porcentaje: Number(row.querySelector('[name="tolerancia_porcentaje"]').value || 0),
        };
    });
}

async function submitRecipeForm(event) {
    event.preventDefault();
    recipeElements.error.textContent = '';
    const data = Object.fromEntries(new FormData(recipeElements.form));

    try {
        const payload = {
            cantidad_base_salida: Number(data.cantidad_base_salida),
            componentes: recipeComponentsPayload(),
        };
        let path = '/api/materiales/transformaciones/recetas';
        if (recipeState.editingRecipeId) {
            path += `/${recipeState.editingRecipeId}/versiones`;
        } else {
            payload.cliente_id = data.cliente_id;
            payload.item_salida_id = data.item_salida_id;
            payload.nombre = data.nombre;
        }

        recipeElements.save.disabled = true;
        recipeElements.save.textContent = recipeState.editingRecipeId ? 'Creando versión…' : 'Creando receta…';
        await recipeApi(path, { method: 'POST', body: JSON.stringify(payload) });
        resetRecipeForm();
        await loadRecipesOffice(true);
    } catch (error) {
        recipeElements.error.textContent = error.message;
    } finally {
        recipeElements.save.disabled = false;
        if (!recipeState.editingRecipeId) recipeElements.save.textContent = 'Crear receta';
    }
}

function renderRecipes() {
    const selectedClient = recipeElements.filter.value;
    const recipes = recipeState.recipes.filter((recipe) => !selectedClient || recipe.cliente?.id === selectedClient);
    recipeElements.summary.textContent = `${recipeState.recipes.length} ${recipeState.recipes.length === 1 ? 'receta' : 'recetas'}`;

    recipeElements.list.innerHTML = recipes.map((recipe) => {
        const version = latestRecipeVersion(recipe);
        const components = version?.componentes || [];
        const principal = components.find((component) => component.es_componente_principal);
        return `
            <article class="materials-recipe-card${recipe.activa ? '' : ' is-inactive'}">
                <div class="materials-recipe-card__header">
                    <div>
                        <h3>${recipeEscape(recipe.nombre)}</h3>
                        <small>${recipeEscape(recipe.cliente?.codigo)} · ${recipeEscape(recipe.cliente?.nombre)} → ${recipeEscape(recipe.item_salida?.codigo)} · ${recipeEscape(recipe.item_salida?.nombre)}</small>
                    </div>
                    ${recipeState.identity?.puede_administrar_recetas_materiales === true ? `<button class="secondary-button" data-new-recipe-version="${recipeEscape(recipe.id)}" type="button">Nueva versión</button>` : ''}
                </div>
                <div class="materials-recipe-card__meta">
                    <span>Versión ${recipeEscape(version?.numero_version || '—')}</span>
                    <span>${recipeEscape(recipeStatus(version?.estado || 'sin_version'))}</span>
                    <span>Salida base: ${recipeQuantity(version?.cantidad_base_salida)} ${recipeEscape(version?.unidad_medida_salida || recipe.item_salida?.unidad_medida || '')}</span>
                    <span>Principal: ${recipeEscape(principal?.item?.codigo || 'No definido')}</span>
                    <span>Merma estándar: ${recipeQuantity(principal?.merma_estandar_porcentaje, 4)}%</span>
                </div>
                <table class="materials-recipe-table">
                    <thead><tr><th>Componente</th><th>Cantidad</th><th>Factor</th><th>Merma</th><th>Tolerancia</th></tr></thead>
                    <tbody>${components.map((component) => `<tr><td><strong>${recipeEscape(component.item?.codigo)}</strong><br><small>${recipeEscape(component.item?.nombre)}${component.es_componente_principal ? ' · principal' : ''}</small></td><td>${recipeQuantity(component.cantidad_estandar)} ${recipeEscape(component.unidad_medida)}</td><td>${recipeQuantity(component.factor_conversion, 6)}</td><td>${recipeQuantity(component.merma_estandar_porcentaje, 4)}%</td><td>${recipeQuantity(component.tolerancia_porcentaje, 4)}%</td></tr>`).join('')}</tbody>
                </table>
            </article>
        `;
    }).join('') || '<p class="materials-recipe-empty">No existen recetas para el filtro seleccionado.</p>';
}

async function loadRecipesOffice(showErrors = false) {
    recipeState.token = localStorage.getItem(recipeTokenKey);
    recipeState.identity = recipeReadJson(recipeIdentityKey);
    if (!recipeState.token || recipeState.identity?.puede_consultar_transformaciones_materiales !== true) {
        recipeElements.panel?.classList.add('is-hidden');
        return;
    }

    recipeElements.panel.classList.remove('is-hidden');
    recipeElements.form.classList.toggle('is-hidden', recipeState.identity?.puede_administrar_recetas_materiales !== true);
    try {
        const [catalog, recipes] = await Promise.all([
            recipeApi('/api/materiales/catalogo'),
            recipeApi('/api/materiales/transformaciones/recetas?per_page=100'),
        ]);
        recipeState.catalog = catalog;
        recipeState.recipes = recipes.data || [];
        recipeState.loadedToken = recipeState.token;
        populateRecipeSelectors();
        if (!recipeState.editingRecipeId) resetRecipeComponents();
        renderRecipes();
    } catch (error) {
        if (showErrors) {
            recipeElements.list.innerHTML = `<p class="materials-recipe-empty">${recipeEscape(error.message)}</p>`;
        }
    }
}

function bootMaterialRecipes() {
    injectRecipeStyles();
    injectRecipePanel();
    if (!recipeElements.panel) return;

    document.getElementById('reloadMaterialsButton')?.addEventListener('click', () => loadRecipesOffice(false));
    window.setInterval(() => {
        const token = localStorage.getItem(recipeTokenKey);
        if (token && token !== recipeState.loadedToken) loadRecipesOffice(false);
        if (!token && recipeState.loadedToken) {
            recipeState.loadedToken = null;
            recipeElements.panel.classList.add('is-hidden');
        }
    }, 900);
    loadRecipesOffice(false);
}

bootMaterialRecipes();
