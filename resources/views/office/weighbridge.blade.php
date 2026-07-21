<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">
        <title>Estiba WMS · Romana</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-weighbridge.css', 'resources/js/office-weighbridge.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand weighbridge-access-brand">
                <div class="office-logo" aria-hidden="true">⚖</div>
                <p class="eyebrow">ESTIBA WMS · ROMANA</p>
                <h1 id="officeAccessTitle">Registra exactamente lo que ingresó al frigorífico.</h1>
                <p>El pesaje inicial es el contrato operacional con el cliente: guía, transporte, envases y kilos quedan trazados desde el primer minuto.</p>
                <div class="feature-row"><span>Pesaje en dos tiempos</span><span>Correlativo inviolable</span><span>Aviso de Recibo PDF</span></div>
            </div>
            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div><p class="eyebrow">ACCESO DE OFICINA</p><h2>Ingresar a Romana</h2><p>Disponible para operador de romana, supervisión, administración y consulta.</p></div>
                <label><span>Correo electrónico</span><input name="email" type="email" autocomplete="username" required></label>
                <label><span>Contraseña</span><input name="password" type="password" autocomplete="current-password" required></label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Entrar a Romana <span>→</span></button>
            </form>
        </section>

        <main class="office-app is-hidden" id="officeApp">
            <header class="office-topbar weighbridge-topbar">
                <div class="brand-lockup"><span class="office-logo office-logo--small" aria-hidden="true">⚖</span><span><strong>ESTIBA WMS</strong><small>ROMANA</small></span></div>
                <nav aria-label="Módulos de oficina">
                    <a class="is-hidden" id="officeManagementNav" href="/oficina/gerencia">Gerencia</a>
                    <a class="is-active" href="/oficina/romana">Romana</a>
                    <a class="is-hidden" id="officeContainerAccountsNav" href="/oficina/envases/cuenta-corriente">Cuenta envases</a>
                    <a class="is-hidden" id="officeCamerasNav" href="/oficina/camaras">Cámaras</a>
                    <a class="is-hidden" id="officeLoadsNav" href="/oficina/cargas">Cargas</a>
                    <a class="is-hidden" id="officeMaterialsNav" href="/oficina/materiales">Materiales</a>
                    <a class="is-hidden" id="officeValidationNav" href="/oficina/validacion">Validación</a>
                    <a class="is-hidden" id="officePrefrioNav" href="/oficina/prefrio">Prefrío</a>
                    <a class="is-hidden" id="officeAccessesNav" href="/oficina/accesos">Accesos</a>
                </nav>
                <div class="identity"><span class="identity__avatar" id="officeInitials">RO</span><span><strong id="officeUserName">Usuario</strong><small id="officeUserRole">Romana</small></span><button id="officeLogoutButton" type="button">Cerrar sesión</button></div>
            </header>

            <section class="weighbridge-workspace">
                <header class="weighbridge-heading">
                    <div><p class="eyebrow">RECEPCIÓN CONTRACTUAL</p><h1>Control de Romana</h1><p>Ingreso cargado, retorno vacío y cierre documental en una sola trazabilidad.</p></div>
                    <div class="weighbridge-heading__actions">
                        <button class="secondary-button" id="reloadButton" type="button">↻ Actualizar</button>
                        <button class="primary-button" id="newReceptionButton" type="button">+ Registrar ingreso</button>
                    </div>
                </header>

                <div class="weighbridge-kpis">
                    <article><span>EN ROMANA · INGRESO</span><strong id="entryCount">0</strong><small>Pendientes de confirmar bruto</small></article>
                    <article><span>PENDIENTES DE DESTARE</span><strong id="exitCount">0</strong><small>Camiones que deben volver vacíos</small></article>
                    <article><span>RECEPCIONES CERRADAS</span><strong id="closedCount">0</strong><small>Según los filtros aplicados</small></article>
                    <article class="weighbridge-kpi--weight"><span>PESO NETO RECEPCIONADO</span><strong id="netWeight">0 kg</strong><small>Bruto menos tara documentada</small></article>
                </div>

                <section class="panel weighbridge-list-panel">
                    <div class="weighbridge-panel-heading">
                        <div><p class="eyebrow">TRÁNSITO Y CIERRES</p><h2>Recepciones</h2></div>
                        <form class="weighbridge-filters" id="receptionFilters">
                            <input name="buscar" maxlength="100" placeholder="Recepción, guía, patente, cliente">
                            <select name="temporada_id"><option value="">Todas las temporadas</option></select>
                            <select name="estado"><option value="">Todos los estados</option><option value="en_bascula_ingreso">En báscula ingreso</option><option value="en_bascula_salida">Pendiente de destare</option><option value="cerrado">Cerrado</option></select>
                            <input name="desde" type="date" aria-label="Desde">
                            <input name="hasta" type="date" aria-label="Hasta">
                            <button class="secondary-button" type="submit">Filtrar</button>
                        </form>
                    </div>
                    <div class="weighbridge-table-scroll">
                        <table class="weighbridge-table">
                            <thead><tr><th>Recepción</th><th>Cliente / guía</th><th>Transporte</th><th>Declaración</th><th>Pesos</th><th>Estado</th></tr></thead>
                            <tbody id="receptionTableBody"></tbody>
                        </table>
                    </div>
                    <div class="weighbridge-pagination"><span id="paginationSummary">0 recepciones</span><div><button id="previousPageButton" type="button">← Anterior</button><button id="nextPageButton" type="button">Siguiente →</button></div></div>
                </section>

                <section class="panel reception-detail is-hidden" id="receptionDetail">
                    <div class="weighbridge-panel-heading">
                        <div><p class="eyebrow">EXPEDIENTE DE RECEPCIÓN</p><h2 id="detailTitle">Recepción</h2><p id="detailSubtitle"></p></div>
                        <div class="detail-actions">
                            <button class="secondary-button is-hidden" id="editReceptionButton" type="button">Editar ingreso</button>
                            <button class="primary-button is-hidden" id="confirmEntryButton" type="button">Confirmar ingreso</button>
                            <button class="primary-button is-hidden" id="closeReceptionButton" type="button">Registrar destare y cerrar</button>
                            <button class="secondary-button is-hidden" id="downloadReceiptButton" type="button">↓ Aviso de Recibo PDF</button>
                            <button class="secondary-button" id="closeDetailButton" type="button">Cerrar detalle</button>
                        </div>
                    </div>
                    <div class="reception-detail__grid" id="detailFacts"></div>
                    <div class="reception-detail__bottom"><section><p class="eyebrow">TRAZABILIDAD</p><h3>Línea de tiempo</h3><div class="weighbridge-timeline" id="detailTimeline"></div></section><section><p class="eyebrow">CONTROL DE PESO</p><h3>Balance del pesaje</h3><div class="weight-balance" id="weightBalance"></div></section></div>
                </section>
            </section>
        </main>

        <dialog class="weighbridge-dialog" id="receptionDialog">
            <form method="dialog" class="weighbridge-dialog__shell" id="receptionForm" novalidate>
                <div class="weighbridge-dialog__heading"><div><p class="eyebrow">PESAJE DE ENTRADA</p><h2 id="receptionDialogTitle">Registrar ingreso</h2><p>Captura los antecedentes documentales y el peso del camión cargado.</p></div><button class="dialog-close" value="cancel" type="submit" aria-label="Cerrar">×</button></div>
                <input name="recepcion_id" type="hidden">
                <div class="weighbridge-form-grid">
                    <label class="field field--span-2"><span>Temporada global *</span><select name="temporada_id" required><option value="">Seleccionar temporada activa</option></select></label>
                    <label class="field field--span-2"><span>Cliente *</span><select name="cliente_id" required><option value="">Seleccionar cliente activo</option></select></label>
                    <label class="field"><span>Tipo de recepción *</span><select name="tipo_recepcion" required></select></label>
                    <label class="field is-hidden" id="containerConceptField"><span>Concepto de envases *</span><select name="concepto_envases"></select></label>
                    <label class="field" id="serviceField"><span>Servicio de fruta *</span><select name="tipo_servicio" required></select></label>
                    <label class="field"><span>Guía de despacho *</span><input name="numero_guia_despacho" maxlength="80" required></label>
                    <fieldset class="field field--span-2 container-lines"><legend>Envases declarados en la guía *</legend><label><span>Bins</span><input name="cantidad_bins" type="number" min="0" max="100000" value="0"></label><label><span>Totes</span><input name="cantidad_totes" type="number" min="0" max="100000" value="0"></label><label><span>Esponjas</span><input name="cantidad_esponjas" type="number" min="0" max="100000" value="0"></label><small>Registra uno, dos o los tres tipos. Cada uno mantiene su propia trazabilidad.</small></fieldset>
                    <label class="field"><span>Patente camión *</span><input name="patente_camion" maxlength="10" autocomplete="off" placeholder="ABCD12" required></label>
                    <label class="field"><span>Patente carro</span><input name="patente_carro" maxlength="10" autocomplete="off" placeholder="Opcional"></label>
                    <label class="field"><span>RUT conductor *</span><input name="rut_conductor" maxlength="12" placeholder="12.345.678-5" required></label>
                    <label class="field field--span-2"><span>Nombre conductor *</span><input name="nombre_conductor" maxlength="150" required></label>
                    <label class="field weight-field"><span>Peso bruto *</span><div><input name="peso_bruto" type="number" min="1" max="200000" step="0.01" inputmode="decimal" required><b>kg</b></div><small>Lectura del camión cargado sobre la romana.</small></label>
                    <label class="field field--span-2"><span>Observación</span><textarea name="observacion" maxlength="2000"></textarea></label>
                </div>
                <p class="form-error" id="receptionFormError" role="alert"></p>
                <div class="dialog-actions"><button class="secondary-button" value="cancel" type="submit">Cancelar</button><button class="primary-button" id="saveReceptionButton" value="default" type="submit">Guardar pesaje de ingreso</button></div>
            </form>
        </dialog>

        <dialog class="weighbridge-dialog" id="tareDialog">
            <form method="dialog" class="weighbridge-dialog__shell weighbridge-dialog__shell--compact" id="tareForm" novalidate>
                <div class="weighbridge-dialog__heading"><div><p class="eyebrow">BÁSCULA DE SALIDA</p><h2>Registrar destare</h2><p id="tareDescription">Captura la lectura del camión vacío.</p></div><button class="dialog-close" value="cancel" type="submit" aria-label="Cerrar">×</button></div>
                <label class="field weight-field"><span>Peso tara *</span><div><input name="peso_tara" type="number" min="1" max="200000" step="0.01" inputmode="decimal" required><b>kg</b></div></label>
                <label class="field"><span>Observación de cierre</span><textarea name="observacion" maxlength="2000"></textarea></label>
                <div class="net-preview"><span>PESO NETO CALCULADO</span><strong id="netWeightPreview">—</strong></div>
                <p class="form-error" id="tareFormError" role="alert"></p>
                <div class="dialog-actions"><button class="secondary-button" value="cancel" type="submit">Cancelar</button><button class="primary-button" value="default" type="submit">Cerrar y emitir aviso</button></div>
            </form>
        </dialog>

        <div class="loading is-hidden" id="officeLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="officeLoadingText">Procesando…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
