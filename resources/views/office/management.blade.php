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
            <x-office.navigation domain="administracion" office="panel" context="ADMINISTRACIÓN & GERENCIA" icon="◆" />

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
                    <small id="materialOperationDetail">Las cantidades se separan por unidad para evitar totales incompatibles.</small>
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
                    <div class="kpi-split"><span><b id="weighbridgeClosedKpi">—</b> cerradas hoy</span><span><b id="weighbridgePendingKpi">—</b> pendientes de cierre</span></div>
                    <small id="weighbridgeDetail">—</small>
                </article>
            </section>

            <div class="management-switcher-shell">
                <x-office.panel-switcher
                    id="management"
                    label="Áreas del panel gerencial"
                    default="alerts"
                    :panels="[
                        'alerts' => ['label' => 'Focos', 'icon' => '!', 'badge_id' => 'alertTabCount'],
                        'cameras' => ['label' => 'Cámaras', 'icon' => '▦'],
                        'products' => ['label' => 'Producto PT', 'icon' => '◇'],
                        'loads' => ['label' => 'Cargas', 'icon' => '↗'],
                        'validation' => ['label' => 'Validación', 'icon' => '✓'],
                        'precooling' => ['label' => 'Prefrío', 'icon' => '❄'],
                        'materials' => ['label' => 'Materiales', 'icon' => '▤'],
                        'weighbridge' => ['label' => 'Romana', 'icon' => '⚖'],
                        'raw-material' => ['label' => 'MP y Envases', 'icon' => '▣'],
                    ]"
                />
            </div>

            <section class="management-panel-workspace">
                <div
                    class="management-view management-view--single"
                    id="management-panel-alerts"
                    data-office-panel-group="management"
                    data-office-panel-id="alerts"
                    role="tabpanel"
                    aria-labelledby="management-tab-alerts"
                >
                    <article class="management-panel management-alerts-panel">
                        <header>
                            <div><p class="eyebrow">ATENCIÓN GERENCIAL</p><h2>Focos operacionales priorizados</h2></div>
                            <span class="alert-count" id="alertCount">0</span>
                        </header>
                        <p class="management-panel-intro">Muestra excepciones que requieren acción, ordenadas por criticidad y enlazadas con la oficina responsable.</p>
                        <div class="management-alerts" id="managementAlerts" aria-live="polite"></div>
                    </article>
                </div>

                <div
                    class="management-view management-view--cameras"
                    id="management-panel-cameras"
                    data-office-panel-group="management"
                    data-office-panel-id="cameras"
                    role="tabpanel"
                    aria-labelledby="management-tab-cameras"
                >
                    <article class="management-panel">
                        <header>
                            <div><p class="eyebrow">USO DE INFRAESTRUCTURA</p><h2>Ocupación por cámara</h2></div>
                            <div class="management-panel-actions">
                                <div class="chart-legend" aria-label="Leyenda"><span><i class="legend-dot legend-dot--occupied"></i>Ocupada</span><span><i class="legend-dot legend-dot--free"></i>Disponible</span></div>
                                <a class="management-panel-link" href="/oficina/frigorifico/camaras">Abrir cámaras →</a>
                            </div>
                        </header>
                        <div class="chart-container chart-container--bar"><canvas id="cameraOccupancyChart" aria-label="Gráfico de ocupación por cámara" role="img"></canvas></div>
                    </article>

                    <article class="management-panel management-table-panel">
                        <header><div><p class="eyebrow">DETALLE FÍSICO</p><h2>Capacidad por cámara</h2></div></header>
                        <div class="table-scroll">
                            <table>
                                <thead><tr><th>Cámara</th><th>Área</th><th>Ocupadas</th><th>Disponibles</th><th>No operativas</th><th>Uso</th></tr></thead>
                                <tbody id="cameraDetailRows"></tbody>
                            </table>
                        </div>
                    </article>
                </div>

                <div
                    class="management-view management-view--single"
                    id="management-panel-products"
                    data-office-panel-group="management"
                    data-office-panel-id="products"
                    role="tabpanel"
                    aria-labelledby="management-tab-products"
                >
                    <article class="management-panel">
                        <header>
                            <div><p class="eyebrow">PRODUCTO TERMINADO</p><h2>Estado y disponibilidad de folios</h2></div>
                            <a class="management-panel-link" href="/oficina/frigorifico/existencias">Existencia PT →</a>
                        </header>
                        <div class="management-chart-with-metrics">
                            <div>
                                <div class="chart-container chart-container--doughnut"><canvas id="productAvailabilityChart" aria-label="Gráfico de disponibilidad de producto" role="img"></canvas></div>
                                <div class="chart-summary" id="productChartSummary"></div>
                            </div>
                            <div class="management-operational-metrics">
                                <article><span>PALLETS</span><strong id="productPalletsMetric">0</strong><small>folios completos activos</small></article>
                                <article><span>SALDOS</span><strong id="productBalancesMetric">0</strong><small>folios incompletos activos</small></article>
                                <article><span>SIN UBICACIÓN</span><strong id="productUnlocatedMetric">0</strong><small>requieren posición</small></article>
                                <article><span>INGRESADOS HOY</span><strong id="productEnteredTodayMetric">0</strong><small>nuevos folios PT</small></article>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    class="management-view management-view--single"
                    id="management-panel-loads"
                    data-office-panel-group="management"
                    data-office-panel-id="loads"
                    role="tabpanel"
                    aria-labelledby="management-tab-loads"
                >
                    <article class="management-panel">
                        <header>
                            <div><p class="eyebrow">DESPACHO DE PRODUCTO TERMINADO</p><h2>Cargas activas y avance operativo</h2></div>
                            <a class="management-panel-link" href="/oficina/cargas">Gestionar cargas →</a>
                        </header>
                        <div class="management-operational-metrics management-operational-metrics--eight">
                            <article><span>CARGAS ACTIVAS</span><strong id="activeLoadsMetric">0</strong><small>en circuito</small></article>
                            <article><span>PENDIENTES</span><strong id="pendingLoadsMetric">0</strong><small>sin preparar</small></article>
                            <article><span>EN PREPARACIÓN</span><strong id="preparingLoadsMetric">0</strong><small>separación activa</small></article>
                            <article><span>SEPARADAS</span><strong id="separatedLoadsMetric">0</strong><small>listas o completas</small></article>
                            <article><span>FOLIOS PENDIENTES</span><strong id="loadFoliosPendingMetric">0</strong><small>por enviar</small></article>
                            <article><span>EN ANDÉN</span><strong id="loadFoliosDockMetric">0</strong><small>preparados para salida</small></article>
                            <article><span>INCIDENCIAS</span><strong id="loadIncidentsMetric">0</strong><small>requieren resolución</small></article>
                            <article><span>CERRADAS HOY</span><strong id="loadsClosedTodayMetric">0</strong><small>despachos finalizados</small></article>
                        </div>
                        <div class="management-operation-list" id="managementLoadList"></div>
                    </article>
                </div>

                <div
                    class="management-view management-view--single"
                    id="management-panel-validation"
                    data-office-panel-group="management"
                    data-office-panel-id="validation"
                    role="tabpanel"
                    aria-labelledby="management-tab-validation"
                >
                    <article class="management-panel">
                        <header>
                            <div><p class="eyebrow">CONTROL DE PRODUCTO TERMINADO</p><h2>Validación de pallets de hoy</h2></div>
                            <a class="management-panel-link" href="/oficina/validacion">Abrir Validación →</a>
                        </header>
                        <div class="management-operational-metrics management-operational-metrics--five">
                            <article><span>PROCESADOS</span><strong id="validationProcessedMetric">0</strong><small>registros recibidos</small></article>
                            <article class="is-positive"><span>APROBADOS</span><strong id="validationApprovedMetric">0</strong><small>sin observación</small></article>
                            <article class="is-warning"><span>OBSERVADOS</span><strong id="validationObservedMetric">0</strong><small>requieren revisión</small></article>
                            <article class="is-critical"><span>RECHAZADOS</span><strong id="validationRejectedMetric">0</strong><small>no liberados</small></article>
                            <article class="is-critical"><span>CONFLICTOS</span><strong id="validationConflictsMetric">0</strong><small>sincronización</small></article>
                        </div>
                        <div class="management-latest-event" id="managementValidationLatest"></div>
                    </article>
                </div>

                <div
                    class="management-view management-view--single"
                    id="management-panel-precooling"
                    data-office-panel-group="management"
                    data-office-panel-id="precooling"
                    role="tabpanel"
                    aria-labelledby="management-tab-precooling"
                >
                    <article class="management-panel">
                        <header>
                            <div><p class="eyebrow">CAPACIDAD TÉRMICA</p><h2>Ocupación, cola y cumplimiento de tiempo</h2></div>
                            <a class="management-panel-link" href="/oficina/prefrio">Abrir Prefrío →</a>
                        </header>
                        <div class="management-chart-with-metrics">
                            <div>
                                <div class="chart-container chart-container--bar"><canvas id="precoolingChart" aria-label="Gráfico de ocupación de túneles" role="img"></canvas></div>
                                <div class="chart-summary" id="precoolingChartSummary"></div>
                            </div>
                            <div>
                                <div class="management-operational-metrics">
                                    <article><span>ACTIVOS</span><strong id="precoolingActiveMetric">0</strong><small>procesos abiertos</small></article>
                                    <article class="is-critical"><span>ATRASADOS</span><strong id="precoolingOverdueMetric">0</strong><small>sobre objetivo</small></article>
                                    <article class="is-positive"><span>APROBADOS HOY</span><strong id="precoolingApprovedMetric">0</strong><small>procesos cerrados</small></article>
                                    <article class="is-warning"><span>REPROCESOS HOY</span><strong id="precoolingReprocessMetric">0</strong><small>requieren nuevo ciclo</small></article>
                                </div>
                                <div class="management-average"><span>PROMEDIO ÚLTIMOS 7 DÍAS</span><strong id="precoolingAverageMetric">—</strong></div>
                            </div>
                        </div>
                        <div class="management-operation-list" id="managementPrecoolingList"></div>
                    </article>
                </div>

                <div
                    class="management-view management-view--single"
                    id="management-panel-materials"
                    data-office-panel-group="management"
                    data-office-panel-id="materials"
                    role="tabpanel"
                    aria-labelledby="management-tab-materials"
                >
                    <article class="management-panel">
                        <header>
                            <div><p class="eyebrow">BODEGA DE MATERIALES</p><h2>Stock utilizable y operación pendiente</h2></div>
                            <div class="management-panel-actions">
                                <label class="chart-filter"><span>Unidad</span><select id="materialUnitSelect" aria-label="Unidad de medida para el gráfico"></select></label>
                                <a class="management-panel-link" href="/oficina/materiales/exportaciones">Exportar materiales →</a>
                            </div>
                        </header>
                        <div class="management-operational-metrics management-operational-metrics--four">
                            <article><span>DESPACHOS ABIERTOS</span><strong id="materialOpenDispatchesMetric">0</strong><small>pendientes o parciales</small></article>
                            <article class="is-warning"><span>PARCIALES</span><strong id="materialPartialDispatchesMetric">0</strong><small>retiro incompleto</small></article>
                            <article class="is-positive"><span>RECEPCIONES HOY</span><strong id="materialReceptionsTodayMetric">0</strong><small>confirmadas</small></article>
                            <article><span>BORRADORES</span><strong id="materialReceptionDraftsMetric">0</strong><small>sin confirmar</small></article>
                        </div>
                        <div class="chart-container chart-container--bar"><canvas id="materialStockChart" aria-label="Gráfico de stock de materiales" role="img"></canvas></div>
                        <div class="chart-summary" id="materialChartSummary"></div>
                    </article>
                </div>

                <div
                    class="management-view management-view--single"
                    id="management-panel-weighbridge"
                    data-office-panel-group="management"
                    data-office-panel-id="weighbridge"
                    role="tabpanel"
                    aria-labelledby="management-tab-weighbridge"
                >
                    <article class="management-panel">
                        <header>
                            <div><p class="eyebrow">RECEPCIÓN DE MATERIA PRIMA</p><h2>Romana: flujo actual y últimos 7 días</h2></div>
                            <a class="management-panel-link" href="/oficina/romana">Abrir Romana →</a>
                        </header>
                        <div class="management-operational-metrics management-operational-metrics--four">
                            <article><span>EN INGRESO</span><strong id="weighbridgeEntryMetric">0</strong><small>sobre báscula</small></article>
                            <article><span>PESAJE ENVASES</span><strong id="weighbridgeContainersMetric">0</strong><small>tandas abiertas</small></article>
                            <article class="is-warning"><span>PENDIENTES DESTARE</span><strong id="weighbridgeTareMetric">0</strong><small>esperan cierre</small></article>
                            <article><span>CLIENTES HOY</span><strong id="weighbridgeClientsMetric">0</strong><small>con recepción cerrada</small></article>
                        </div>
                        <div class="chart-container chart-container--bar"><canvas id="weighbridgeReceptionChart" aria-label="Gráfico de peso neto recibido en romana" role="img"></canvas></div>
                        <div class="chart-summary" id="weighbridgeChartSummary"></div>
                    </article>
                </div>

                <div
                    class="management-view management-view--domain-pair"
                    id="management-panel-raw-material"
                    data-office-panel-group="management"
                    data-office-panel-id="raw-material"
                    role="tabpanel"
                    aria-labelledby="management-tab-raw-material"
                >
                    <article class="management-panel">
                        <header>
                            <div><p class="eyebrow">MATERIA PRIMA</p><h2>Lotes y continuidad hacia proceso</h2></div>
                            <div class="management-panel-actions">
                                <a class="management-panel-link" href="/oficina/materia-prima">Abrir lotes →</a>
                                <a class="management-panel-link" href="/oficina/materia-prima/existencias">Existencia MP →</a>
                            </div>
                        </header>
                        <div class="management-operational-metrics">
                            <article><span>LOTES ACTIVOS</span><strong id="rawLotsActiveMetric">0</strong><small>en circuito</small></article>
                            <article class="is-warning"><span>ESPERA HIDROCOOLER</span><strong id="rawHydrocoolerPendingMetric">0</strong><small>pendientes</small></article>
                            <article><span>EN HIDROCOOLER</span><strong id="rawHydrocoolerActiveMetric">0</strong><small>proceso activo</small></article>
                            <article class="is-warning"><span>SIN CÁMARA</span><strong id="rawAssignmentPendingMetric">0</strong><small>esperan asignación</small></article>
                            <article><span>EN CÁMARA</span><strong id="rawInCameraMetric">0</strong><small>lotes ubicados</small></article>
                            <article><span>ENTREGA PARCIAL</span><strong id="rawPartialDeliveryMetric">0</strong><small>saldo por procesar</small></article>
                        </div>
                        <div class="management-average"><span>INGRESO CONFIRMADO HOY</span><strong id="rawConfirmedTodayMetric">0 lotes · 0 kg</strong></div>
                    </article>

                    <article class="management-panel">
                        <header>
                            <div><p class="eyebrow">CUENTA DE ENVASES</p><h2>Movimientos y revisión documental</h2></div>
                            <a class="management-panel-link" href="/oficina/envases/cuenta-corriente">Abrir cuenta →</a>
                        </header>
                        <div class="management-operational-metrics">
                            <article><span>MOVIMIENTOS HOY</span><strong id="containerMovementsTodayMetric">0</strong><small>registros</small></article>
                            <article><span>UNIDADES HOY</span><strong id="containerUnitsTodayMetric">0</strong><small>movidas</small></article>
                            <article class="is-warning"><span>PENDIENTES</span><strong id="containerPendingReviewMetric">0</strong><small>sin revisión</small></article>
                            <article class="is-critical"><span>OBSERVADOS</span><strong id="containerObservedMetric">0</strong><small>requieren gestión</small></article>
                        </div>
                    </article>
                </div>
            </section>
        </main>

        <div class="loading is-hidden" id="officeLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="officeLoadingText">Actualizando indicadores…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
