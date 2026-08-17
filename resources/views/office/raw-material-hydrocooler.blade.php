<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">
        <title>Estiba WMS · Hidrocooler</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-hydrocooler.css', 'resources/js/office-hydrocooler.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand hydrocooler-access-brand">
                <div class="office-logo" aria-hidden="true">❄</div>
                <p class="eyebrow">ESTIBA WMS · MATERIA PRIMA</p>
                <h1 id="officeAccessTitle">Un ciclo de Hidrocooler por cada lote.</h1>
                <p>Registra tiempos, operador, equipo, temperaturas, kilos y el destino físico de la fruta sin perder trazabilidad.</p>
                <div class="feature-row"><span>1 lote por ciclo</span><span>Tiempo real</span><span>Temperaturas</span><span>Salida controlada</span></div>
            </div>
            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div><p class="eyebrow">ACCESO DE OFICINA</p><h2>Ingresar a Hidrocooler</h2><p>Disponible para operación, supervisión, administración y consulta autorizada.</p></div>
                <label><span>Correo electrónico</span><input name="email" type="email" autocomplete="username" required></label>
                <label><span>Contraseña</span><input name="password" type="password" autocomplete="current-password" required></label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Entrar al módulo <span>→</span></button>
            </form>
        </section>

        <main class="office-app is-hidden" id="officeApp">
            <x-office.navigation domain="materia-prima" office="hidrocooler" context="MATERIA PRIMA" icon="❄" />

            <section class="hydrocooler-workspace">
                <header class="hydrocooler-heading">
                    <div><p class="eyebrow">CONTROL DE ENFRIAMIENTO MP</p><h1>Hidrocooler</h1><p id="seasonDescription">Cargando ciclos de la temporada…</p></div>
                    <button class="secondary-button" id="reloadButton" type="button">↻ Actualizar</button>
                </header>

                <div class="hydrocooler-kpis">
                    <article><span>LOTES EN ESPERA</span><strong id="pendingCount">0</strong><small>Confirmados por Digitación</small></article>
                    <article><span>CICLOS EN CURSO</span><strong id="activeCount">0</strong><small>Un lote por equipo</small></article>
                    <article><span>KILOS EN CURSO</span><strong id="activeKilos">0</strong><small>Peso congelado al iniciar</small></article>
                    <article><span>COMPLETADOS HOY</span><strong id="completedToday">0</strong><small>Con destino definido</small></article>
                    <article><span>TIEMPO PROMEDIO HOY</span><strong id="averageDuration">0 min</strong><small>Calculado por el servidor</small></article>
                </div>

                <section class="panel hydrocooler-panel">
                    <div class="hydrocooler-toolbar">
                        <div class="hydrocooler-tabs" role="tablist" aria-label="Bandejas Hidrocooler">
                            <button class="is-active" data-tray="pendientes" type="button">Pendientes</button>
                            <button data-tray="en_curso" type="button">En curso</button>
                            <button data-tray="historial" type="button">Historial</button>
                        </div>
                        <form id="hydrocoolerFilters">
                            <input name="buscar" maxlength="100" placeholder="Lote, recepción, cliente, ciclo o equipo">
                            <select name="equipo" id="equipmentFilter"><option value="">Todos los equipos</option></select>
                            <select name="destino"><option value="">Todos los destinos</option><option value="camara">Cámara MP</option><option value="proceso">Directo a proceso</option></select>
                            <input name="desde" type="date" aria-label="Desde">
                            <input name="hasta" type="date" aria-label="Hasta">
                            <button class="secondary-button" type="submit">Filtrar</button>
                        </form>
                    </div>
                    <div class="hydrocooler-list" id="hydrocoolerList"></div>
                </section>
            </section>
        </main>

        <dialog class="hydrocooler-dialog" id="startDialog">
            <form method="dialog" id="startForm" novalidate>
                <div class="hydrocooler-dialog__heading"><div><p class="eyebrow">INICIO TRAZABLE</p><h2 id="startTitle">Iniciar ciclo</h2><p id="startDescription"></p></div><button value="cancel" type="submit" aria-label="Cerrar">×</button></div>
                <input name="lote_id" type="hidden">
                <input name="operacion_id" type="hidden">
                <div class="cycle-summary" id="startSummary"></div>
                <div class="hydrocooler-fields">
                    <label><span>Equipo / Hidrocooler *</span><input name="equipo" list="equipmentOptions" maxlength="100" autocomplete="off" required></label>
                    <label><span>Operador responsable</span><input name="operador" readonly></label>
                    <label><span>Fecha y hora de inicio *</span><input name="inicio_at" type="datetime-local" required></label>
                    <label><span>Temperatura inicial fruta °C *</span><input name="temperatura_inicial_c" type="number" min="-20" max="50" step="0.01" required></label>
                    <label><span>Temperatura objetivo fruta °C *</span><input name="temperatura_objetivo_c" type="number" min="-20" max="50" step="0.01" required></label>
                    <label><span>Temperatura inicial agua °C</span><input name="temperatura_agua_inicial_c" type="number" min="-20" max="50" step="0.01"></label>
                    <label class="field-wide"><span>Observación de inicio</span><textarea name="observacion_inicio" maxlength="2000"></textarea></label>
                </div>
                <datalist id="equipmentOptions"></datalist>
                <p class="form-error" id="startError" role="alert"></p>
                <div class="dialog-actions"><button class="secondary-button" value="cancel" type="submit">Cancelar</button><button class="primary-button" value="default" type="submit">Iniciar ciclo</button></div>
            </form>
        </dialog>

        <dialog class="hydrocooler-dialog" id="finishDialog">
            <form method="dialog" id="finishForm" novalidate>
                <div class="hydrocooler-dialog__heading"><div><p class="eyebrow">CIERRE Y DESTINO</p><h2 id="finishTitle">Finalizar ciclo</h2><p id="finishDescription"></p></div><button value="cancel" type="submit" aria-label="Cerrar">×</button></div>
                <input name="lote_id" type="hidden">
                <input name="operacion_id" type="hidden">
                <div class="cycle-summary" id="finishSummary"></div>
                <div class="hydrocooler-fields">
                    <label><span>Fecha y hora de término *</span><input name="termino_at" type="datetime-local" required></label>
                    <label><span>Temperatura final fruta °C *</span><input name="temperatura_c" type="number" min="-20" max="50" step="0.01" required></label>
                    <label><span>Temperatura final agua °C</span><input name="temperatura_agua_final_c" type="number" min="-20" max="50" step="0.01"></label>
                    <fieldset class="destination-choice"><legend>Destino después del Hidrocooler *</legend><label><input name="destino_salida" type="radio" value="camara" checked required><span><strong>Cámara MP</strong><small>Queda pendiente de asignación a cámara.</small></span></label><label><input name="destino_salida" type="radio" value="proceso" required><span><strong>Directo a Fruta a proceso</strong><small>Disponible para viajes a Packing sin ubicación ficticia.</small></span></label></fieldset>
                    <label class="field-wide"><span>Observación de término</span><textarea name="observacion" maxlength="2000"></textarea></label>
                </div>
                <p class="form-error" id="finishError" role="alert"></p>
                <div class="dialog-actions"><button class="secondary-button" value="cancel" type="submit">Cancelar</button><button class="primary-button" value="default" type="submit">Finalizar y liberar lote</button></div>
            </form>
        </dialog>

        <div class="loading is-hidden" id="officeLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="officeLoadingText">Procesando…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
