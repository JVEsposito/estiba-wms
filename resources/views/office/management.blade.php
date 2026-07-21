<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">

        <title>Estiba WMS · Panel gerencial</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-management.css', 'resources/js/office-management.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand management-access-brand">
                <div class="office-logo" aria-hidden="true">◆</div>
                <p class="eyebrow">ESTIBA WMS · VISIÓN EJECUTIVA</p>
                <h1 id="officeAccessTitle">La operación completa, en una sola mirada.</h1>
                <p>Información actual de inventario, ocupación y capacidad para apoyar decisiones de gerencia sin modificar la operación.</p>
                <div class="feature-row">
                    <span>Solo observación</span>
                    <span>Actualización automática</span>
                    <span>Indicadores trazables</span>
                </div>
            </div>

            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div>
                    <p class="eyebrow">PANEL GERENCIAL</p>
                    <h2>Ingresar al tablero</h2>
                    <p>Disponible para perfiles de consulta, supervisión y administración.</p>
                </div>
                <label>
                    <span>Correo electrónico</span>
                    <input name="email" type="email" autocomplete="username" placeholder="gerencia@empresa.cl" required>
                </label>
                <label>
                    <span>Contraseña</span>
                    <input name="password" type="password" autocomplete="current-password" placeholder="••••••••" required>
                </label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Abrir panel gerencial <span>→</span></button>
            </form>
        </section>

        <main class="office-app management-app is-hidden" id="officeApp">
            <header class="office-topbar management-topbar">
                <div class="brand-lockup">
                    <span class="office-logo office-logo--small" aria-hidden="true">◆</span>
                    <span><strong>ESTIBA WMS</strong><small>INTELIGENCIA OPERACIONAL</small></span>
                </div>
                <nav aria-label="Módulos de oficina">
                    <a class="is-active" href="/oficina/gerencia">Gerencia</a>
                    <a class="is-hidden" id="officeRomanaNav" href="/oficina/romana">Romana</a>
                    <a id="officeCamerasNav" href="/oficina/camaras">Cámaras</a>
                    <a id="officeLoadsNav" href="/oficina/cargas">Cargas</a>
                    <a id="officeMaterialsNav" href="/oficina/materiales">Materiales</a>
                    <a class="is-hidden" id="officePrefrioNav" href="/oficina/prefrio">Prefrío</a>
                    <a class="is-hidden" id="officeAccessesNav" href="/oficina/accesos">Accesos</a>
                </nav>
                <div class="identity">
                    <span class="live-indicator"><i></i><span>EN LÍNEA</span></span>
                    <span class="identity__avatar" id="officeInitials">GE</span>
                    <span><strong id="officeUserName">Gerencia</strong><small id="officeUserRole">Consulta</small></span>
                    <button id="officeLogoutButton" type="button">Cerrar sesión</button>
                </div>
            </header>

            <section class="management-heading">
                <div>
                    <p class="eyebrow">RESUMEN DE LA OPERACIÓN</p>
                    <h1>Panel gerencial</h1>
                    <p>Recepciones de romana, capacidad física, inventario utilizable y operación de prefrío al momento.</p>
                </div>
                <div class="management-refresh">
                    <div>
                        <span>ÚLTIMA ACTUALIZACIÓN</span>
                        <strong id="lastUpdatedAt">Sin actualizar</strong>
                        <small id="refreshStatus">Actualiza automáticamente cada 30 segundos</small>
                    </div>
                    <button class="refresh-button" id="refreshDashboardButton" type="button"><span aria-hidden="true">↻</span> Actualizar ahora</button>
                </div>
            </section>

            <section class="management-kpis" aria-label="Indicadores principales">
                <article class="management-kpi management-kpi--capacity">
                    <div class="management-kpi__top"><span>CAPACIDAD DE CÁMARAS</span><i aria-hidden="true">▦</i></div>
                    <strong id="availablePositionsKpi">—</strong>
                    <p>posiciones disponibles</p>
                    <div class="kpi-progress"><i id="cameraOccupancyProgress"></i></div>
                    <small id="cameraOccupancyDetail">—</small>
                </article>
                <article class="management-kpi management-kpi--product">
                    <div class="management-kpi__top"><span>PRODUCTO DISPONIBLE</span><i aria-hidden="true">◇</i></div>
                    <strong id="availableProductsKpi">—</strong>
                    <p>folios listos para despacho</p>
                    <div class="kpi-progress"><i id="productAvailabilityProgress"></i></div>
                    <small id="productAvailabilityDetail">—</small>
                </article>
                <article class="management-kpi management-kpi--material">
                    <div class="management-kpi__top"><span>INVENTARIO DE MATERIALES</span><i aria-hidden="true">▤</i></div>
                    <strong id="materialItemsKpi">—</strong>
                    <p>ítems con stock</p>
                    <div class="kpi-split"><span><b id="materialFoliosKpi">—</b> folios</span><span><b id="materialUnitsKpi">—</b> unidades de medida</span></div>
                    <small>Las cantidades se separan por unidad para evitar totales incompatibles.</small>
                </article>
                <article class="management-kpi management-kpi--prefrio">
                    <div class="management-kpi__top"><span>PREFRÍO</span><i aria-hidden="true">❄</i></div>
                    <strong id="precoolingAvailableKpi">—</strong>
                    <p>posiciones disponibles</p>
                    <div class="kpi-progress"><i id="precoolingOccupancyProgress"></i></div>
                    <small id="precoolingDetail">—</small>
                </article>
                <article class="management-kpi management-kpi--romana">
                    <div class="management-kpi__top"><span>RECEPCIÓN ROMANA</span><i aria-hidden="true">⚖</i></div>
                    <strong id="weighbridgeNetWeightKpi">—</strong>
                    <p>kg netos recibidos hoy</p>
                    <div class="kpi-split"><span><b id="weighbridgeClosedKpi">—</b> cerradas hoy</span><span><b id="weighbridgePendingKpi">—</b> pendientes destare</span></div>
                    <small id="weighbridgeDetail">—</small>
                </article>
            </section>

            <section class="management-grid">
                <article class="management-panel management-panel--wide">
                    <header>
                        <div><p class="eyebrow">USO DE INFRAESTRUCTURA</p><h2>Ocupación por cámara</h2></div>
                        <div class="chart-legend" aria-label="Leyenda"><span><i class="legend-dot legend-dot--occupied"></i>Ocupada</span><span><i class="legend-dot legend-dot--free"></i>Disponible</span></div>
                    </header>
                    <div class="chart-container chart-container--bar"><canvas id="cameraOccupancyChart" aria-label="Gráfico de ocupación por cámara" role="img"></canvas></div>
                </article>

                <article class="management-panel">
                    <header><div><p class="eyebrow">RECEPCIÓN DE CLIENTES</p><h2>Peso neto últimos 7 días</h2></div></header>
                    <div class="chart-container chart-container--bar"><canvas id="weighbridgeReceptionChart" aria-label="Gráfico de peso neto recibido en romana" role="img"></canvas></div>
                    <div class="chart-summary" id="weighbridgeChartSummary"></div>
                </article>

                <article class="management-panel">
                    <header><div><p class="eyebrow">FOLIOS ACTIVOS</p><h2>Disponibilidad de producto</h2></div></header>
                    <div class="chart-container chart-container--doughnut"><canvas id="productAvailabilityChart" aria-label="Gráfico de disponibilidad de producto" role="img"></canvas></div>
                    <div class="chart-summary" id="productChartSummary"></div>
                </article>

                <article class="management-panel management-panel--wide">
                    <header>
                        <div><p class="eyebrow">STOCK UTILIZABLE</p><h2>Materiales por ítem</h2></div>
                        <label class="chart-filter"><span>Unidad</span><select id="materialUnitSelect" aria-label="Unidad de medida para el gráfico"></select></label>
                    </header>
                    <div class="chart-container chart-container--bar"><canvas id="materialStockChart" aria-label="Gráfico de stock de materiales" role="img"></canvas></div>
                    <div class="chart-summary" id="materialChartSummary"></div>
                </article>

                <article class="management-panel">
                    <header><div><p class="eyebrow">CAPACIDAD TÉRMICA</p><h2>Ocupación de prefrío</h2></div></header>
                    <div class="chart-container chart-container--bar"><canvas id="precoolingChart" aria-label="Gráfico de ocupación de túneles" role="img"></canvas></div>
                    <div class="chart-summary" id="precoolingChartSummary"></div>
                </article>
            </section>

            <section class="management-detail-grid">
                <article class="management-panel management-table-panel">
                    <header><div><p class="eyebrow">DETALLE FÍSICO</p><h2>Capacidad por cámara</h2></div></header>
                    <div class="table-scroll">
                        <table>
                            <thead><tr><th>Cámara</th><th>Área</th><th>Ocupadas</th><th>Disponibles</th><th>No operativas</th><th>Uso</th></tr></thead>
                            <tbody id="cameraDetailRows"></tbody>
                        </table>
                    </div>
                </article>

                <article class="management-panel management-alerts-panel">
                    <header><div><p class="eyebrow">ATENCIÓN GERENCIAL</p><h2>Focos operacionales</h2></div><span class="alert-count" id="alertCount">0</span></header>
                    <div class="management-alerts" id="managementAlerts" aria-live="polite"></div>
                </article>
            </section>
        </main>

        <div class="loading is-hidden" id="officeLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="officeLoadingText">Actualizando indicadores…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
