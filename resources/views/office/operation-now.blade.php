<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#183442">
        <meta name="color-scheme" content="light dark">

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
            <x-office.navigation
                domain="administracion"
                office="operacion-ahora"
                context="GERENCIA · OPERACIÓN"
                icon="OA"
            />

            <div class="operation-now estiba-ui" id="officeContent" data-density="comfortable">
                <header class="operation-now-heading">
                    <div class="operation-now-heading__title">
                        <p class="operation-now-eyebrow">CENTRO DE CONTROL · SOLO LECTURA</p>
                        <h1>Operación ahora</h1>
                        <p>Estado físico y actividad vigente de la temporada activa.</p>
                    </div>
                    <div class="operation-now-heading__clock" aria-label="Jornada operacional">
                        <span id="operationDate">—</span>
                        <strong id="operationTime">--:--:--</strong>
                        <small id="operationTimezone">Hora operacional</small>
                    </div>
                    <div class="operation-now-heading__status">
                        <span class="operation-now-live" id="operationLiveSignal" data-tone="neutral">
                            <i aria-hidden="true"></i><strong id="operationLiveText">Sin consultar</strong>
                        </span>
                        <span id="operationSeason">Temporada sin consultar</span>
                        <small id="operationUpdatedAt">Última lectura: —</small>
                    </div>
                    <button class="eui-button eui-button--secondary operation-now-refresh" id="operationRefresh" type="button">
                        Actualizar ahora
                    </button>
                </header>

                <div class="operation-now-connection" id="operationConnection" role="status" hidden>
                    <strong id="operationConnectionTitle">Información conservada</strong>
                    <span id="operationConnectionDetail">Se mantiene la última lectura disponible.</span>
                </div>

                <section class="operation-now-metrics" aria-label="Situación general">
                    <article>
                        <span>CÁMARAS ACTIVAS</span>
                        <strong id="metricCameras">—</strong>
                        <small id="metricCamerasDetail">sin consultar</small>
                    </article>
                    <article>
                        <span>OCUPACIÓN PT</span>
                        <strong id="metricOccupancy">—</strong>
                        <small id="metricOccupancyDetail">capacidad operativa</small>
                    </article>
                    <article data-metric-tone="warning">
                        <span>CONTROL AMBIENTAL</span>
                        <strong id="metricEnvironmental">—</strong>
                        <small id="metricEnvironmentalDetail">pendientes o vencidos</small>
                    </article>
                    <article>
                        <span>CAMAREROS ACTIVOS</span>
                        <strong id="metricOperators">—</strong>
                        <small id="metricOperatorsDetail">sesiones abiertas</small>
                    </article>
                    <article>
                        <span>PREFRÍO ACTIVO</span>
                        <strong id="metricPrecooling">—</strong>
                        <small id="metricPrecoolingDetail">procesos en curso</small>
                    </article>
                    <article data-metric-tone="critical">
                        <span>INCIDENCIAS ABIERTAS</span>
                        <strong id="metricIncidents">—</strong>
                        <small id="metricIncidentsDetail">requieren revisión</small>
                    </article>
                </section>

                <section class="operation-now-sync" aria-label="Estado de sincronización">
                    <div>
                        <span>ÚLTIMA OPERACIÓN RECIBIDA</span>
                        <strong id="syncLatestOperation">Sin actividad registrada</strong>
                        <small id="syncLatestContext">Esperando evidencia de dispositivos</small>
                    </div>
                    <dl>
                        <div><dt>Aceptadas hoy</dt><dd id="syncAccepted">0</dd></div>
                        <div><dt>Pendientes</dt><dd id="syncPending">0</dd></div>
                        <div><dt>Procesando</dt><dd id="syncProcessing">0</dd></div>
                        <div><dt>Rechazadas</dt><dd id="syncRejected">0</dd></div>
                        <div><dt>Con conflicto</dt><dd id="syncConflict">0</dd></div>
                    </dl>
                </section>

                <div class="operation-now-grid" aria-busy="true" id="operationWorkspace">
                    <section class="operation-now-panel operation-now-panel--cameras" aria-labelledby="operationCamerasTitle">
                        <header class="operation-now-panel__heading">
                            <div>
                                <p class="operation-now-eyebrow">INFRAESTRUCTURA PT</p>
                                <h2 id="operationCamerasTitle">Cámaras y ambiente</h2>
                                <p>Capacidad física y vigencia del último control horario.</p>
                            </div>
                            <a href="/oficina/frigorifico/camaras">Abrir cámaras</a>
                        </header>
                        <div class="operation-now-table-scroll">
                            <table class="operation-now-table operation-now-table--cameras">
                                <thead>
                                    <tr>
                                        <th scope="col">Cámara</th>
                                        <th scope="col">Área</th>
                                        <th scope="col">Ocupación</th>
                                        <th scope="col">Ambiente</th>
                                        <th scope="col">Última lectura</th>
                                    </tr>
                                </thead>
                                <tbody id="operationCameraRows">
                                    <tr><td colspan="5"><div class="operation-now-empty">Consultando cámaras…</div></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="operation-now-panel operation-now-panel--operators" aria-labelledby="operationOperatorsTitle">
                        <header class="operation-now-panel__heading">
                            <div>
                                <p class="operation-now-eyebrow">PERSONAS EN TERRENO</p>
                                <h2 id="operationOperatorsTitle">Camareros activos</h2>
                                <p>Sesión, ubicación y tarea tomada.</p>
                            </div>
                        </header>
                        <div class="operation-now-operator-list" id="operationOperatorList">
                            <div class="operation-now-empty">Consultando sesiones…</div>
                        </div>
                    </section>

                    <section class="operation-now-panel operation-now-panel--precooling" aria-labelledby="operationPrecoolingTitle">
                        <header class="operation-now-panel__heading">
                            <div>
                                <p class="operation-now-eyebrow">PREFRÍO</p>
                                <h2 id="operationPrecoolingTitle">Operación de túneles</h2>
                                <p>Estado administrativo, capacidad y avance contra el tiempo objetivo.</p>
                            </div>
                            <a href="/oficina/prefrio">Abrir prefrío</a>
                        </header>
                        <div class="operation-now-tunnels" id="operationTunnelList">
                            <div class="operation-now-empty">Consultando túneles…</div>
                        </div>
                    </section>

                    <section class="operation-now-panel operation-now-panel--incidents" aria-labelledby="operationIncidentsTitle">
                        <header class="operation-now-panel__heading">
                            <div>
                                <p class="operation-now-eyebrow">EXCEPCIONES ABIERTAS</p>
                                <h2 id="operationIncidentsTitle">Incidencias y discrepancias</h2>
                                <p>Reportes vigentes de la temporada, sin severidad inventada.</p>
                            </div>
                            <a href="/oficina/frigorifico/discrepancias">Revisar discrepancias</a>
                        </header>
                        <div class="operation-now-table-scroll">
                            <table class="operation-now-table operation-now-table--incidents">
                                <thead>
                                    <tr>
                                        <th scope="col">Antigüedad</th>
                                        <th scope="col">Origen</th>
                                        <th scope="col">Prioridad</th>
                                        <th scope="col">Folio / contexto</th>
                                        <th scope="col">Reporte</th>
                                    </tr>
                                </thead>
                                <tbody id="operationIncidentRows">
                                    <tr><td colspan="5"><div class="operation-now-empty">Consultando incidencias…</div></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <footer class="operation-now-footer">
                    <span>Los avances de prefrío representan tiempo transcurrido, no progreso térmico.</span>
                    <span>Actualización automática según el intervalo informado por el servidor.</span>
                </footer>
            </div>

            <div class="operation-now-loading is-hidden" id="operationLoading" aria-hidden="true">
                <span aria-hidden="true"></span><strong id="operationLoadingText">Consultando la operación…</strong>
            </div>
            <div class="toast-region" id="operationToasts" aria-live="polite"></div>
        </main>
    </body>
</html>
