<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">

        <title>Estiba WMS · Órdenes de carga</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-loads.css', 'resources/js/office-loads.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand loads-access-brand">
                <div class="office-logo" aria-hidden="true">⇥</div>
                <p class="eyebrow">ESTIBA WMS · DESPACHO</p>
                <h1 id="officeAccessTitle">Prepara la carga antes de mover un solo pallet.</h1>
                <p>Asigna folios, define prioridades y publica órdenes para que la operación trabaje con una referencia única y trazable.</p>
                <div class="feature-row">
                    <span>Máximo 26 folios</span>
                    <span>Control de versiones</span>
                    <span>Distribución por cámara</span>
                </div>
            </div>

            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div>
                    <p class="eyebrow">ACCESO DE OFICINA</p>
                    <h2>Ingresar a cargas</h2>
                    <p>Disponible para despachadores, supervisores y administradores.</p>
                </div>
                <label>
                    <span>Correo electrónico</span>
                    <input name="email" type="email" autocomplete="username" placeholder="despachador@empresa.cl" required>
                </label>
                <label>
                    <span>Contraseña</span>
                    <input name="password" type="password" autocomplete="current-password" placeholder="••••••••" required>
                </label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Entrar a cargas <span>→</span></button>
            </form>
        </section>

        <main class="office-app is-hidden" id="officeApp">
            <header class="office-topbar">
                <div class="brand-lockup">
                    <span class="office-logo office-logo--small" aria-hidden="true">❄</span>
                    <span><strong>ESTIBA WMS</strong><small>OPERACIÓN DE OFICINA</small></span>
                </div>
                <nav aria-label="Módulos de oficina">
                    <a id="officeCamerasNav" href="/oficina/camaras">Cámaras</a>
                    <a class="is-active" href="/oficina/cargas">Cargas</a>
                    <a class="is-hidden" id="officeAccessesNav" href="/oficina/accesos">Accesos</a>
                </nav>
                <div class="identity">
                    <span class="identity__avatar" id="officeInitials">DP</span>
                    <span><strong id="officeUserName">Despachador</strong><small id="officeUserRole">Despachador</small></span>
                    <button id="officeLogoutButton" type="button">Cerrar sesión</button>
                </div>
            </header>

            <section class="loads-workspace">
                <aside class="load-catalog panel">
                    <div class="panel-heading load-catalog__heading">
                        <div>
                            <p class="eyebrow">DESPACHO</p>
                            <h2>Órdenes de carga</h2>
                        </div>
                        <div class="catalog-actions">
                            <button class="icon-button" id="reloadLoadsButton" type="button" aria-label="Actualizar órdenes">↻</button>
                            <button class="primary-button compact-button" id="newLoadButton" type="button">+ Nueva</button>
                        </div>
                    </div>

                    <div class="load-filters">
                        <label>
                            <span>Buscar</span>
                            <input id="loadSearch" type="search" maxlength="100" placeholder="Código u orden externa">
                        </label>
                        <label>
                            <span>Estado</span>
                            <select id="loadStatusFilter">
                                <option value="">Todos</option>
                                <option value="borrador">Borrador</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="en_separacion">En separación</option>
                                <option value="separada">Separada</option>
                                <option value="separacion_completa">Separación completa</option>
                                <option value="despachada">Despachada</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </label>
                    </div>

                    <div class="load-catalog__status">
                        <div class="load-catalog__summary" id="loadCatalogSummary" role="status" aria-live="polite">0 órdenes</div>
                        <label class="page-size-control">
                            <span>Mostrar</span>
                            <select id="loadPageSize" aria-label="Órdenes por página">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                            </select>
                        </label>
                    </div>
                    <div class="load-catalog__list" id="loadList" aria-label="Listado de órdenes de carga"></div>
                    <div class="pagination" id="loadPagination" aria-label="Paginación de órdenes"></div>
                </aside>

                <section class="load-editor panel">
                    <div class="load-empty" id="loadEmptyState">
                        <span aria-hidden="true">⇥</span>
                        <p class="eyebrow">OFICINA DEL DESPACHADOR</p>
                        <h1>Selecciona una orden o crea un nuevo borrador.</h1>
                        <p>Los borradores pueden guardarse sin folios. La orden solo será visible para la operación cuando la publiques.</p>
                        <button class="primary-button" id="emptyNewLoadButton" type="button">Crear primera orden <span>→</span></button>
                    </div>

                    <div class="is-hidden" id="loadEditorContent">
                        <header class="load-editor__heading">
                            <div>
                                <p class="eyebrow" id="loadEditorEyebrow">NUEVA ORDEN</p>
                                <div class="load-title-row">
                                    <h1 id="loadEditorTitle">Nuevo borrador</h1>
                                    <span class="status-badge status-badge--draft" id="loadStatusBadge">Sin guardar</span>
                                    <span class="priority-badge is-hidden" id="loadPriorityBadge"></span>
                                </div>
                                <p id="loadEditorDescription">Crea el encabezado y luego incorpora los folios.</p>
                            </div>
                            <div class="load-audit is-hidden" id="loadAudit">
                                <span>ACTUALIZACIÓN</span>
                                <strong id="loadVersion">—</strong>
                                <small id="loadUpdatedAt">Sin guardar</small>
                            </div>
                        </header>

                        <form class="load-header-form" id="loadHeaderForm" novalidate>
                            <div class="load-form-grid">
                                <label class="field">
                                    <span>Número de orden externa</span>
                                    <input name="numero_orden_externa" maxlength="100" placeholder="Ej. OC-2026-001">
                                </label>
                                <label class="field">
                                    <span>Prioridad</span>
                                    <select name="prioridad">
                                        <option value="normal">Normal</option>
                                        <option value="alta">Alta</option>
                                        <option value="urgente">Urgente</option>
                                    </select>
                                </label>
                                <label class="field">
                                    <span>Cámara objetivo</span>
                                    <select name="camara_objetivo_id" id="targetCameraSelect">
                                        <option value="">Sin cámara objetivo</option>
                                    </select>
                                </label>
                                <label class="field field--full">
                                    <span>Observación operacional</span>
                                    <textarea name="observacion" maxlength="1000" rows="3" placeholder="Indicaciones para la preparación de esta carga"></textarea>
                                </label>
                            </div>
                            <p class="form-error" id="loadHeaderError" role="alert"></p>
                            <div class="load-header-actions">
                                <button class="secondary-button is-hidden" id="discardNewLoadButton" type="button">Descartar</button>
                                <button class="primary-button" id="saveLoadButton" type="submit"><span id="saveLoadButtonText">Crear borrador</span> <span>→</span></button>
                            </div>
                        </form>

                        <section class="load-operation is-hidden" id="loadOperation">
                            <div class="load-metrics">
                                <article><span>FOLIOS ASIGNADOS</span><strong id="loadTotalFolios">0 / 26</strong></article>
                                <article><span>CÁMARAS INVOLUCRADAS</span><strong id="loadTotalCameras">0</strong></article>
                                <article><span>ÚLTIMO CAMBIO</span><strong id="loadUpdatedBy">—</strong></article>
                            </div>

                            <div class="distribution-block">
                                <div>
                                    <p class="eyebrow">DISTRIBUCIÓN ACTUAL</p>
                                    <h2>Folios por cámara</h2>
                                </div>
                                <div class="distribution-list" id="loadDistribution"></div>
                            </div>

                            <div class="folio-add" id="folioAddSection">
                                <div>
                                    <p class="eyebrow">INCORPORACIÓN MASIVA</p>
                                    <h2 id="folioAddTitle">Agregar folios</h2>
                                    <p id="folioAddHelp">Pega o escanea uno o varios folios, separados por salto de línea, coma o punto y coma. El lote completo se rechaza si uno presenta problemas.</p>
                                </div>
                                <div class="folio-add__controls">
                                    <textarea id="folioInput" rows="5" aria-labelledby="folioAddTitle" aria-describedby="folioAddHelp folioInputCount" placeholder="FOLIO-001&#10;FOLIO-002&#10;FOLIO-003"></textarea>
                                    <div class="folio-add__footer">
                                        <span id="folioInputCount" aria-live="polite">0 folios únicos detectados</span>
                                        <button class="primary-button" id="addFoliosButton" type="button">Agregar a la orden</button>
                                    </div>
                                </div>
                                <section class="available-folios" aria-labelledby="availableFoliosTitle">
                                    <div class="available-folios__heading">
                                        <div>
                                            <p class="eyebrow">EXISTENCIA DISPONIBLE</p>
                                            <h3 id="availableFoliosTitle">Seleccionar desde inventario</h3>
                                        </div>
                                        <div class="available-folios__tools">
                                            <label>
                                                <span class="sr-only">Buscar folio disponible</span>
                                                <input id="availableFolioSearch" type="search" maxlength="100" placeholder="Folio, variedad, calibre, marca, cámara o posición">
                                            </label>
                                            <label class="page-size-control">
                                                <span>Mostrar</span>
                                                <select id="availableFolioPageSize" aria-label="Folios por página">
                                                    <option value="10" selected>10</option>
                                                    <option value="25">25</option>
                                                    <option value="50">50</option>
                                                </select>
                                            </label>
                                            <button class="icon-button" id="reloadAvailableFoliosButton" type="button" aria-label="Actualizar folios disponibles">↻</button>
                                        </div>
                                    </div>
                                    <p class="available-folios__summary" id="availableFolioSummary" role="status" aria-live="polite">Cargando existencia…</p>
                                    <div class="available-folios__table-scroll" id="availableFolioList">
                                        <table class="available-folios__table">
                                            <thead>
                                                <tr>
                                                    <th class="available-folios__check">
                                                        <input id="availableFolioSelectPage" type="checkbox" aria-label="Seleccionar todos los folios de esta página">
                                                    </th>
                                                    <th>Folio</th>
                                                    <th>Tipo</th>
                                                    <th>Variedad / calibre</th>
                                                    <th>Marca / exportadora</th>
                                                    <th>Condición SAG</th>
                                                    <th>Cámara</th>
                                                    <th>Posición</th>
                                                    <th>Ingreso</th>
                                                </tr>
                                            </thead>
                                            <tbody id="availableFolioTableBody"></tbody>
                                        </table>
                                    </div>
                                    <div class="available-folios__footer">
                                        <span>La selección se conserva al cambiar de página.</span>
                                        <div class="pagination" id="availableFolioPagination" aria-label="Paginación de folios disponibles"></div>
                                    </div>
                                </section>
                                <div class="folio-errors is-hidden" id="folioErrors" role="alert"></div>
                            </div>

                            <div class="folio-table-block">
                                <div class="folio-table-heading">
                                    <div>
                                        <p class="eyebrow">DETALLE DE LA ORDEN</p>
                                        <h2>Folios asignados</h2>
                                    </div>
                                    <span id="folioTableHint">El borrador todavía no es visible para el camarero.</span>
                                </div>
                                <div class="table-scroll">
                                    <table class="folio-table">
                                        <thead>
                                            <tr>
                                                <th>Folio</th>
                                                <th>Tipo</th>
                                                <th>Ubicación actual</th>
                                                <th>Asignado</th>
                                                <th><span class="sr-only">Acciones</span></th>
                                            </tr>
                                        </thead>
                                        <tbody id="folioTableBody"></tbody>
                                    </table>
                                </div>
                            </div>

                            <footer class="load-command-bar" id="loadCommandBar">
                                <div>
                                    <strong id="loadCommandTitle">Orden en borrador</strong>
                                    <span id="loadCommandDescription">Publica la orden cuando la asignación esté lista.</span>
                                </div>
                                <div>
                                    <button class="danger-button" id="cancelLoadButton" type="button">Cancelar orden</button>
                                    <button class="primary-button" id="publishLoadButton" type="button">Publicar para operación <span>→</span></button>
                                </div>
                            </footer>
                        </section>
                    </div>
                </section>
            </section>
        </main>

        <div class="loading is-hidden" id="officeLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="officeLoadingText">Procesando…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
