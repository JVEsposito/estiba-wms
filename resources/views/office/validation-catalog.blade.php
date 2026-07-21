<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">
        <title>Estiba WMS · Catálogo jerárquico</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-validation.css', 'resources/css/office-validation-catalog.css', 'resources/js/office-validation-catalog.js'])
        @endif
    </head>
    <body>
        <main class="office-app" id="catalogApp">
            <header class="office-topbar">
                <div class="brand-lockup"><span class="office-logo office-logo--small" aria-hidden="true">✓</span><span><strong>ESTIBA WMS</strong><small>CATÁLOGO DE VALIDACIÓN</small></span></div>
                <nav aria-label="Módulos de oficina">
                    <a href="/oficina/romana">Romana</a>
                    <a href="/oficina/validacion">← Volver a Validación</a>
                </nav>
                <div class="identity"><span class="identity__avatar" id="catalogInitials">AD</span><span><strong id="catalogUserName">Administrador</strong><small>Catálogo jerárquico</small></span><button id="catalogLogout" type="button">Cerrar sesión</button></div>
            </header>

            <section class="catalog-workspace">
                <header class="validation-heading panel">
                    <div><p class="eyebrow">CONFIGURACIÓN MAESTRA</p><h1>Catálogo jerárquico</h1><p>Los elementos se crean individualmente. La proyección para la PDA se actualiza automáticamente al guardar.</p></div>
                    <div class="validation-heading__actions">
                        <label><span>Temporada</span><select id="catalogSeasonSelector"></select></label>
                        <button class="secondary-button" id="catalogReload" type="button">↻ Actualizar</button>
                    </div>
                </header>

                <section class="panel catalog-season-panel">
                    <div class="validation-panel__heading"><div><p class="eyebrow">TEMPORADAS</p><h2>Crear temporada</h2></div><span>Vacía o copia completa</span></div>
                    <form class="catalog-inline-form" id="catalogSeasonForm">
                        <label><span>Código *</span><input name="codigo" maxlength="30" required></label>
                        <label><span>Nombre *</span><input name="nombre" maxlength="100" required></label>
                        <label><span>Copiar catálogo desde</span><select name="copiar_desde_temporada_id"><option value="">Comenzar vacía</option></select></label>
                        <label class="validation-check"><input name="activa" type="checkbox"><span>Dejar activa</span></label>
                        <button class="primary-button" type="submit">Crear temporada</button>
                    </form>
                    <p class="form-error" id="catalogSeasonError"></p>
                </section>

                <section aria-labelledby="catalogProjectionTitle">
                    <div class="validation-panel__heading catalog-projection-heading">
                        <div><p class="eyebrow">PROYECCIÓN PDA</p><h2 id="catalogProjectionTitle">Registros activos generados</h2></div>
                        <span>Se actualizan automáticamente con la jerarquía</span>
                    </div>
                    <div class="validation-metrics catalog-projection-metrics">
                        <article><span>ARTÍCULOS</span><strong id="projectionArticleCount">0</strong></article>
                        <article><span>ORÍGENES</span><strong id="projectionOriginCount">0</strong></article>
                        <article><span>COMBINACIONES</span><strong id="projectionCombinationCount">0</strong></article>
                    </div>
                </section>

                <div class="catalog-columns catalog-columns--three">
                    <section class="panel catalog-card">
                        <div class="validation-panel__heading"><div><p class="eyebrow">BASE COMERCIAL</p><h2>Clientes</h2></div><span id="clientCount">0</span></div>
                        <form class="catalog-form" id="clientForm">
                            <input name="id" type="hidden">
                            <label><span>Nombre *</span><input name="nombre" maxlength="150" required></label>
                            <label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label>
                            <label class="validation-check"><input name="activo" type="checkbox" checked><span>Activo</span></label>
                            <div class="catalog-actions"><button class="secondary-button" data-reset-form="clientForm" type="button">Limpiar</button><button class="primary-button" type="submit">Guardar cliente</button></div>
                        </form>
                        <p class="form-error" id="clientError"></p><div class="validation-list" id="clientList"></div>
                    </section>

                    <section class="panel catalog-card">
                        <div class="validation-panel__heading"><div><p class="eyebrow">CLIENTE → MARCA</p><h2>Marcas</h2></div><span id="brandCount">0</span></div>
                        <form class="catalog-form" id="brandForm">
                            <input name="id" type="hidden">
                            <label><span>Cliente *</span><select name="cliente_validacion_id" required></select></label>
                            <label><span>Nombre *</span><input name="nombre" maxlength="150" required></label>
                            <label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label>
                            <label class="validation-check"><input name="activo" type="checkbox" checked><span>Activa</span></label>
                            <div class="catalog-actions"><button class="secondary-button" data-reset-form="brandForm" type="button">Limpiar</button><button class="primary-button" type="submit">Guardar marca</button></div>
                        </form>
                        <p class="form-error" id="brandError"></p><div class="validation-list" id="brandList"></div>
                    </section>

                    <section class="panel catalog-card">
                        <div class="validation-panel__heading"><div><p class="eyebrow">INDEPENDIENTE</p><h2>Categorías</h2></div><span id="categoryCount">0</span></div>
                        <form class="catalog-form" id="categoryForm">
                            <input name="id" type="hidden">
                            <label><span>Nombre *</span><input name="nombre" maxlength="100" required></label>
                            <label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label>
                            <label class="validation-check"><input name="activo" type="checkbox" checked><span>Activa</span></label>
                            <div class="catalog-actions"><button class="secondary-button" data-reset-form="categoryForm" type="button">Limpiar</button><button class="primary-button" type="submit">Guardar categoría</button></div>
                        </form>
                        <p class="form-error" id="categoryError"></p><div class="validation-list" id="categoryList"></div>
                    </section>
                </div>

                <div class="catalog-columns">
                    <section class="panel catalog-card">
                        <div class="validation-panel__heading"><div><p class="eyebrow">BASE PRODUCTIVA</p><h2>Especies</h2></div><span id="speciesCount">0</span></div>
                        <form class="catalog-form" id="speciesForm">
                            <input name="id" type="hidden">
                            <label><span>Nombre *</span><input name="nombre" maxlength="100" required></label>
                            <label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label>
                            <label class="validation-check"><input name="activo" type="checkbox" checked><span>Activa</span></label>
                            <div class="catalog-actions"><button class="secondary-button" data-reset-form="speciesForm" type="button">Limpiar</button><button class="primary-button" type="submit">Guardar especie</button></div>
                        </form>
                        <p class="form-error" id="speciesError"></p><div class="validation-list" id="speciesList"></div>
                    </section>

                    <section class="panel catalog-card">
                        <div class="validation-panel__heading"><div><p class="eyebrow">ESPECIE → VARIEDAD</p><h2>Variedades</h2></div><span id="varietyCount">0</span></div>
                        <form class="catalog-form" id="varietyForm">
                            <input name="id" type="hidden">
                            <label><span>Especie *</span><select name="especie_validacion_id" required></select></label>
                            <label><span>Nombre *</span><input name="nombre" maxlength="100" required></label>
                            <label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label>
                            <label class="validation-check"><input name="activo" type="checkbox" checked><span>Activa</span></label>
                            <div class="catalog-actions"><button class="secondary-button" data-reset-form="varietyForm" type="button">Limpiar</button><button class="primary-button" type="submit">Guardar variedad</button></div>
                        </form>
                        <p class="form-error" id="varietyError"></p><div class="validation-list" id="varietyList"></div>
                    </section>
                </div>

                <div class="catalog-columns catalog-columns--three">
                    <section class="panel catalog-card">
                        <div class="validation-panel__heading"><div><p class="eyebrow">POR ESPECIE</p><h2>Calibres</h2></div><span id="caliberCount">0</span></div>
                        <form class="catalog-form" id="caliberForm">
                            <input name="id" type="hidden"><label><span>Especie *</span><select name="especie_validacion_id" required></select></label><label><span>Calibre *</span><input name="nombre" maxlength="50" required></label><label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label><label class="validation-check"><input name="activo" type="checkbox" checked><span>Activo</span></label>
                            <div class="catalog-actions"><button class="secondary-button" data-reset-form="caliberForm" type="button">Limpiar</button><button class="primary-button" type="submit">Guardar calibre</button></div>
                        </form><p class="form-error" id="caliberError"></p><div class="validation-list" id="caliberList"></div>
                    </section>
                    <section class="panel catalog-card">
                        <div class="validation-panel__heading"><div><p class="eyebrow">POR ESPECIE</p><h2>Envases</h2></div><span id="packageCount">0</span></div>
                        <form class="catalog-form" id="packageForm">
                            <input name="id" type="hidden"><label><span>Especie *</span><select name="especie_validacion_id" required></select></label><label><span>Envase *</span><input name="nombre" maxlength="100" required></label><label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label><label class="validation-check"><input name="activo" type="checkbox" checked><span>Activo</span></label>
                            <div class="catalog-actions"><button class="secondary-button" data-reset-form="packageForm" type="button">Limpiar</button><button class="primary-button" type="submit">Guardar envase</button></div>
                        </form><p class="form-error" id="packageError"></p><div class="validation-list" id="packageList"></div>
                    </section>
                    <section class="panel catalog-card">
                        <div class="validation-panel__heading"><div><p class="eyebrow">CSG → VARIEDADES</p><h2>CSG</h2></div><span id="csgCount">0</span></div>
                        <form class="catalog-form" id="csgForm">
                            <input name="id" type="hidden"><label><span>Código CSG *</span><input name="codigo" maxlength="50" required></label><label><span>Predio</span><input name="predio" maxlength="150"></label><label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label>
                            <fieldset class="catalog-varieties"><legend>Variedades autorizadas *</legend><div id="csgVarietyOptions"></div></fieldset>
                            <label class="validation-check"><input name="activo" type="checkbox" checked><span>Activo</span></label>
                            <div class="catalog-actions"><button class="secondary-button" data-reset-form="csgForm" type="button">Limpiar</button><button class="primary-button" type="submit">Guardar CSG</button></div>
                        </form><p class="form-error" id="csgError"></p><div class="validation-list" id="csgList"></div>
                    </section>
                </div>
            </section>
        </main>
        <div class="loading is-hidden" id="catalogLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="catalogLoadingText">Procesando…</strong></div>
        <div class="toast-region" id="catalogToasts" aria-live="polite"></div>
    </body>
</html>
