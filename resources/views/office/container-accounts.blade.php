<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">
        <title>Estiba WMS · Cuenta corriente de envases</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-container-accounts.css', 'resources/js/office-container-accounts.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess">
            <div class="office-access__brand"><div class="office-logo">⇄</div><p class="eyebrow">ESTIBA WMS · ENVASES</p><h1>Consulta la cuenta corriente con trazabilidad a guía, cliente y hora.</h1><p>Lo declarado queda pendiente hasta Validación MP; lo validado confirma el movimiento y nunca reemplaza la evidencia de Romana.</p></div>
            <form class="office-access__form" id="officeLoginForm"><div><p class="eyebrow">ACCESO DE OFICINA</p><h2>Ingresar a cuenta corriente</h2></div><label><span>Correo electrónico</span><input name="email" type="email" required></label><label><span>Contraseña</span><input name="password" type="password" required></label><p class="form-error" id="officeLoginError"></p><button class="primary-button" type="submit">Ingresar <span>→</span></button></form>
        </section>
        <main class="office-app is-hidden" id="officeApp">
            <header class="office-topbar"><div class="brand-lockup"><span class="office-logo office-logo--small">⇄</span><span><strong>ESTIBA WMS</strong><small>CUENTA ENVASES</small></span></div><nav><a href="/oficina/gerencia">Gerencia</a><a href="/oficina/romana">Romana</a><a class="is-active" href="/oficina/envases/cuenta-corriente">Cuenta envases</a><a href="/oficina/envases/despachos">Guías envases</a><a href="/oficina/accesos">Accesos</a></nav><div class="identity"><span class="identity__avatar" id="officeInitials">CE</span><span><strong id="officeUserName">Usuario</strong><small id="officeUserRole">Consulta</small></span><button id="officeLogoutButton" type="button">Cerrar sesión</button></div></header>
            <section class="accounts-workspace">
                <header class="accounts-heading"><div><p class="eyebrow">CONTROL Y CONCILIACIÓN</p><h1>Cuenta corriente de envases</h1><p>La operación muestra la temporada activa; selecciona otra temporada sólo para consultar su historial.</p></div><button class="secondary-button" id="reloadButton" type="button">↻ Actualizar</button></header>
                <div class="accounts-kpis"><article><span>MOVIMIENTOS CONFIRMADOS</span><strong id="confirmedCount">0</strong></article><article><span>ENVASES RESERVADOS</span><strong id="reservedCount">0</strong></article><article><span>PENDIENTES DE VALIDACIÓN</span><strong id="pendingCount">0</strong></article><article><span>OBSERVADOS</span><strong id="observedCount">0</strong></article><article><span>ÚLTIMA SINCRONIZACIÓN</span><strong id="syncTime">—</strong></article></div>
                <section class="panel accounts-filters"><form id="filtersForm"><input name="buscar" placeholder="Guía, recepción o cliente"><select name="cliente_id"><option value="">Todos los clientes</option></select><select name="temporada_id"><option value="">Temporada activa</option></select><select name="tipo_envase"><option value="">Todos los envases</option></select><select name="estado_revision"><option value="">Toda revisión</option><option value="pendiente">Pendiente</option><option value="revisado">Revisado</option><option value="observado">Observado</option></select><input name="desde" type="date"><input name="hasta" type="date"><button class="primary-button" type="submit">Aplicar filtros</button></form></section>
                <div class="accounts-grid">
                    <section class="panel"><div class="panel-heading"><div><p class="eyebrow">SALDOS</p><h2>Por cliente y tipo</h2></div></div><div class="balance-list" id="balanceList"></div></section>
                    <section class="panel"><div class="panel-heading"><div><p class="eyebrow">EN VERDE</p><h2>Pendiente de Validación MP</h2></div></div><div class="pending-list" id="pendingList"></div></section>
                </div>
                <section class="panel"><div class="panel-heading"><div><p class="eyebrow">RESERVAS OPERACIONALES</p><h2>Borradores de despacho</h2></div><small>No modifican el saldo hasta confirmar la salida.</small></div><div class="reservation-list" id="reservationList"></div></section>
                <section class="panel movements-panel"><div class="panel-heading"><div><p class="eyebrow">KARDEX DOCUMENTAL</p><h2>Movimientos confirmados</h2></div><small>Ingreso y salida conservan fecha y hora exactas.</small></div><div class="accounts-table-scroll"><table><thead><tr><th>Fecha y hora</th><th>Cliente / documento</th><th>Envase</th><th>Cuenta</th><th>Propiedad</th><th>Revisión</th><th>Acción</th></tr></thead><tbody id="movementsBody"></tbody></table></div></section>
            </section>
        </main>
        <dialog class="review-dialog" id="reviewDialog"><form id="reviewForm" method="dialog"><input name="movimiento_id" type="hidden"><div><p class="eyebrow">CHEQUEO DOCUMENTAL</p><h2>Revisar movimiento</h2></div><label><span>Resultado</span><select name="estado" required><option value="revisado">Revisado</option><option value="observado">Observado</option></select></label><label><span>Nota</span><textarea name="nota" maxlength="2000"></textarea></label><p class="form-error" id="reviewError"></p><div class="dialog-actions"><button class="secondary-button" value="cancel">Cancelar</button><button class="primary-button" value="default">Guardar chequeo</button></div></form></dialog>
        <div class="loading is-hidden" id="officeLoading"><span></span><strong id="officeLoadingText">Procesando…</strong></div><div class="toast-region" id="officeToasts"></div>
    </body>
</html>
