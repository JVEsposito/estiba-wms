<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#102f43">
        <meta name="color-scheme" content="light">
        <title>Estiba WMS · Operación ahora</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite([
                'resources/css/office.css',
                'resources/css/estiba-ui.css',
                'resources/css/office-operation-now.css',
                'resources/js/office-operation-now.js',
            ])
        @endif
    </head>
    <body>
        <main class="office-app operation-now-app is-hidden" id="operationNowApp">
            <x-office.navigation domain="administracion" office="operacion-ahora" context="CENTRO DE CONTROL" icon="OA" />

            <div class="operation-now estiba-ui" id="officeContent" data-density="compact">
                <header class="operation-now-command">
                    <div class="operation-now-command__title">
                        <p class="operation-now-eyebrow">CENTRO DE CONTROL · INFORMACIÓN EN VIVO</p>
                        <h1>Operación ahora</h1>
                        <p>Personal, infraestructura y excepciones vigentes de la planta.</p>
                    </div>
                    <div class="operation-now-command__date" aria-label="Fecha y hora operacional">
                        <span id="operationDate">—</span>
                        <strong id="operationTime">--:--:--</strong>
                        <small id="operationTimezone">Hora operacional</small>
                        <small id="operationShift">Turno sin configurar</small>
                    </div>
                    <x-estiba.button class="operation-now-refresh" id="operationRefresh" variant="secondary" icon="refresh">Actualizar</x-estiba.button>
                </header>

                <section class="operation-now-syncbar" aria-label="Estado y sincronización de la operación">
                    <div class="operation-now-syncbar__state">
                        <span>ESTADO DE LOS DATOS</span>
                        <strong class="operation-now-live" id="operationLiveSignal" data-tone="neutral">
                            <i aria-hidden="true"></i><span id="operationLiveText">Sin consultar</span>
                        </strong>
                        <small id="operationSeason">Temporada sin consultar</small>
                        <small id="operationUpdatedAt">Última lectura: —</small>
                    </div>
                    <div class="operation-now-syncbar__latest">
                        <span>ÚLTIMA OPERACIÓN RECIBIDA</span>
                        <small class="operation-now-command__latest" id="syncLatestOperation">Sin actividad registrada</small>
                        <small class="operation-now-command__latest-context" id="syncLatestContext">Esperando evidencia de dispositivos</small>
                    </div>
                    <dl class="operation-now-syncbar__metrics" aria-label="Sincronizaciones de hoy">
                        <div data-tone="success"><dt>Aceptadas</dt><dd id="syncAccepted">0</dd></div>
                        <div data-tone="warning"><dt>Pendientes</dt><dd id="syncPending">0</dd></div>
                        <div data-tone="info"><dt>Procesando</dt><dd id="syncProcessing">0</dd></div>
                        <div data-tone="critical"><dt>Rechazadas</dt><dd id="syncRejected">0</dd></div>
                        <div data-tone="critical"><dt>Conflictos</dt><dd id="syncConflict">0</dd></div>
                    </dl>
                </section>

                <div class="operation-now-connection" id="operationConnection" role="status" hidden>
                    <strong id="operationConnectionTitle">Información conservada</strong>
                    <span id="operationConnectionDetail">Se mantiene la última lectura disponible.</span>
                </div>

                <div class="operation-now-dashboard" aria-busy="true" id="operationWorkspace">
                    <section class="operation-now-panel operation-now-panel--operators" aria-labelledby="operationOperatorsTitle">
                        <header class="operation-now-panel__heading">
                            <div class="operation-now-panel__title">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <div><h2 id="operationOperatorsTitle">Camareros activos</h2><span>Ubicación y labor vigente</span></div>
                            </div>
                            <strong class="operation-now-panel__count" id="operatorPanelCount">—</strong>
                        </header>
                        <div class="operation-now-panel__body operation-now-operator-list" id="operationOperatorList"><div class="operation-now-empty">Consultando sesiones…</div></div>
                    </section>

                    <section class="operation-now-panel operation-now-panel--cameras" aria-labelledby="operationCamerasTitle">
                        <header class="operation-now-panel__heading">
                            <div class="operation-now-panel__title">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V8l8-4 8 4v12M8 20v-6h8v6M9 9h.01M15 9h.01"/></svg>
                                <div><h2 id="operationCamerasTitle">Cámaras</h2><span>Ocupación y estado ambiental</span></div>
                            </div>
                            <div class="operation-now-panel__tools"><strong class="operation-now-panel__summary" id="cameraPanelSummary">—</strong><a href="/oficina/frigorifico/camaras">Ver detalle <span aria-hidden="true">→</span></a></div>
                        </header>
                        <div class="operation-now-panel__body operation-now-table-scroll">
                            <table class="operation-now-table operation-now-table--cameras">
                                <caption class="office-visually-hidden">Ocupación y control ambiental de cámaras de producto terminado</caption>
                                <thead><tr><th scope="col">Cámara</th><th scope="col">Ocupación</th><th scope="col">T° actual</th><th scope="col">Control</th></tr></thead>
                                <tbody id="operationCameraRows"><tr><td colspan="4"><div class="operation-now-empty">Consultando cámaras…</div></td></tr></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="operation-now-panel operation-now-panel--precooling" aria-labelledby="operationPrecoolingTitle">
                        <header class="operation-now-panel__heading">
                            <div class="operation-now-panel__title">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2 1.5 5.5L18 4l-1.5 5.5L22 8l-4.5 3.5L22 14l-5.5-1.5L18 18l-4.5-3.5L12 22l-1.5-7.5L6 18l1.5-5.5L2 14l4.5-2.5L2 8l5.5 1.5L6 4l4.5 3.5L12 2Z"/></svg>
                                <div><h2 id="operationPrecoolingTitle">Prefrío</h2><span>Túneles y avance temporal</span></div>
                            </div>
                            <div class="operation-now-panel__tools"><strong class="operation-now-panel__summary" id="precoolingPanelSummary">—</strong><a href="/oficina/prefrio">Ver detalle <span aria-hidden="true">→</span></a></div>
                        </header>
                        <div class="operation-now-panel__body operation-now-tunnels" id="operationTunnelList"><div class="operation-now-empty">Consultando túneles…</div></div>
                    </section>

                    <section class="operation-now-panel operation-now-panel--incidents" aria-labelledby="operationIncidentsTitle">
                        <header class="operation-now-panel__heading">
                            <div class="operation-now-panel__title">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.7 2.6 17a2 2 0 0 0 1.73 3h15.34a2 2 0 0 0 1.73-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/></svg>
                                <div><h2 id="operationIncidentsTitle">Incidencias abiertas</h2><span>Condiciones que requieren atención</span></div>
                            </div>
                            <div class="operation-now-panel__tools"><strong class="operation-now-panel__summary" id="incidentPanelSummary">—</strong><a href="/oficina/frigorifico/discrepancias">Revisar <span aria-hidden="true">→</span></a></div>
                        </header>
                        <div class="operation-now-panel__body operation-now-table-scroll">
                            <table class="operation-now-table operation-now-table--incidents">
                                <caption class="office-visually-hidden">Incidencias y discrepancias operacionales abiertas</caption>
                                <thead><tr><th scope="col">Antigüedad</th><th scope="col">Origen</th><th scope="col">Prioridad</th><th scope="col">Folio / contexto</th><th scope="col">Reporte</th></tr></thead>
                                <tbody id="operationIncidentRows"><tr><td colspan="5"><div class="operation-now-empty">Consultando incidencias…</div></td></tr></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="operation-now-panel operation-now-panel--facility" aria-labelledby="operationFacilityTitle">
                        <header class="operation-now-panel__heading">
                            <div class="operation-now-panel__title">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-5h6v5"/></svg>
                                <div><h2 id="operationFacilityTitle">Vista operacional de recintos</h2><span>Estado de cámaras y túneles; no es un plano físico</span></div>
                            </div>
                        </header>
                        <div class="operation-now-panel__body operation-now-facility" id="operationFacilityMap"><div class="operation-now-empty">Preparando vista operacional…</div></div>
                    </section>

                    <section class="operation-now-panel operation-now-panel--alerts" id="operationAlertsPanel" data-tone="neutral" aria-labelledby="operationAlertsTitle">
                        <header class="operation-now-panel__heading">
                            <div class="operation-now-panel__title">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.7 2.6 17a2 2 0 0 0 1.73 3h15.34a2 2 0 0 0 1.73-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/></svg>
                                <div><h2 id="operationAlertsTitle">Alertas operacionales</h2><span>Condiciones verificables que requieren atención</span></div>
                            </div>
                            <strong class="operation-now-panel__count" id="operationAlertCount">—</strong>
                        </header>
                        <div class="operation-now-panel__body operation-now-alert-list" id="operationAlertRows"><div class="operation-now-empty">Evaluando alertas…</div></div>
                    </section>

                    <nav class="operation-now-shortcuts" aria-label="Accesos rápidos">
                        <strong>ACCESOS RÁPIDOS</strong>
                        <a href="/oficina/frigorifico/camaras"><span>Cámaras</span><small>Ocupación y posiciones</small><b aria-hidden="true">→</b></a>
                        <a href="/oficina/prefrio"><span>Prefrío</span><small>Procesos y túneles</small><b aria-hidden="true">→</b></a>
                        <a href="/oficina/cargas"><span>Cargas</span><small>Despachos vigentes</small><b aria-hidden="true">→</b></a>
                        <a href="/oficina/frigorifico/discrepancias"><span>Incidencias</span><small>Supervisión operativa</small><b aria-hidden="true">→</b></a>
                    </nav>
                </div>

                <footer class="operation-now-footer">
                    <span>Los avances de prefrío representan tiempo transcurrido, no progreso térmico.</span>
                    <span>El esquema de recintos no representa coordenadas ni posiciones físicas.</span>
                </footer>
            </div>

            <div class="operation-now-loading is-hidden" id="operationLoading" aria-hidden="true"><span aria-hidden="true"></span><strong id="operationLoadingText">Consultando la operación…</strong></div>
            <div class="toast-region" id="operationToasts" aria-live="polite"></div>
        </main>
    </body>
</html>
