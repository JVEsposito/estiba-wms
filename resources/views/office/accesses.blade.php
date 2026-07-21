<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">

        <title>Estiba WMS · Administración de accesos</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-admin.css', 'resources/js/office-admin.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand admin-access-brand">
                <div class="office-logo" aria-hidden="true">⚿</div>
                <p class="eyebrow">ESTIBA WMS · ADMINISTRACIÓN</p>
                <h1 id="officeAccessTitle">Administra los accesos y la configuración transversal de la operación.</h1>
                <p>Los nuevos accesos quedan activos inmediatamente y la temporada seleccionada se aplica a todas las oficinas. Solo un administrador puede utilizar este módulo.</p>
                <div class="feature-row">
                    <span>Contraseñas cifradas</span>
                    <span>Roles operacionales</span>
                    <span>Tablets identificadas</span>
                    <span>Temporada global</span>
                </div>
            </div>

            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div>
                    <p class="eyebrow">ACCESO ADMINISTRATIVO</p>
                    <h2>Ingresar a accesos</h2>
                    <p>Utiliza una cuenta con rol administrador.</p>
                </div>
                <label>
                    <span>Correo electrónico</span>
                    <input name="email" type="email" autocomplete="username" placeholder="administrador@empresa.cl" required>
                </label>
                <label>
                    <span>Contraseña</span>
                    <input name="password" type="password" autocomplete="current-password" placeholder="••••••••" required>
                </label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Entrar a administración <span>→</span></button>
            </form>
        </section>

        <main class="office-app is-hidden" id="officeApp">
            <header class="office-topbar">
                <div class="brand-lockup">
                    <span class="office-logo office-logo--small" aria-hidden="true">❄</span>
                    <span><strong>ESTIBA WMS</strong><small>ADMINISTRACIÓN</small></span>
                </div>
                <nav aria-label="Módulos de oficina">
                    <a href="/oficina/gerencia">Gerencia</a>
                    <a href="/oficina/romana">Romana</a>
                    <a href="/oficina/camaras">Cámaras</a>
                    <a href="/oficina/cargas">Cargas</a>
                    <a href="/oficina/materiales">Materiales</a>
                    <a href="/oficina/validacion">Validación</a>
                    <a href="/oficina/prefrio">Prefrío</a>
                    <a class="is-active" href="/oficina/accesos">Accesos</a>
                </nav>
                <div class="identity">
                    <span class="identity__avatar" id="officeInitials">AD</span>
                    <span><strong id="officeUserName">Administrador</strong><small id="officeUserRole">Administrador</small></span>
                    <button id="officeLogoutButton" type="button">Cerrar sesión</button>
                </div>
            </header>

            <section class="admin-workspace">
                <header class="admin-heading">
                    <div>
                        <p class="eyebrow">CONFIGURACIÓN TRANSVERSAL</p>
                        <h1>Accesos y temporada operacional</h1>
                        <p>Crea credenciales, registra tablets y define la única temporada que consumen Romana, Validación, Materiales y Frigorífico.</p>
                    </div>
                    <button class="secondary-button admin-reload" id="reloadAccessesButton" type="button">↻ Actualizar listados</button>
                </header>

                <div class="admin-metrics">
                    <article><span>USUARIOS ACTIVOS</span><strong id="activeUsersCount">0</strong></article>
                    <article><span>TABLETS ACTIVAS</span><strong id="activeDevicesCount">0</strong></article>
                    <article><span>TEMPORADA ACTIVA</span><strong id="activeSeasonCode">—</strong></article>
                    <article><span>ÚLTIMO ACCESO TABLET</span><strong id="lastDeviceAccess">Sin accesos</strong></article>
                </div>

                <section class="admin-panel admin-season-panel panel" aria-labelledby="seasonsTitle">
                    <div class="admin-panel__heading">
                        <div><p class="eyebrow">TEMPORADA GLOBAL</p><h2 id="seasonsTitle">Ciclo operacional compartido</h2></div>
                        <span id="seasonsSummary">0 registradas</span>
                    </div>

                    <form class="admin-form" id="seasonForm" novalidate>
                        <input name="id" type="hidden">
                        <div class="admin-form__grid admin-form__grid--season">
                            <label class="field"><span>Código *</span><input name="codigo" maxlength="30" placeholder="2026-2027" required></label>
                            <label class="field"><span>Nombre *</span><input name="nombre" maxlength="100" placeholder="Temporada cerezas 2026–2027" required></label>
                            <label class="field"><span>Inicio</span><input name="fecha_inicio" type="date"></label>
                            <label class="field"><span>Término</span><input name="fecha_fin" type="date"></label>
                        </div>
                        <label class="admin-check"><input name="activa" type="checkbox"><span>Dejar como temporada activa para todas las oficinas</span></label>
                        <p class="admin-form__hint">La activación es global. Las oficinas operacionales solo consultan esta configuración y mantienen sus flujos separados.</p>
                        <p class="form-error" id="seasonError" role="alert"></p>
                        <div class="admin-form__actions">
                            <button class="secondary-button is-hidden" id="cancelSeasonEdit" type="button">Nueva temporada</button>
                            <button class="primary-button" type="submit">Guardar temporada <span>→</span></button>
                        </div>
                    </form>

                    <div class="admin-table-scroll admin-season-list">
                        <table class="admin-table">
                            <thead><tr><th>Temporada</th><th>Vigencia</th><th>Estado</th><th>Acciones</th></tr></thead>
                            <tbody id="seasonsTableBody"></tbody>
                        </table>
                    </div>

                    <form class="admin-form admin-migration-form is-hidden" id="seasonMigrationForm" novalidate>
                        <input name="temporada_destino_id" type="hidden">
                        <div class="admin-panel__heading admin-migration-heading">
                            <div><p class="eyebrow">MIGRACIÓN CONTROLADA</p><h3 id="seasonMigrationTitle">Preparar nueva temporada</h3></div>
                            <button class="secondary-button" id="cancelSeasonMigration" type="button">Cerrar</button>
                        </div>
                        <div class="admin-form__grid admin-form__grid--migration">
                            <label class="field field--wide"><span>Temporada de origen *</span><select name="temporada_origen_id" required></select></label>
                            <label class="admin-check"><input name="copiar_catalogo_validacion" type="checkbox" checked><span>Copiar catálogos de Validación</span></label>
                            <label class="admin-check"><input name="copiar_catalogo_materiales" type="checkbox" checked><span>Copiar clientes e ítems de Bodega</span></label>
                            <label class="admin-check"><input name="migrar_inventario_materiales" type="checkbox"><span>Migrar inventario vivo de Bodega</span></label>
                            <label class="admin-check"><input name="activar_destino" type="checkbox"><span>Activar el destino para todos los procesos</span></label>
                        </div>
                        <p class="admin-form__hint">No se copian recepciones, validaciones, cargas ni procesos históricos. El inventario conserva folio, ubicación, saldos y kardex; requiere no tener despachos ni reservas abiertas.</p>
                        <p class="form-error" id="seasonMigrationError" role="alert"></p>
                        <div class="admin-form__actions"><button class="primary-button" type="submit">Ejecutar migración <span>→</span></button></div>
                    </form>
                </section>

                <div class="admin-grid">
                    <section class="admin-panel panel" aria-labelledby="usersTitle">
                        <div class="admin-panel__heading">
                            <div><p class="eyebrow">PERSONAS</p><h2 id="usersTitle">Usuarios</h2></div>
                            <span id="usersSummary">0 registrados</span>
                        </div>

                        <form class="admin-form" id="createUserForm" novalidate>
                            <div class="admin-form__grid">
                                <label class="field field--wide"><span>Nombre completo *</span><input name="nombre" maxlength="255" placeholder="Ej. Camilo González" required></label>
                                <label class="field field--wide"><span>Correo electrónico *</span><input name="email" type="email" maxlength="255" autocomplete="off" placeholder="camilo@empresa.cl" required></label>
                                <label class="field"><span>Rol *</span><select name="rol" required><option value="camarero_frio">Camarero de frío</option><option value="camarero_materiales">Camarero de materiales</option><option value="operador_prefrio">Operador de prefrío</option><option value="operador_romana">Operador de romana</option><option value="supervisor_frio">Supervisor de frío</option><option value="supervisor_materiales">Supervisor de materiales</option><option value="despachador">Despachador</option><option value="validador">Validador de pallets</option><option value="validador_mp">Validador MP</option><option value="consulta">Solo consulta</option><option value="administrador">Administrador</option></select></label>
                                <label class="field"><span>Contraseña temporal *</span><input name="password" type="password" minlength="10" maxlength="255" autocomplete="new-password" placeholder="Mínimo 10 caracteres" required></label>
                                <label class="field"><span>Confirmar contraseña *</span><input name="password_confirmation" type="password" minlength="10" maxlength="255" autocomplete="new-password" required></label>
                            </div>
                            <p class="admin-form__hint">Mínimo 10 caracteres; debe contener al menos una letra y un número.</p>
                            <p class="form-error" id="createUserError" role="alert"></p>
                            <div class="admin-form__actions"><button class="primary-button" type="submit">Crear usuario <span>→</span></button></div>
                        </form>

                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th></tr></thead>
                                <tbody id="usersTableBody"></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="admin-panel panel" aria-labelledby="devicesTitle">
                        <div class="admin-panel__heading">
                            <div><p class="eyebrow">EQUIPOS</p><h2 id="devicesTitle">Tablets autorizadas</h2></div>
                            <span id="devicesSummary">0 registradas</span>
                        </div>

                        <form class="admin-form" id="createDeviceForm" novalidate>
                            <div class="admin-form__grid admin-form__grid--device">
                                <label class="field"><span>Código de tablet *</span><input name="codigo" maxlength="100" autocomplete="off" placeholder="TABLET-02" required></label>
                                <label class="field"><span>Nombre descriptivo *</span><input name="nombre" maxlength="150" placeholder="Tablet cámara norte" required></label>
                            </div>
                            <p class="admin-form__hint">El código se convierte a mayúsculas y debe coincidir con el utilizado al iniciar turno.</p>
                            <p class="form-error" id="createDeviceError" role="alert"></p>
                            <div class="admin-form__actions"><button class="primary-button" type="submit">Autorizar tablet <span>→</span></button></div>
                        </form>

                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead><tr><th>Tablet</th><th>Último acceso</th><th>Estado</th></tr></thead>
                                <tbody id="devicesTableBody"></tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>
        </main>

        <div class="loading is-hidden" id="officeLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="officeLoadingText">Procesando…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
