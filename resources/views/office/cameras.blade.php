<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">

        <title>Estiba WMS · Cámaras</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/js/office-cameras.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand">
                <div class="office-logo" aria-hidden="true">❄</div>
                <p class="eyebrow">ESTIBA WMS · OFICINA</p>
                <h1 id="officeAccessTitle">Consulta la disponibilidad antes de preparar una carga.</h1>
                <p>Despachadores pueden revisar ocupación y disponibilidad. Supervisores y administradores conservan sus herramientas de configuración.</p>
                <div class="feature-row">
                    <span>Bandas verticales</span>
                    <span>Trazabilidad</span>
                    <span>Sin código de tablet</span>
                </div>
            </div>

            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div>
                    <p class="eyebrow">ACCESO DE OFICINA</p>
                    <h2>Ingresar a cámaras</h2>
                    <p>Disponible para despachadores, supervisores y administradores.</p>
                </div>
                <label>
                    <span>Correo electrónico</span>
                    <input name="email" type="email" autocomplete="username" placeholder="supervisor@empresa.cl" required>
                </label>
                <label>
                    <span>Contraseña</span>
                    <input name="password" type="password" autocomplete="current-password" placeholder="••••••••" required>
                </label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Entrar a oficina <span>→</span></button>
            </form>
        </section>

        <main class="office-app is-hidden" id="officeApp">
            <header class="office-topbar">
                <div class="brand-lockup">
                    <span class="office-logo office-logo--small" aria-hidden="true">❄</span>
                    <span><strong>ESTIBA WMS</strong><small>OPERACIÓN DE OFICINA</small></span>
                </div>
                <nav aria-label="Módulos de oficina">
                    <a class="is-active" href="/oficina/camaras">Cámaras</a>
                    <a id="officeLoadsNav" href="/oficina/cargas">Cargas</a>
                    <a id="officeMaterialsNav" href="/oficina/materiales">Materiales</a>
                    <a href="/oficina/validacion">Validación</a>
                    <a class="is-hidden" id="officeAccessesNav" href="/oficina/accesos">Accesos</a>
                </nav>
                <div class="identity">
                    <span class="identity__avatar" id="officeInitials">SP</span>
                    <span><strong id="officeUserName">Supervisor</strong><small id="officeUserRole">Supervisor</small></span>
                    <button id="officeLogoutButton" type="button">Cerrar sesión</button>
                </div>
            </header>

            <div class="configuration-module-tabs is-hidden" id="configurationModuleTabs" role="tablist" aria-label="Configuración de infraestructura">
                <button class="is-active" id="cameraModuleButton" type="button" role="tab" aria-selected="true" aria-controls="officeWorkspace">Cámaras</button>
                <button id="dockModuleButton" type="button" role="tab" aria-selected="false" aria-controls="dockWorkspace">Andenes</button>
            </div>

            <section class="office-workspace" id="officeWorkspace" role="tabpanel" aria-labelledby="cameraModuleButton">
                <aside class="camera-catalog panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow" id="cameraCatalogEyebrow">CONFIGURACIÓN</p><h2 id="cameraCatalogTitle">Cámaras creadas</h2></div>
                        <button class="icon-button" id="reloadOfficeButton" type="button" aria-label="Actualizar">↻</button>
                    </div>
                    <div class="camera-catalog__list" id="officeCameraList" aria-live="polite"></div>
                </aside>

                <section class="configuration panel">
                    <div class="configuration__heading">
                        <div>
                            <p class="eyebrow" id="configurationEyebrow">NUEVO PLANO</p>
                            <h1 id="configurationTitle">Crear cámara</h1>
                            <p id="configurationDescription">Define la estructura y revisa cada banda antes de confirmar.</p>
                        </div>
                        <div class="next-code"><span id="cameraCodeLabel">PRÓXIMO CÓDIGO</span><strong id="nextCameraCode">CAM-—</strong></div>
                    </div>

                    <form id="createCameraForm" novalidate>
                        <div class="form-grid">
                            <label class="field field--wide"><span>Nombre de la cámara *</span><input name="nombre" maxlength="150" placeholder="Ej. Cámara de tránsito norte" required></label>
                            <label class="field"><span>Tipo *</span><select name="tipo" required><option value="transito">Tránsito</option><option value="almacenaje">Almacenaje</option><option value="preparacion">Preparación</option><option value="despacho">Despacho</option></select></label>
                            <label class="field"><span>Contenido permitido *</span><select name="contenido" required><option value="productos">Productos / fruta</option><option value="materiales">Materiales</option></select></label>
                            <label class="field"><span>Bandas *</span><input name="bandas" type="number" min="1" max="40" value="3" required></label>
                            <label class="field"><span>Posiciones por banda *</span><input name="posiciones_por_banda" type="number" min="1" max="40" value="4" required></label>
                            <label class="field"><span>Niveles *</span><input name="niveles" type="number" min="1" max="10" value="2" required></label>
                        </div>

                        <div class="capacity-summary">
                            <span><strong id="previewCapacity">24</strong> posiciones totales</span>
                            <span><strong id="previewActive">24</strong> operativas</span>
                            <span><strong id="previewDisabled">0</strong> fuera de servicio</span>
                        </div>

                        <div class="preview-heading">
                            <div><p class="eyebrow">VISTA PREVIA</p><h2>Bandas verticales</h2></div>
                            <div class="level-tabs" id="previewLevelTabs"></div>
                        </div>
                        <div class="orientation"><strong>↑ FONDO</strong><span>Haz clic sobre una posición para marcarla fuera de servicio.</span></div>
                        <div class="camera-preview" id="cameraPreview"></div>
                        <div class="orientation orientation--entrance"><strong>↓ ENTRADA</strong><span>La posición P01 se ocupa primero.</span></div>

                        <p class="form-error" id="createCameraError" role="alert"></p>
                        <div class="form-actions">
                            <button class="danger-button is-hidden" id="deactivateCameraButton" type="button">Desactivar cámara</button>
                            <button class="secondary-button is-hidden" id="cancelEditCameraButton" type="button">Cancelar edición</button>
                            <button class="secondary-button" id="resetCameraButton" type="button">Restablecer plano</button>
                            <button class="primary-button" id="saveCameraButton" type="submit"><span id="saveCameraButtonText">Crear cámara y posiciones</span> <span>→</span></button>
                        </div>
                    </form>
                </section>
            </section>

            <section class="office-workspace is-hidden" id="dockWorkspace" role="tabpanel" aria-labelledby="dockModuleButton">
                <aside class="camera-catalog panel">
                    <div class="panel-heading">
                        <div><p class="eyebrow">DESPACHO</p><h2>Andenes creados</h2></div>
                        <button class="icon-button" id="reloadDocksButton" type="button" aria-label="Actualizar andenes">↻</button>
                    </div>
                    <div class="camera-catalog__list" id="officeDockList" aria-live="polite"></div>
                </aside>

                <section class="configuration panel" id="dockConfiguration">
                    <div class="configuration__heading">
                        <div>
                            <p class="eyebrow" id="dockFormEyebrow">NUEVO ANDÉN</p>
                            <h1 id="dockFormTitle">Crear andén</h1>
                            <p id="dockFormDescription">Registra los puntos físicos donde se concentran y despachan las cargas.</p>
                        </div>
                        <div class="next-code"><span>CÓDIGO SUGERIDO</span><strong id="nextDockCode">AND-01</strong></div>
                    </div>

                    <form id="dockForm" novalidate>
                        <div class="dock-form-grid">
                            <label class="field"><span>Código *</span><input name="codigo" maxlength="30" placeholder="AND-01" pattern="[A-Za-z0-9-]+" required></label>
                            <label class="field"><span>Nombre del andén *</span><input name="nombre" maxlength="100" placeholder="Ej. Andén principal" required></label>
                            <label class="field"><span>Código externo</span><input name="codigo_externo" maxlength="100" placeholder="Opcional: ERP-AND-01"></label>
                        </div>

                        <label class="dock-status-toggle">
                            <input name="activo" type="checkbox" checked>
                            <span><strong>Andén activo</strong><small>Los andenes inactivos conservan su historial, pero no aparecen al preparar o despachar cargas.</small></span>
                        </label>

                        <p class="form-error" id="dockFormError" role="alert"></p>
                        <div class="form-actions">
                            <button class="secondary-button is-hidden" id="cancelEditDockButton" type="button">Cancelar edición</button>
                            <button class="primary-button" id="saveDockButton" type="submit"><span id="saveDockButtonText">Crear andén</span> <span>→</span></button>
                        </div>
                    </form>
                </section>
            </section>
        </main>

        <div class="loading is-hidden" id="officeLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="officeLoadingText">Procesando…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
