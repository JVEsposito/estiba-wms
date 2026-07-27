<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">
        <title>Estiba WMS · Existencias</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-inventory-exports.css', 'resources/js/office-inventory-exports.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand inventory-access-brand">
                <div class="office-logo" aria-hidden="true">▤</div>
                <p class="eyebrow">ESTIBA WMS · EXISTENCIAS</p>
                <h1 id="officeAccessTitle">Tres inventarios. Una fuente oficial.</h1>
                <p>Descarga producto terminado, materiales o materia prima sin mezclar unidades, estados ni responsabilidades operacionales.</p>
                <div class="feature-row">
                    <span>Corte histórico XLSX</span>
                    <span>Excel conectado</span>
                    <span>Solo lectura</span>
                </div>
            </div>
            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div>
                    <p class="eyebrow">ACCESO DE OFICINA</p>
                    <h2>Ingresar a existencias</h2>
                    <p>La pantalla mostrará únicamente las existencias autorizadas para tu perfil.</p>
                </div>
                <label><span>Correo electrónico</span><input name="email" type="email" autocomplete="username" required></label>
                <label><span>Contraseña</span><input name="password" type="password" autocomplete="current-password" required></label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Abrir existencias <span>→</span></button>
            </form>
        </section>

        <main class="office-app inventory-app is-hidden" id="officeApp">
            
            <x-office.navigation domain="materiales" office="existencias" context="MATERIALES" icon="⇩" />


            <section class="inventory-workspace">
                <header class="inventory-heading panel">
                    <div>
                        <p class="eyebrow">DESCARGAS CONTROLADAS</p>
                        <h1>Existencias</h1>
                        <p>Cada archivo mantiene su propia unidad operacional y consulta únicamente la temporada activa.</p>
                    </div>
                    <button class="secondary-button" id="reloadInventoryButton" type="button">↻ Actualizar permisos</button>
                </header>

                <section class="inventory-cards" id="inventoryCards" aria-live="polite"></section>

                <section class="inventory-help-grid">
                    <article class="panel inventory-help-card">
                        <span class="inventory-help-card__icon" aria-hidden="true">▦</span>
                        <div>
                            <p class="eyebrow">CORTE ESTÁTICO</p>
                            <h2>Excel de respaldo</h2>
                            <p>Genera un archivo <strong>.xlsx</strong> con fecha de corte, usuario, filtros y valores numéricos reales. No cambia después de descargarlo.</p>
                            <ul><li>Auditorías y conciliaciones</li><li>Cierres diarios</li><li>Envío a terceros</li></ul>
                        </div>
                    </article>
                    <article class="panel inventory-help-card inventory-help-card--connected">
                        <span class="inventory-help-card__icon" aria-hidden="true">↻</span>
                        <div>
                            <p class="eyebrow">EXCEL CONECTADO</p>
                            <h2>Consulta autoactualizable</h2>
                            <p>Descarga una consulta <strong>.iqy</strong>. Ábrela en Excel, guarda el libro como <strong>.xlsx</strong> y usa <strong>Datos → Actualizar todo</strong>.</p>
                            <ol><li>Abre el archivo descargado con Excel.</li><li>Acepta la conexión de solo lectura.</li><li>Guarda el libro como XLSX.</li><li>En Propiedades de conexión activa “Actualizar al abrir”.</li></ol>
                        </div>
                    </article>
                </section>

                <section class="panel inventory-connections-panel">
                    <header>
                        <div><p class="eyebrow">SEGURIDAD</p><h2>Conexiones emitidas</h2><p>Revoca una conexión cuando el archivo deje de usarse, se pierda o sea compartido por error.</p></div>
                        <span class="inventory-connection-count" id="inventoryConnectionCount">0</span>
                    </header>
                    <div class="table-scroll">
                        <table>
                            <thead><tr><th>Existencia</th><th>Creada</th><th>Último uso</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
                            <tbody id="inventoryConnectionRows"></tbody>
                        </table>
                    </div>
                </section>
            </section>
        </main>

        <div class="loading is-hidden" id="officeLoading" role="status" aria-live="assertive" aria-hidden="true"><span aria-hidden="true"></span><strong id="officeLoadingText">Preparando existencia…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
