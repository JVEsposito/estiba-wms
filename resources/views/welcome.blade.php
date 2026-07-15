<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#090d11">
        <meta name="color-scheme" content="dark">

        <title>Estiba WMS · Operación de cámaras</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body>
        <section class="access-screen" id="accessScreen" aria-labelledby="accessTitle">
            <div class="access-brand">
                <div class="brand-mark brand-mark--large" aria-hidden="true">
                    <svg viewBox="0 0 32 32"><path d="M5 9.5 16 4l11 5.5v13L16 28 5 22.5z"/><path d="m5 9.5 11 5.7 11-5.7M16 15.2V28"/></svg>
                </div>
                <p class="eyebrow">OPERACIÓN DE CÁMARAS</p>
                <h1 id="accessTitle">Estiba WMS</h1>
                <p>Ubica y mueve folios con trazabilidad completa desde tu tablet.</p>
                <div class="access-features" aria-label="Características operacionales">
                    <span><i class="status-dot status-dot--green"></i> Bloqueo seguro por cámara</span>
                    <span><i class="status-dot status-dot--cyan"></i> Movimientos sincronizados</span>
                </div>
            </div>

            <form class="access-form" id="accessForm" novalidate>
                <div>
                    <p class="eyebrow">ACCESO OPERACIONAL</p>
                    <h2>Iniciar turno</h2>
                    <p class="form-intro">Ingresa tus credenciales y el código visible en esta tablet.</p>
                </div>

                <label>
                    <span>Correo</span>
                    <input type="email" name="email" autocomplete="username" inputmode="email" placeholder="operador@empresa.cl" required>
                </label>

                <label>
                    <span>Contraseña</span>
                    <input type="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
                </label>

                <label>
                    <span>Código de tablet</span>
                    <input type="text" name="codigo_dispositivo" autocomplete="off" autocapitalize="characters" placeholder="TABLET-01" required>
                </label>

                <p class="form-error" id="accessError" role="alert"></p>
                <button class="button button--primary button--wide" type="submit">
                    <span>Entrar a cámaras</span>
                    <span aria-hidden="true">→</span>
                </button>
                <p class="access-help">Si esta tablet no está autorizada, solicita su habilitación al supervisor.</p>
            </form>
        </section>

        <main class="app-shell is-hidden" id="appShell">
            <header class="topbar">
                <div class="brand-lockup">
                    <div class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 32 32"><path d="M5 9.5 16 4l11 5.5v13L16 28 5 22.5z"/><path d="m5 9.5 11 5.7 11-5.7M16 15.2V28"/></svg>
                    </div>
                    <div>
                        <strong>ESTIBA</strong>
                        <span>WMS</span>
                    </div>
                </div>

                <nav class="module-nav" aria-label="Módulos">
                    <button class="module-nav__item is-active" type="button">Estibas</button>
                    <button class="module-nav__item" type="button" disabled>Inventario</button>
                    <button class="module-nav__item" type="button" disabled>Despachos</button>
                </nav>

                <div class="topbar-status">
                    <span class="status-chip" id="networkStatus">
                        <i class="status-dot status-dot--green"></i>
                        <span>En línea</span>
                    </span>
                    <span class="status-chip status-chip--session" id="globalSessionStatus">
                        <i class="status-dot"></i>
                        <span>Solo consulta</span>
                    </span>
                    <button class="operator-button" id="operatorButton" type="button" aria-expanded="false">
                        <span class="operator-avatar" id="operatorInitials">OP</span>
                        <span class="operator-copy">
                            <strong id="operatorName">Operador</strong>
                            <small id="deviceName">Tablet</small>
                        </span>
                        <span aria-hidden="true">⌄</span>
                    </button>
                    <button class="logout-button is-hidden" id="logoutButton" type="button">Cerrar turno</button>
                </div>
            </header>

            <section class="workspace">
                <aside class="camera-panel panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">CÁMARAS</p>
                            <h2>Área de trabajo</h2>
                        </div>
                        <button class="icon-button" id="refreshCamerasButton" type="button" aria-label="Actualizar cámaras" title="Actualizar cámaras">↻</button>
                    </div>
                    <div class="camera-list" id="cameraList" aria-live="polite"></div>
                    <div class="camera-legend">
                        <span><i class="status-dot status-dot--green"></i> Disponible</span>
                        <span><i class="status-dot status-dot--amber"></i> En uso</span>
                    </div>
                </aside>

                <section class="plan-panel panel" aria-labelledby="planTitle">
                    <div class="plan-heading">
                        <div>
                            <p class="eyebrow" id="planBreadcrumb">PLANO DE ESTIBA</p>
                            <div class="title-row">
                                <h1 id="planTitle">Selecciona una cámara</h1>
                                <span class="version-chip" id="planVersion">v0</span>
                            </div>
                            <p class="plan-subtitle" id="planSubtitle">El plano mostrará la ubicación actual de cada folio.</p>
                        </div>
                        <div class="occupancy-block">
                            <span>OCUPACIÓN</span>
                            <strong id="occupancyValue">0%</strong>
                            <div class="progress-track"><i id="occupancyBar"></i></div>
                            <small id="occupancyDetail">0 de 0 posiciones</small>
                        </div>
                    </div>

                    <div class="lock-banner is-hidden" id="lockBanner" role="status">
                        <span class="lock-icon" aria-hidden="true">⌁</span>
                        <div>
                            <strong id="lockTitle">Cámara en uso</strong>
                            <span id="lockMessage">Puedes consultar el plano, pero no modificarlo.</span>
                        </div>
                    </div>

                    <div class="map-toolbar">
                        <div class="map-legend">
                            <span><i class="legend-box legend-box--free"></i> Libre</span>
                            <span><i class="legend-box legend-box--pallet"></i> Pallet</span>
                            <span><i class="legend-box legend-box--saldo"></i> Saldo</span>
                            <span><i class="legend-box legend-box--blocked"></i> Bloqueada</span>
                        </div>
                        <span class="map-hint">Toca una posición para ver su detalle</span>
                    </div>

                    <div class="position-map" id="positionMap">
                        <div class="empty-state">
                            <div class="empty-state__icon">▦</div>
                            <strong>Sin cámara seleccionada</strong>
                            <span>Elige una cámara desde el panel izquierdo.</span>
                        </div>
                    </div>
                </section>

                <aside class="action-panel panel">
                    <div class="panel-heading panel-heading--actions">
                        <div>
                            <p class="eyebrow">OPERACIÓN</p>
                            <h2>Acciones rápidas</h2>
                        </div>
                    </div>

                    <div class="selection-card" id="selectionCard">
                        <span class="selection-card__label">POSICIÓN SELECCIONADA</span>
                        <strong id="selectedPositionLabel">Ninguna</strong>
                        <small id="selectedPositionState">Toca una posición del plano</small>
                    </div>

                    <div class="folio-card is-hidden" id="folioCard">
                        <div class="folio-card__top">
                            <span id="selectedFolioType">PALLET</span>
                            <i class="status-dot status-dot--cyan"></i>
                        </div>
                        <strong id="selectedFolioNumber">—</strong>
                        <dl>
                            <div><dt>Variedad</dt><dd id="selectedFolioVariety">—</dd></div>
                            <div><dt>Calibre</dt><dd id="selectedFolioCaliber">—</dd></div>
                            <div><dt>Condición</dt><dd id="selectedFolioSag">—</dd></div>
                        </dl>
                    </div>

                    <div class="action-stack">
                        <button class="action-button action-button--primary" id="locateButton" type="button" disabled>
                            <span class="action-button__icon">＋</span>
                            <span><strong>Ubicar folio</strong><small>Registrar un bulto nuevo</small></span>
                        </button>
                        <button class="action-button" id="moveButton" type="button" disabled>
                            <span class="action-button__icon">⇄</span>
                            <span><strong>Mover folio</strong><small>Reubicar o cambiar cámara</small></span>
                        </button>
                        <button class="action-button" id="sessionButton" type="button" disabled>
                            <span class="action-button__icon">⌁</span>
                            <span><strong id="sessionButtonTitle">Abrir estiba</strong><small id="sessionButtonSubtitle">Iniciar sesión de edición</small></span>
                        </button>
                        <button class="action-button action-button--quiet" id="refreshPlanButton" type="button" disabled>
                            <span class="action-button__icon">↻</span>
                            <span><strong>Actualizar plano</strong><small>Traer cambios del servidor</small></span>
                        </button>
                    </div>

                    <p class="action-note" id="actionNote">Selecciona una cámara para comenzar.</p>
                </aside>

                <section class="recent-panel panel">
                    <div class="recent-heading">
                        <div>
                            <p class="eyebrow">TRAZABILIDAD</p>
                            <h2>Movimientos recientes</h2>
                        </div>
                        <span id="lastSyncText">Sin sincronizar</span>
                    </div>
                    <div class="recent-list" id="recentList">
                        <div class="recent-empty">Los movimientos de la cámara aparecerán aquí.</div>
                    </div>
                </section>
            </section>
        </main>

        <dialog class="operation-dialog" id="locateDialog">
            <form method="dialog" class="dialog-shell" id="locateForm">
                <div class="dialog-heading">
                    <div>
                        <p class="eyebrow">UBICACIÓN INICIAL</p>
                        <h2>Registrar folio</h2>
                        <p id="locateDestinationText">Selecciona una posición libre.</p>
                    </div>
                    <button class="dialog-close" type="button" data-close-dialog="locateDialog" aria-label="Cerrar">×</button>
                </div>

                <div class="form-grid">
                    <label class="form-field form-field--wide">
                        <span>Número de folio *</span>
                        <input type="text" name="numero_folio" autocomplete="off" autocapitalize="characters" placeholder="Ej. 00498127" required>
                    </label>
                    <label class="form-field">
                        <span>Tipo de bulto *</span>
                        <select name="tipo_bulto" required>
                            <option value="pallet">Pallet completo</option>
                            <option value="saldo">Saldo incompleto</option>
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Condición SAG</span>
                        <select name="condicion_sag_id" id="sagSelect">
                            <option value="">Sin especificar</option>
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Variedad</span>
                        <input type="text" name="variedad" autocomplete="off" placeholder="Ej. Santina">
                    </label>
                    <label class="form-field">
                        <span>Calibre</span>
                        <input type="text" name="calibre" autocomplete="off" placeholder="Ej. 2J">
                    </label>
                    <label class="form-field">
                        <span>Marca</span>
                        <input type="text" name="marca" autocomplete="off">
                    </label>
                    <label class="form-field">
                        <span>Exportadora</span>
                        <input type="text" name="exportadora" autocomplete="off">
                    </label>
                </div>
                <p class="form-error" id="locateError" role="alert"></p>
                <div class="dialog-actions">
                    <button class="button button--secondary" type="button" data-close-dialog="locateDialog">Cancelar</button>
                    <button class="button button--primary" type="submit">Confirmar ubicación</button>
                </div>
            </form>
        </dialog>

        <dialog class="operation-dialog operation-dialog--move" id="moveDialog">
            <form method="dialog" class="dialog-shell" id="moveForm">
                <div class="dialog-heading">
                    <div>
                        <p class="eyebrow">MOVIMIENTO DE FOLIO</p>
                        <h2 id="moveDialogTitle">Mover folio</h2>
                        <p id="moveOriginText">Selecciona la nueva posición.</p>
                    </div>
                    <button class="dialog-close" type="button" data-close-dialog="moveDialog" aria-label="Cerrar">×</button>
                </div>

                <label class="form-field">
                    <span>Cámara de destino</span>
                    <select name="camara_destino_id" id="moveCameraSelect" required></select>
                </label>

                <div class="move-destination">
                    <div class="move-destination__heading">
                        <span>PLANO DE DESTINO · POSICIONES LIBRES</span>
                        <small id="moveDestinationHint">Selecciona una posición</small>
                    </div>
                    <div class="destination-grid" id="moveDestinationGrid"></div>
                </div>

                <p class="form-error" id="moveError" role="alert"></p>
                <div class="dialog-actions">
                    <button class="button button--secondary" type="button" data-close-dialog="moveDialog">Cancelar</button>
                    <button class="button button--primary" id="confirmMoveButton" type="submit" disabled>Confirmar movimiento</button>
                </div>
            </form>
        </dialog>

        <div class="loading-overlay is-hidden" id="loadingOverlay" aria-live="polite">
            <span class="spinner"></span>
            <strong id="loadingText">Sincronizando…</strong>
        </div>
        <div class="toast-region" id="toastRegion" aria-live="polite"></div>
    </body>
</html>
