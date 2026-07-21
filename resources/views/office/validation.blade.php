<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">
        <title>Estiba WMS · Validación de pallets</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-validation.css', 'resources/js/office-validation.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand validation-access-brand">
                <div class="office-logo" aria-hidden="true">✓</div>
                <p class="eyebrow">ESTIBA WMS · VALIDACIÓN</p>
                <h1 id="officeAccessTitle">El punto de nacimiento trazable de cada pallet.</h1>
                <p>Consulta la temporada transversal, carga sus catálogos y revisa aprobaciones, observaciones y conflictos enviados desde terreno.</p>
                <div class="feature-row"><span>Catálogo por temporada</span><span>Importación auditable</span><span>Intentos inmutables</span></div>
            </div>
            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div><p class="eyebrow">ACCESO DE OFICINA</p><h2>Ingresar a validación</h2><p>Disponible para administración y supervisión de frío.</p></div>
                <label><span>Correo electrónico</span><input name="email" type="email" autocomplete="username" required></label>
                <label><span>Contraseña</span><input name="password" type="password" autocomplete="current-password" required></label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Entrar a validación <span>→</span></button>
            </form>
        </section>

        <main class="office-app is-hidden" id="officeApp">
            <header class="office-topbar">
                <div class="brand-lockup"><span class="office-logo office-logo--small" aria-hidden="true">✓</span><span><strong>ESTIBA WMS</strong><small>VALIDACIÓN</small></span></div>
                <nav aria-label="Módulos de oficina">
                    <a class="is-hidden" id="officeManagementNav" href="/oficina/gerencia">Gerencia</a>
                    <a class="is-hidden" id="officeRomanaNav" href="/oficina/romana">Romana</a>
                    <a id="officeCamerasNav" href="/oficina/camaras">Cámaras</a>
                    <a id="officeLoadsNav" href="/oficina/cargas">Cargas</a>
                    <a id="officeMaterialsNav" href="/oficina/materiales">Materiales</a>
                    <a class="is-active" href="/oficina/validacion">Validación</a>
                    <a class="is-hidden" id="officePrefrioNav" href="/oficina/prefrio">Prefrío</a>
                    <a class="is-hidden" id="officeAccessesNav" href="/oficina/accesos">Accesos</a>
                </nav>
                <div class="identity"><span class="identity__avatar" id="officeInitials">VP</span><span><strong id="officeUserName">Usuario</strong><small id="officeUserRole">Oficina</small></span><button id="officeLogoutButton" type="button">Cerrar sesión</button></div>
            </header>

            <section class="validation-workspace">
                <header class="validation-heading panel">
                    <div><p class="eyebrow">CONTROL DE INGRESO</p><h1>Validación de pallets</h1><p>La aprobación crea el folio pendiente de prefrío; observar conserva la posibilidad de corregir y volver a validar.</p></div>
                    <div class="validation-heading__actions">
                        <a class="secondary-button is-hidden" id="hierarchyCatalogLink" href="/oficina/validacion/catalogo">Configurar catálogo</a>
                        <label><span>Temporada visible</span><select id="seasonSelector"></select></label>
                        <button class="secondary-button" id="reloadValidationButton" type="button">↻ Actualizar</button>
                    </div>
                </header>

                <div class="validation-metrics">
                    <article><span>VERSIÓN CATÁLOGO</span><strong id="catalogVersion">—</strong></article>
                    <article><span>ARTÍCULOS ACTIVOS</span><strong id="activeArticleCount">0</strong></article>
                    <article><span>ORÍGENES ACTIVOS</span><strong id="activeOriginCount">0</strong></article>
                    <article><span>COMBINACIONES ACTIVAS</span><strong id="activeCombinationCount">0</strong></article>
                    <article><span>OBSERVADOS RECIENTES</span><strong id="observedCount">0</strong></article>
                </div>

                <section class="panel validation-history-panel">
                    <div class="validation-panel__heading">
                        <div><p class="eyebrow">TRAZABILIDAD</p><h2>Validaciones recientes</h2></div>
                        <form class="validation-filters" id="validationFilters">
                            <input name="folio" maxlength="50" placeholder="Buscar folio">
                            <select name="resultado"><option value="">Todos los resultados</option><option value="aprobado">Aprobado</option><option value="observado">Observado</option><option value="rechazado">Rechazado</option></select>
                            <select name="estado"><option value="">Todos los estados</option><option value="aceptada">Aceptada</option><option value="conflicto">Conflicto</option></select>
                            <button class="secondary-button" type="submit">Filtrar</button>
                        </form>
                    </div>
                    <div class="validation-table-scroll"><table class="validation-table"><thead><tr><th>Folio</th><th>Artículo</th><th>Origen</th><th>Resultado</th><th>Validador</th><th>Fecha</th></tr></thead><tbody id="validationHistoryBody"></tbody></table></div>
                </section>

                <div class="validation-admin is-hidden" id="validationAdmin">
                    <div class="validation-admin-grid">
                        <section class="panel validation-panel">
                            <div class="validation-panel__heading"><div><p class="eyebrow">TEMPORADA TRANSVERSAL</p><h2>Configuración de solo lectura</h2></div><span id="seasonStatus">Sin temporada</span></div>
                            <p class="validation-help">La temporada se crea, edita y activa exclusivamente desde la oficina Accesos. Aquí se selecciona para administrar su catálogo de Validación.</p>
                            <div class="validation-actions"><a class="secondary-button is-hidden" id="seasonAccessLink" href="/oficina/accesos">Administrar en Accesos</a></div>
                            <div class="validation-list" id="seasonList"></div>
                        </section>

                        <section class="panel validation-panel validation-import-panel">
                            <div class="validation-panel__heading"><div><p class="eyebrow">CARGA MASIVA</p><h2>Importar planilla</h2></div><span>CSV o XLSX · 10 MB</span></div>
                            <p class="validation-help">Columnas obligatorias: especie, variedad, calibre, envase, cliente, marca y CSG. Categoría, predio y códigos externos son opcionales; cada categoría importada queda disponible para todas las especies y marcas.</p>
                            <form class="validation-form" id="importForm" enctype="multipart/form-data">
                                <label class="validation-file"><span>Archivo de temporada *</span><input name="archivo" type="file" accept=".csv,.txt,.xlsx" required></label>
                                <p class="form-error" id="importError" role="alert"></p>
                                <div class="validation-actions"><button class="primary-button" type="submit">Previsualizar importación</button></div>
                            </form>
                            <div class="import-preview is-hidden" id="importPreview"></div>
                            <div class="validation-list" id="importList"></div>
                        </section>
                    </div>

                    <section class="panel validation-panel">
                        <div class="validation-panel__heading"><div><p class="eyebrow">CATÁLOGO NORMALIZADO</p><h2>Configuración individual y jerárquica</h2></div><a class="secondary-button" href="/oficina/validacion/catalogo">Abrir configuración</a></div>
                        <p class="validation-help">Crea clientes y marcas; categorías independientes; especies con sus variedades, calibres y envases; y autoriza variedades por CSG. Los artículos y orígenes de la PDA se generan automáticamente.</p>
                    </section>

                    <div class="validation-catalog-grid is-hidden">
                        <section class="panel validation-panel">
                            <div class="validation-panel__heading"><div><p class="eyebrow">FRUTA</p><h2>Artículos</h2></div><span id="articleSummary">0 registrados</span></div>
                            <form class="validation-form" id="articleForm" novalidate>
                                <input name="id" type="hidden">
                                <div class="validation-form__grid">
                                    <label><span>Especie *</span><input name="especie" maxlength="100" required></label>
                                    <label><span>Variedad *</span><input name="variedad" maxlength="100" required></label>
                                    <label><span>Calibre *</span><input name="calibre" maxlength="50" required></label>
                                    <label><span>Envase *</span><input name="envase" maxlength="100" required></label>
                                    <label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label>
                                    <label class="validation-check"><input name="activo" type="checkbox" checked><span>Artículo activo</span></label>
                                </div>
                                <p class="form-error" id="articleError" role="alert"></p>
                                <div class="validation-actions"><button class="secondary-button is-hidden" id="cancelArticleEdit" type="button">Cancelar</button><button class="primary-button" type="submit">Guardar artículo</button></div>
                            </form>
                            <div class="validation-list" id="articleList"></div>
                        </section>

                        <section class="panel validation-panel">
                            <div class="validation-panel__heading"><div><p class="eyebrow">ORIGEN COMERCIAL</p><h2>Clientes, marcas y CSG</h2></div><span id="originSummary">0 registrados</span></div>
                            <form class="validation-form" id="originForm" novalidate>
                                <input name="id" type="hidden">
                                <div class="validation-form__grid">
                                    <label><span>Cliente *</span><input name="cliente" maxlength="150" required></label>
                                    <label><span>Marca *</span><input name="marca" maxlength="150" required></label>
                                    <label><span>CSG *</span><input name="csg" maxlength="50" required></label>
                                    <label><span>Predio</span><input name="predio" maxlength="150"></label>
                                    <label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label>
                                    <label class="validation-check"><input name="activo" type="checkbox" checked><span>Origen activo</span></label>
                                </div>
                                <p class="form-error" id="originError" role="alert"></p>
                                <div class="validation-actions"><button class="secondary-button is-hidden" id="cancelOriginEdit" type="button">Cancelar</button><button class="primary-button" type="submit">Guardar origen</button></div>
                            </form>
                            <div class="validation-list" id="originList"></div>
                        </section>
                    </div>

                    <section class="panel validation-panel is-hidden">
                        <div class="validation-panel__heading"><div><p class="eyebrow">REGLA DE SELECCIÓN</p><h2>Combinaciones artículo–origen habilitadas</h2></div><span id="combinationSummary">0 registradas</span></div>
                        <form class="validation-form validation-combination-form" id="combinationForm" novalidate>
                            <input name="id" type="hidden">
                            <label><span>Artículo *</span><select name="articulo_validacion_id" required></select></label>
                            <label><span>Origen *</span><select name="origen_validacion_id" required></select></label>
                            <label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label>
                            <label class="validation-check"><input name="activo" type="checkbox" checked><span>Combinación activa</span></label>
                            <p class="form-error" id="combinationError" role="alert"></p>
                            <div class="validation-actions"><button class="secondary-button is-hidden" id="cancelCombinationEdit" type="button">Cancelar</button><button class="primary-button" type="submit">Guardar combinación</button></div>
                        </form>
                        <div class="validation-list validation-combination-list" id="combinationList"></div>
                    </section>
                </div>
            </section>
        </main>

        <div class="loading is-hidden" id="officeLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="officeLoadingText">Procesando…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
