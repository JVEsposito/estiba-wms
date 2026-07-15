<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">
        <title>Estiba WMS · Materiales</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-materials.css', 'resources/js/office-materials.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand materials-access-brand">
                <div class="office-logo" aria-hidden="true">▦</div>
                <p class="eyebrow">ESTIBA WMS · MATERIALES</p>
                <h1 id="officeAccessTitle">Inventario por folio, cantidades y centro de costo.</h1>
                <p>Administra ítems y destinos, prepara solicitudes y consulta el saldo físico disponible en las cámaras de materiales.</p>
                <div class="feature-row"><span>Catálogo controlado</span><span>Reserva FIFO</span><span>Kardex trazable</span></div>
            </div>
            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div><p class="eyebrow">ACCESO DE OFICINA</p><h2>Ingresar a materiales</h2><p>Disponible para despachadores, supervisores y administradores.</p></div>
                <label><span>Correo electrónico</span><input name="email" type="email" autocomplete="username" required></label>
                <label><span>Contraseña</span><input name="password" type="password" autocomplete="current-password" required></label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Entrar a materiales <span>→</span></button>
            </form>
        </section>

        <main class="office-app is-hidden" id="officeApp">
            <header class="office-topbar">
                <div class="brand-lockup"><span class="office-logo office-logo--small" aria-hidden="true">▦</span><span><strong>ESTIBA WMS</strong><small>MATERIALES</small></span></div>
                <nav aria-label="Módulos de oficina">
                    <a href="/oficina/camaras">Cámaras</a><a href="/oficina/cargas">Cargas</a><a class="is-active" href="/oficina/materiales">Materiales</a><a class="is-hidden" id="officeAccessesNav" href="/oficina/accesos">Accesos</a>
                </nav>
                <div class="identity"><span class="identity__avatar" id="officeInitials">MT</span><span><strong id="officeUserName">Usuario</strong><small id="officeUserRole">Oficina</small></span><button id="officeLogoutButton" type="button">Cerrar sesión</button></div>
            </header>

            <section class="materials-workspace">
                <header class="materials-heading panel">
                    <div><p class="eyebrow">CONTROL DE MATERIALES</p><h1>Inventario y despachos internos</h1><p>Las cantidades reservadas no se ofrecen a otra orden; FIFO es una sugerencia y nunca un bloqueo.</p></div>
                    <button class="secondary-button" id="reloadMaterialsButton" type="button">↻ Actualizar</button>
                </header>

                <div class="materials-metrics">
                    <article><span>ÍTEMS ACTIVOS</span><strong id="materialsItemCount">0</strong></article>
                    <article><span>FOLIOS CON SALDO</span><strong id="materialsFolioCount">0</strong></article>
                    <article><span>DESPACHOS ABIERTOS</span><strong id="materialsDispatchCount">0</strong></article>
                    <article><span>DESTINOS ACTIVOS</span><strong id="materialsDestinationCount">0</strong></article>
                </div>

                <div class="materials-admin-grid" id="materialsAdminCatalogs">
                    <section class="panel materials-panel">
                        <div class="materials-panel__heading"><div><p class="eyebrow">CATÁLOGO</p><h2>Ítems seleccionables</h2></div><span id="itemsSummary">0 registrados</span></div>
                        <form class="materials-form" id="itemMaterialForm" novalidate>
                            <input name="id" type="hidden">
                            <div class="materials-form__grid">
                                <label><span>Código *</span><input name="codigo" maxlength="80" placeholder="MAT-CAJ-010" required></label>
                                <label><span>Descripción *</span><input name="nombre" maxlength="180" placeholder="Caja cartón 10 kg" required></label>
                                <label><span>Categoría</span><input name="categoria" maxlength="100" placeholder="Cajas"></label>
                                <label><span>Unidad *</span><input name="unidad_medida" maxlength="40" placeholder="unidades" required></label>
                                <label><span>Código ERP futuro</span><input name="codigo_externo" maxlength="150"></label>
                                <label class="materials-check"><input name="activo" type="checkbox" checked><span>Ítem activo</span></label>
                            </div>
                            <p class="form-error" id="itemMaterialError" role="alert"></p>
                            <div class="materials-actions"><button class="secondary-button is-hidden" id="cancelItemEdit" type="button">Cancelar</button><button class="primary-button" type="submit">Guardar ítem</button></div>
                        </form>
                        <div class="materials-list" id="itemsMaterialList"></div>
                    </section>

                    <section class="panel materials-panel">
                        <div class="materials-panel__heading"><div><p class="eyebrow">DESTINOS</p><h2>Centros de costo</h2></div><span id="destinationsSummary">0 registrados</span></div>
                        <form class="materials-form" id="destinationMaterialForm" novalidate>
                            <input name="id" type="hidden">
                            <div class="materials-form__grid">
                                <label><span>Nombre *</span><input name="nombre" maxlength="180" placeholder="Packing cerezas" required></label>
                                <label><span>Centro de costo *</span><input name="centro_costo" maxlength="100" placeholder="CC-1205" required></label>
                                <label><span>Código ERP futuro</span><input name="codigo_externo" maxlength="150"></label>
                                <label class="materials-check"><input name="activo" type="checkbox" checked><span>Destino activo</span></label>
                                <label class="materials-wide"><span>Descripción</span><textarea name="descripcion" maxlength="1000" rows="2"></textarea></label>
                            </div>
                            <p class="form-error" id="destinationMaterialError" role="alert"></p>
                            <div class="materials-actions"><button class="secondary-button is-hidden" id="cancelDestinationEdit" type="button">Cancelar</button><button class="primary-button" type="submit">Guardar destino</button></div>
                        </form>
                        <div class="materials-list" id="destinationsMaterialList"></div>
                    </section>
                </div>

                <div class="materials-operation-grid">
                    <section class="panel materials-panel">
                        <div class="materials-panel__heading"><div><p class="eyebrow">SOLICITUD</p><h2>Nuevo despacho de materiales</h2></div><span>Reserva FIFO automática</span></div>
                        <form class="materials-form" id="dispatchMaterialForm" novalidate>
                            <label><span>Destino *</span><select name="destino_material_id" id="dispatchDestination" required></select></label>
                            <label class="materials-wide"><span>Observación</span><textarea name="observacion" maxlength="1000" rows="2"></textarea></label>
                            <div class="dispatch-lines" id="dispatchMaterialLines"></div>
                            <button class="secondary-button" id="addDispatchLine" type="button">+ Agregar ítem</button>
                            <p class="form-error" id="dispatchMaterialError" role="alert"></p>
                            <div class="materials-actions"><button class="primary-button" type="submit">Crear despacho y reservar</button></div>
                        </form>
                        <div class="dispatch-list" id="dispatchMaterialList"></div>
                    </section>

                    <section class="panel materials-panel materials-inventory-panel">
                        <div class="materials-panel__heading"><div><p class="eyebrow">EXISTENCIA</p><h2>Folios en cámaras</h2></div><input id="materialsInventorySearch" type="search" placeholder="Buscar folio o ítem"></div>
                        <div class="materials-table-scroll"><table class="materials-table"><thead><tr><th>Folio</th><th>Ítem</th><th>Actual</th><th>Reservada</th><th>Disponible</th><th>Ubicación</th></tr></thead><tbody id="materialsInventoryBody"></tbody></table></div>
                    </section>
                </div>
            </section>
        </main>
        <div class="loading is-hidden" id="officeLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="officeLoadingText">Procesando…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
