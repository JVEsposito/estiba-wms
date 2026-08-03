<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">
        <title>Estiba WMS · Fruta a proceso</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-raw-material-process.css', 'resources/js/office-raw-material-process.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand process-access-brand">
                <div class="office-logo" aria-hidden="true">→</div>
                <p class="eyebrow">ESTIBA WMS · MATERIA PRIMA</p>
                <h1 id="officeAccessTitle">Entrega fruta desde cámara hacia Packing.</h1>
                <p>Cada viaje descuenta únicamente los bins entregados y conserva línea, turno, orden, operador y saldo.</p>
                <div class="feature-row"><span>Viajes parciales</span><span>Saldo en tiempo real</span><span>Corrección trazable</span></div>
            </div>
            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div><p class="eyebrow">ACCESO DE OFICINA</p><h2>Ingresar a Fruta a proceso</h2><p>Disponible para camareros, supervisión y administración autorizada.</p></div>
                <label><span>Correo electrónico</span><input name="email" type="email" autocomplete="username" required></label>
                <label><span>Contraseña</span><input name="password" type="password" autocomplete="current-password" required></label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Entrar al módulo <span>→</span></button>
            </form>
        </section>

        <main class="office-app is-hidden" id="officeApp">
            <x-office.navigation domain="materia-prima" office="fruta-proceso" context="MATERIA PRIMA" icon="→" />

            <section class="process-workspace">
                <header class="process-heading">
                    <div><p class="eyebrow">CÁMARA MP → PACKING</p><h1>Fruta a proceso</h1><p id="seasonDescription">Cargando lotes disponibles…</p></div>
                    <button class="secondary-button" id="reloadButton" type="button">↻ Actualizar</button>
                </header>

                <div class="process-kpis">
                    <article><span>LOTES ABIERTOS</span><strong id="openLotsCount">0</strong><small>Con bins disponibles</small></article>
                    <article><span>BINS DISPONIBLES</span><strong id="availableBinsCount">0</strong><small>Aún en cámara</small></article>
                    <article><span>BINS ENTREGADOS</span><strong id="deliveredBinsCount">0</strong><small>Movimientos vigentes</small></article>
                    <article><span>LOTES COMPLETADOS</span><strong id="completedLotsCount">0</strong><small>Saldo cero</small></article>
                </div>

                <section class="panel process-panel">
                    <div class="process-panel__heading">
                        <div><p class="eyebrow">CONTROL DE DESPACHO INTERNO</p><h2>Lotes en cámara de materia prima</h2><p>Registra la cantidad de cada viaje físico; no es necesario escanear cada bin.</p></div>
                        <form id="processFilters">
                            <input name="buscar" maxlength="100" placeholder="Lote, cliente, CSG u orden">
                            <select name="estado"><option value="abiertos">Abiertos</option><option value="completados">Completados</option><option value="">Todos</option></select>
                            <button class="secondary-button" type="submit">Filtrar</button>
                        </form>
                    </div>
                    <div class="process-lot-list" id="processLotList"></div>
                </section>
            </section>
        </main>

        <dialog class="process-dialog" id="deliveryDialog">
            <form method="dialog" id="deliveryForm" novalidate>
                <div class="process-dialog__heading"><div><p class="eyebrow">VIAJE FÍSICO A PACKING</p><h2 id="deliveryTitle">Registrar entrega</h2><p id="deliveryDescription"></p></div><button value="cancel" type="submit" aria-label="Cerrar">×</button></div>
                <input name="lote_id" type="hidden">
                <div class="delivery-summary" id="deliverySummary"></div>
                <div class="delivery-fields">
                    <label><span>Cantidad de bins *</span><input name="cantidad_envases" type="number" min="1" max="100000" inputmode="numeric" required></label>
                    <label><span>Línea de proceso *</span><input name="linea_proceso" maxlength="50" autocomplete="off" placeholder="Ej. Línea 1" required></label>
                    <label><span>Turno *</span><select name="turno" required><option value="">Seleccionar</option><option value="A">Turno A</option><option value="B">Turno B</option></select></label>
                    <label><span>N° de orden *</span><input name="numero_orden" maxlength="80" autocomplete="off" required></label>
                    <label class="field-wide"><span>Observación</span><textarea name="observacion" maxlength="2000"></textarea></label>
                </div>
                <p class="form-error" id="deliveryError" role="alert"></p>
                <div class="dialog-actions"><button class="secondary-button" value="cancel" type="submit">Cancelar</button><button class="primary-button" value="default" type="submit">Confirmar viaje</button></div>
            </form>
        </dialog>

        <div class="loading is-hidden" id="officeLoading" aria-hidden="true"><span></span><strong id="officeLoadingText">Procesando…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
