<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">
        <title>Estiba WMS · Prefrío</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-prefrio.css', 'resources/js/office-prefrio.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand prefrio-access-brand">
                <div class="office-logo" aria-hidden="true">❄</div>
                <p class="eyebrow">ESTIBA WMS · PREFRÍO</p>
                <h1 id="officeAccessTitle">Controla cada ciclo térmico sin perder el historial del pallet.</h1>
                <p>Supervisa túneles, procesos activos, verificaciones y reprocesos desde una vista operacional única.</p>
                <div class="feature-row"><span>Túneles configurables</span><span>Procesos históricos</span><span>Decisiones auditadas</span></div>
            </div>
            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div><p class="eyebrow">ACCESO DE OFICINA</p><h2>Ingresar a Prefrío</h2><p>Disponible para administración, supervisión de frío y consulta.</p></div>
                <label><span>Correo electrónico</span><input name="email" type="email" autocomplete="username" required></label>
                <label><span>Contraseña</span><input name="password" type="password" autocomplete="current-password" required></label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Entrar a Prefrío <span>→</span></button>
            </form>
        </section>

        <main class="office-app is-hidden" id="officeApp">
            <header class="office-topbar">
                <div class="brand-lockup"><span class="office-logo office-logo--small" aria-hidden="true">❄</span><span><strong>ESTIBA WMS</strong><small>PREFRÍO</small></span></div>
                <nav aria-label="Módulos de oficina">
                    <a id="officeCamerasNav" href="/oficina/camaras">Cámaras</a>
                    <a id="officeLoadsNav" href="/oficina/cargas">Cargas</a>
                    <a id="officeMaterialsNav" href="/oficina/materiales">Materiales</a>
                    <a id="officeValidationNav" href="/oficina/validacion">Validación</a>
                    <a class="is-active" href="/oficina/prefrio">Prefrío</a>
                    <a class="is-hidden" id="officeAccessesNav" href="/oficina/accesos">Accesos</a>
                </nav>
                <div class="identity"><span class="identity__avatar" id="officeInitials">PF</span><span><strong id="officeUserName">Usuario</strong><small id="officeUserRole">Oficina</small></span><button id="officeLogoutButton" type="button">Cerrar sesión</button></div>
            </header>

            <section class="prefrio-workspace">
                <header class="prefrio-heading panel">
                    <div><p class="eyebrow">CONTROL TÉRMICO</p><h1>Tablero de Prefrío</h1><p>La aprobación habilita almacenamiento; el folio solo queda disponible después de ser ubicado en cámara.</p></div>
                    <div class="prefrio-heading__actions">
                        <button class="secondary-button" id="newProcessButton" type="button">+ Nuevo proceso</button>
                        <button class="secondary-button" id="newTunnelButton" type="button">+ Nuevo túnel</button>
                        <button class="secondary-button" id="reloadPrefrioButton" type="button">↻ Actualizar</button>
                    </div>
                </header>

                <div class="prefrio-metrics">
                    <article><span>TÚNELES ACTIVOS</span><strong id="activeTunnelCount">0</strong></article>
                    <article><span>EN PROCESO</span><strong id="runningProcessCount">0</strong></article>
                    <article><span>PENDIENTES DE VERIFICACIÓN</span><strong id="pendingVerificationCount">0</strong></article>
                    <article><span>REPROCESOS RECIENTES</span><strong id="reprocessCount">0</strong></article>
                    <article><span>PALLETS EN CICLOS ACTIVOS</span><strong id="activeFolioCount">0</strong></article>
                </div>

                <div class="prefrio-grid">
                    <aside class="panel tunnel-panel">
                        <div class="prefrio-panel__heading"><div><p class="eyebrow">INFRAESTRUCTURA</p><h2>Túneles</h2></div><span id="tunnelSummary">0 configurados</span></div>
                        <div class="tunnel-list" id="tunnelList"></div>
                    </aside>

                    <section class="panel process-panel">
                        <div class="prefrio-panel__heading process-heading">
                            <div><p class="eyebrow">OPERACIÓN</p><h2>Procesos</h2></div>
                            <form class="process-filters" id="processFilters">
                                <select name="tunel_prefrio_id"><option value="">Todos los túneles</option></select>
                                <select name="estado"><option value="">Todos los estados</option><option value="borrador">Borrador</option><option value="cargando">Cargando</option><option value="listo_para_iniciar">Listo para iniciar</option><option value="en_proceso">En proceso</option><option value="pendiente_verificacion">Pendiente de verificación</option><option value="aprobado">Aprobado</option><option value="requiere_reproceso">Requiere reproceso</option><option value="cancelado">Cancelado</option></select>
                                <input name="folio" maxlength="50" placeholder="Buscar folio">
                                <button class="secondary-button" type="submit">Filtrar</button>
                            </form>
                        </div>
                        <div class="process-table-scroll"><table class="process-table"><thead><tr><th>Proceso</th><th>Túnel</th><th>Estado</th><th>Folios</th><th>Inicio</th><th>Resultado</th></tr></thead><tbody id="processTableBody"></tbody></table></div>
                    </section>
                </div>

                <section class="panel process-detail is-hidden" id="processDetail">
                    <div class="process-detail__heading">
                        <div><p class="eyebrow">DETALLE DEL CICLO</p><h2 id="processDetailTitle">Proceso</h2><p id="processDetailSubtitle"></p></div>
                        <div class="process-detail__actions"><button class="secondary-button" id="refreshProcessButton" type="button">↻ Actualizar</button><button class="secondary-button" id="closeProcessDetailButton" type="button">Cerrar</button></div>
                    </div>
                    <div class="process-detail__metrics" id="processDetailMetrics"></div>
                    <div class="process-detail__layout">
                        <div>
                            <div class="prefrio-panel__heading"><div><p class="eyebrow">DISTRIBUCIÓN</p><h3>Posiciones del túnel</h3></div></div>
                            <div class="tunnel-direction"><strong>FONDO</strong><span>Dos lados por profundidad</span></div>
                            <div class="tunnel-map" id="processTunnelMap"></div>
                            <div class="tunnel-direction tunnel-direction--entrance"><span>Recorrido operacional</span><strong>ENTRADA</strong></div>
                        </div>
                        <div>
                            <div class="prefrio-panel__heading"><div><p class="eyebrow">EVENTOS</p><h3>Línea de tiempo</h3></div></div>
                            <div class="event-timeline" id="processTimeline"></div>
                        </div>
                    </div>
                    <section class="decision-panel is-hidden" id="decisionPanel">
                        <div class="prefrio-panel__heading"><div><p class="eyebrow">VERIFICACIÓN FINAL</p><h3>Resultado por folio</h3></div><span>Supervisor de frío o administrador</span></div>
                        <div class="decision-folios" id="decisionFolios"></div>
                        <div class="decision-actions">
                            <button class="primary-button" id="approveProcessButton" type="button">Aprobar proceso</button>
                            <button class="secondary-button" id="reprocessProcessButton" type="button">Enviar a reproceso</button>
                            <button class="danger-button" id="cancelProcessButton" type="button">Cancelar proceso</button>
                        </div>
                        <p class="form-error" id="decisionError" role="alert"></p>
                    </section>
                </section>
            </section>
        </main>

        <dialog class="prefrio-dialog" id="tunnelDialog">
            <form method="dialog" class="prefrio-dialog__shell" id="tunnelForm">
                <div class="prefrio-dialog__heading"><div><p class="eyebrow">CONFIGURACIÓN</p><h2 id="tunnelDialogTitle">Nuevo túnel</h2><p>Define capacidad y estado técnico. Las posiciones se generan automáticamente.</p></div><button class="dialog-close" value="cancel" type="submit" aria-label="Cerrar">×</button></div>
                <input name="id" type="hidden">
                <div class="prefrio-form-grid">
                    <label><span>Nombre *</span><input name="nombre" maxlength="150" required></label>
                    <label><span>Capacidad *</span><input name="capacidad_posiciones" type="number" min="2" max="100" step="2" value="22" required><small>Dos lados por profundidad, desde el fondo hacia la entrada.</small></label>
                    <label><span>Setpoint habitual</span><input name="setpoint_habitual" type="number" min="-20" max="20" step="0.1" value="-1.5"></label>
                    <label><span>Estado administrativo</span><select name="estado_administrativo"><option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></label>
                    <label><span>Estado técnico</span><select name="estado_tecnico"><option value="operativo">Operativo</option><option value="mantenimiento">Mantenimiento</option><option value="fuera_de_servicio">Fuera de servicio</option></select></label>
                    <label><span>Código externo</span><input name="codigo_externo" maxlength="100"></label>
                    <label class="prefrio-field-wide"><span>Observación</span><textarea name="observacion" maxlength="2000"></textarea></label>
                </div>
                <div class="tunnel-preview-heading"><span>VISTA PREVIA</span><strong id="tunnelPreviewSummary">22 posiciones</strong></div>
                <div class="tunnel-direction"><strong>FONDO</strong><span>Lados A / B</span></div>
                <div class="tunnel-preview" id="tunnelPreview"></div>
                <div class="tunnel-direction tunnel-direction--entrance"><span>Última profundidad</span><strong>ENTRADA</strong></div>
                <p class="form-error" id="tunnelFormError" role="alert"></p>
                <div class="dialog-actions"><button class="secondary-button" value="cancel" type="submit">Cancelar</button><button class="primary-button" id="saveTunnelButton" value="default" type="submit">Guardar túnel</button></div>
            </form>
        </dialog>

        <dialog class="prefrio-dialog" id="processDialog">
            <form method="dialog" class="prefrio-dialog__shell" id="processForm">
                <div class="prefrio-dialog__heading"><div><p class="eyebrow">PLANIFICACIÓN</p><h2>Crear proceso</h2><p>Abre un ciclo vacío para que el operador cargue los pallets en terreno.</p></div><button class="dialog-close" value="cancel" type="submit" aria-label="Cerrar">×</button></div>
                <div class="prefrio-form-grid">
                    <label><span>Túnel *</span><select name="tunel_prefrio_id" required></select></label>
                    <label><span>Setpoint *</span><input name="setpoint" type="number" min="-20" max="20" step="0.1" value="-1.5" required></label>
                    <label><span>Duración objetivo (min)</span><input name="duracion_objetivo_minutos" type="number" min="1" max="4320" value="720"></label>
                    <label><span>Formato de referencia</span><input name="formato_referencia" maxlength="100" placeholder="Granel 5 kg"></label>
                    <label class="prefrio-field-wide"><span>Observación</span><textarea name="observacion" maxlength="2000"></textarea></label>
                </div>
                <p class="form-error" id="processFormError" role="alert"></p>
                <div class="dialog-actions"><button class="secondary-button" value="cancel" type="submit">Cancelar</button><button class="primary-button" value="default" type="submit">Crear proceso</button></div>
            </form>
        </dialog>

        <dialog class="prefrio-dialog prefrio-dialog--compact" id="reasonDialog">
            <form method="dialog" class="prefrio-dialog__shell" id="reasonForm">
                <div class="prefrio-dialog__heading"><div><p class="eyebrow" id="reasonEyebrow">DECISIÓN</p><h2 id="reasonTitle">Registrar motivo</h2><p id="reasonDescription"></p></div><button class="dialog-close" value="cancel" type="submit" aria-label="Cerrar">×</button></div>
                <label><span>Motivo *</span><input name="motivo" maxlength="100" required></label>
                <label><span>Observación</span><textarea name="observacion" maxlength="2000"></textarea></label>
                <p class="form-error" id="reasonError" role="alert"></p>
                <div class="dialog-actions"><button class="secondary-button" value="cancel" type="submit">Cancelar</button><button class="danger-button" id="confirmReasonButton" value="default" type="submit">Confirmar</button></div>
            </form>
        </dialog>

        <div class="loading is-hidden" id="officeLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="officeLoadingText">Procesando…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
