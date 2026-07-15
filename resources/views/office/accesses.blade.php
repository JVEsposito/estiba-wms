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
                <h1 id="officeAccessTitle">Autoriza a las personas y tablets que participan en la operación.</h1>
                <p>Los nuevos accesos quedan activos inmediatamente. Solo un administrador puede utilizar este módulo.</p>
                <div class="feature-row">
                    <span>Contraseñas cifradas</span>
                    <span>Roles operacionales</span>
                    <span>Tablets identificadas</span>
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
                    <a href="/oficina/camaras">Cámaras</a>
                    <a href="/oficina/cargas">Cargas</a>
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
                        <p class="eyebrow">CONTROL DE ACCESO</p>
                        <h1>Usuarios y tablets autorizadas</h1>
                        <p>Crea credenciales personales y registra los equipos habilitados para entrar a la operación.</p>
                    </div>
                    <button class="secondary-button admin-reload" id="reloadAccessesButton" type="button">↻ Actualizar listados</button>
                </header>

                <div class="admin-metrics">
                    <article><span>USUARIOS ACTIVOS</span><strong id="activeUsersCount">0</strong></article>
                    <article><span>TABLETS ACTIVAS</span><strong id="activeDevicesCount">0</strong></article>
                    <article><span>ÚLTIMO ACCESO TABLET</span><strong id="lastDeviceAccess">Sin accesos</strong></article>
                </div>

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
                                <label class="field"><span>Rol *</span><select name="rol" required><option value="operador">Operador / camarero</option><option value="despachador">Despachador</option><option value="supervisor">Supervisor</option><option value="consulta">Solo consulta</option><option value="administrador">Administrador</option></select></label>
                                <label class="field"><span>Contraseña temporal *</span><input name="password" type="password" minlength="10" maxlength="255" autocomplete="new-password" placeholder="Mínimo 10 caracteres" required></label>
                                <label class="field"><span>Confirmar contraseña *</span><input name="password_confirmation" type="password" minlength="10" maxlength="255" autocomplete="new-password" required></label>
                            </div>
                            <p class="admin-form__hint">Debe contener al menos una letra y un número.</p>
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
