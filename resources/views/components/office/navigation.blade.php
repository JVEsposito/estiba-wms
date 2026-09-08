@props([
    'domain',
    'office',
    'context' => 'OPERACIÓN DE OFICINA',
    'icon' => '◆',
])

@php
    $offices = [
        'materia-prima' => [
            ['key' => 'resumen', 'module' => '', 'label' => 'Resumen', 'href' => '/oficina/materia-prima', 'permissions' => ['puede_consultar_romana', 'puede_consultar_materia_prima', 'puede_consultar_hidrocooler_materia_prima', 'puede_consultar_fruta_proceso', 'puede_consultar_cuenta_envases', 'puede_gestionar_despacho_envases', 'puede_anular_despacho_envases']],
            ['key' => 'romana', 'module' => 'materia-prima.romana', 'label' => 'Romana', 'href' => '/oficina/romana', 'permissions' => ['puede_consultar_romana']],
            ['key' => 'digitacion', 'module' => 'materia-prima.digitacion', 'label' => 'Digitación de Lotes', 'href' => '/oficina/materia-prima/lotes', 'permissions' => ['puede_consultar_materia_prima']],
            ['key' => 'hidrocooler', 'module' => 'materia-prima.hidrocooler', 'label' => 'Hidrocooler', 'href' => '/oficina/materia-prima/hidrocooler', 'permissions' => ['puede_consultar_hidrocooler_materia_prima']],
            ['key' => 'fruta-proceso', 'module' => 'materia-prima.fruta-proceso', 'label' => 'Fruta a Proceso', 'href' => '/oficina/materia-prima/fruta-a-proceso', 'permissions' => ['puede_consultar_fruta_proceso']],
            ['key' => 'existencias-mp', 'module' => 'materia-prima.digitacion', 'label' => 'Existencias MP', 'href' => '/oficina/materia-prima/existencias', 'permissions' => ['puede_consultar_materia_prima']],
            ['key' => 'envases', 'module' => 'materia-prima.cuenta-envases', 'label' => 'Cuenta Envases', 'href' => '/oficina/envases/cuenta-corriente', 'permissions' => ['puede_consultar_cuenta_envases']],
            ['key' => 'despacho-envases', 'module' => 'materia-prima.despacho-envases', 'label' => 'Despacho Envases', 'href' => '/oficina/envases/despachos', 'permissions' => ['puede_consultar_cuenta_envases', 'puede_gestionar_despacho_envases', 'puede_anular_despacho_envases']],
        ],
        'frigorifico' => [
            ['key' => 'resumen', 'module' => '', 'label' => 'Resumen', 'href' => '/oficina/frigorifico', 'permissions' => ['puede_consultar_validaciones_pallet', 'puede_consultar_inspeccion_sag', 'puede_consultar_prefrio', 'ambito_camaras_productos', 'puede_consultar_catalogo_cargas', 'puede_consultar_cargas']],
            ['key' => 'validacion', 'module' => 'frigorifico.validacion', 'label' => 'Validación', 'href' => '/oficina/validacion', 'permissions' => ['puede_consultar_validaciones_pallet']],
            ['key' => 'repaletizajes', 'module' => 'frigorifico.validacion', 'label' => 'Repaletizajes', 'href' => '/oficina/validacion/repaletizajes', 'permissions' => ['puede_consultar_validaciones_pallet']],
            ['key' => 'anulaciones-validacion', 'module' => 'frigorifico.validacion', 'label' => 'Anulaciones', 'href' => '/oficina/validacion/anulaciones', 'permissions' => ['puede_consultar_validaciones_pallet']],
            ['key' => 'inspeccion-sag', 'module' => 'frigorifico.inspeccion-sag', 'label' => 'Inspección SAG', 'href' => '/oficina/frigorifico/inspeccion-sag', 'permissions' => ['puede_consultar_inspeccion_sag']],
            ['key' => 'prefrio', 'module' => 'frigorifico.prefrio', 'label' => 'Prefrío', 'href' => '/oficina/prefrio', 'permissions' => ['puede_consultar_prefrio']],
            ['key' => 'camaras', 'module' => 'frigorifico.camaras', 'label' => 'Cámaras', 'href' => '/oficina/frigorifico/camaras', 'permissions' => ['ambito_camaras_productos']],
            ['key' => 'discrepancias', 'module' => 'frigorifico.camaras', 'label' => 'Discrepancias', 'href' => '/oficina/frigorifico/discrepancias', 'permissions' => ['puede_supervisar']],
            ['key' => 'embarques', 'module' => 'frigorifico.cargas', 'label' => 'Calendario de embarques', 'href' => '/oficina/frigorifico/calendario-embarques', 'permissions' => ['puede_consultar_catalogo_cargas']],
            ['key' => 'cargas', 'module' => 'frigorifico.cargas', 'label' => 'Cargas & Despachos', 'href' => '/oficina/cargas', 'permissions' => ['puede_consultar_cargas']],
            ['key' => 'existencias-pt', 'module' => 'frigorifico.cargas', 'label' => 'Existencias PT', 'href' => '/oficina/frigorifico/existencias', 'permissions' => ['puede_consultar_cargas']],
        ],
        'materiales' => [
            ['key' => 'resumen', 'module' => 'materiales.resumen', 'label' => 'Resumen', 'href' => '/oficina/materiales', 'permissions' => ['puede_consultar_despachos_materiales']],
            ['key' => 'catalogos', 'module' => 'materiales.catalogos', 'label' => 'Catálogos', 'href' => '/oficina/materiales/catalogos', 'permissions' => ['puede_consultar_despachos_materiales']],
            ['key' => 'recepciones', 'module' => 'materiales.etiquetas', 'label' => 'Recepciones', 'href' => '/oficina/materiales/recepciones', 'permissions' => ['puede_consultar_recepciones_materiales']],
            ['key' => 'recepcion', 'module' => 'materiales.etiquetas', 'label' => 'Etiquetas', 'href' => '/oficina/materiales/recepcion', 'permissions' => ['puede_consultar_recepciones_materiales', 'puede_imprimir_etiquetas_materiales']],
            ['key' => 'inventario', 'module' => 'materiales.inventario', 'label' => 'Inventario BC', 'href' => '/oficina/materiales/inventario', 'permissions' => ['puede_consultar_despachos_materiales']],
            ['key' => 'custodia', 'module' => 'materiales.inventario', 'label' => 'Inventario CC', 'href' => '/oficina/materiales/almacenes', 'permissions' => ['puede_consultar_despachos_materiales']],
            ['key' => 'despachos', 'module' => 'materiales.despachos', 'label' => 'Despachos', 'href' => '/oficina/materiales/despachos', 'permissions' => ['puede_consultar_despachos_materiales']],
            ['key' => 'recetas', 'module' => 'materiales.recetas', 'label' => 'Recetas', 'href' => '/oficina/materiales/recetas', 'permissions' => ['puede_consultar_transformaciones_materiales']],
            ['key' => 'ordenes', 'module' => 'materiales.ordenes', 'label' => 'Órdenes', 'href' => '/oficina/materiales/ordenes', 'permissions' => ['puede_consultar_transformaciones_materiales']],
            ['key' => 'exportaciones', 'module' => 'materiales.exportaciones', 'label' => 'Existencias', 'href' => '/oficina/materiales/exportaciones', 'permissions' => ['puede_consultar_despachos_materiales']],
        ],
        'administracion' => [
            ['key' => 'resumen', 'module' => '', 'label' => 'Resumen', 'href' => '/oficina/administracion', 'permissions' => ['puede_consultar_panel_gerencial', 'puede_consultar_accesos', 'puede_administrar_catalogos_validacion', 'puede_consultar_configuracion_camaras', 'puede_consultar_integridad_operacional']],
            ['key' => 'operacion-ahora', 'module' => 'gerencia.panel', 'label' => 'Operación ahora', 'href' => '/oficina/operacion-ahora', 'permissions' => ['puede_consultar_panel_gerencial']],
            ['key' => 'panel', 'module' => 'gerencia.panel', 'label' => 'Panel Gerencial', 'href' => '/oficina/gerencia', 'permissions' => ['puede_consultar_panel_gerencial']],
            ['key' => 'accesos', 'module' => 'administracion.accesos', 'label' => 'Accesos & Temporadas', 'href' => '/oficina/accesos', 'permissions' => ['puede_consultar_accesos']],
            ['key' => 'maestros-temporada', 'module' => 'administracion.maestros-temporada', 'label' => 'Maestros de temporada', 'href' => '/oficina/administracion/maestros-temporada', 'permissions' => ['puede_administrar_catalogos_validacion']],
            ['key' => 'configuracion-camaras', 'module' => 'administracion.camaras', 'label' => 'Configuración de cámaras', 'href' => '/oficina/administracion/camaras', 'permissions' => ['puede_consultar_configuracion_camaras']],
            ['key' => 'integridad-operacional', 'module' => 'administracion.integridad-operacional', 'label' => 'Salud operacional', 'href' => '/oficina/administracion/integridad-operacional', 'permissions' => ['puede_consultar_integridad_operacional']],
            ['key' => 'demo', 'module' => '', 'label' => 'Demo comercial', 'href' => '/oficina/demo', 'permissions' => ['puede_habilitar_demo']],
        ],
        'consultas' => [
            ['key' => 'resumen', 'module' => '', 'label' => 'Resumen', 'href' => '/oficina/consultas', 'permissions' => ['puede_consultar_oficina_consultas', 'puede_consultar_sag']],
            ['key' => 'busqueda', 'module' => 'consultas.busqueda', 'label' => 'Búsqueda Operacional', 'href' => '/oficina/consultas/busqueda', 'permissions' => ['puede_consultar_oficina_consultas']],
            ['key' => 'sag', 'module' => 'consultas.sag', 'label' => 'Productores SAG / CSG', 'href' => '/oficina/consultas/sag', 'permissions' => ['puede_consultar_sag']],
            ['key' => 'productores', 'module' => 'consultas.productores', 'label' => 'Productores Verificados', 'href' => '/oficina/consultas/productores', 'permissions' => ['puede_consultar_oficina_consultas']],
        ],
    ];

    $domains = [
        'materia-prima' => ['label' => 'Materia Prima', 'icon' => 'MP'],
        'frigorifico' => ['label' => 'Frigorífico (PT)', 'icon' => 'PT'],
        'materiales' => ['label' => 'Materiales', 'icon' => 'MT'],
        'administracion' => ['label' => 'Gerencia & Administración', 'icon' => 'GA'],
        'consultas' => ['label' => 'Consultas', 'icon' => 'CO'],
    ];

    foreach ($domains as $domainKey => &$definition) {
        $definition['targets'] = array_map(
            fn (array $officeDefinition): array => [
                'href' => $officeDefinition['href'],
                'module' => $officeDefinition['module'],
                'permissions' => $officeDefinition['permissions'],
            ],
            $offices[$domainKey] ?? [],
        );
        $definition['href'] = $definition['targets'][0]['href'] ?? '/';
    }
    unset($definition);

    $activeDomain = $domains[$domain] ?? $domains['materia-prima'];
    $activeOffices = $offices[$domain] ?? [];
@endphp

<div class="estiba-ui estiba-office-shell" data-office-shell>
    <a class="estiba-office-skip" href="#officeContent" data-office-skip>Saltar al contenido</a>
    <header class="estiba-office-header" data-office-shell-header data-active-domain="{{ $domain }}" data-active-office="{{ $office }}">
        <div class="estiba-office-brand">
            <button type="button" class="estiba-office-menu" data-office-menu aria-controls="officeSidebar" aria-expanded="true">Menú</button>
            <strong>ESTIBA</strong>
            <span>SISTEMA DE GESTIÓN<small>{{ $activeDomain['label'] }} · {{ collect($activeOffices)->firstWhere('key', $office)['label'] ?? 'Oficina' }}</small></span>
        </div>
        <div class="estiba-office-context">
            <span>Temporada<strong data-office-season>Sin consultar</strong></span>
            <span>Planta<strong data-office-plant>Sin consultar</strong></span>
        </div>
        <div class="estiba-office-identity">
            <span class="estiba-office-avatar" id="officeInitials" aria-hidden="true">OF</span>
            <span><strong id="officeUserName">Usuario</strong><small id="officeUserRole">Oficina</small><small data-office-readonly hidden>Solo consulta</small></span>
            <button id="officeLogoutButton" type="button">Cerrar sesión</button>
        </div>
    </header>

    <aside class="estiba-office-sidebar" id="officeSidebar" aria-label="Navegación de Oficina">
        @php($operationNowDefinition = collect($offices['administracion'])->firstWhere('key', 'operacion-ahora'))
        <nav class="estiba-office-primary" aria-label="Operación en tiempo real">
            <p class="estiba-office-nav-label">OPERACIÓN</p>
            <a class="{{ $office === 'operacion-ahora' ? 'is-active' : '' }}"
                data-office-key="operacion-ahora"
                data-office-domain="administracion"
                data-navigation-permissions="{{ implode(',', $operationNowDefinition['permissions']) }}"
                data-navigation-module="{{ $operationNowDefinition['module'] }}"
                href="{{ $operationNowDefinition['href'] }}"
                @if ($office === 'operacion-ahora') aria-current="page" @endif
            ><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5M5.5 10v10h13V10M9.5 20v-6h5v6" /></svg><strong>Operación ahora</strong></a>
        </nav>
        <nav aria-label="Áreas del sistema">
            <p class="estiba-office-nav-label">MÓDULOS</p>
            @foreach ($domains as $domainKey => $definition)
                <a class="estiba-office-domain {{ $domain === $domainKey && $office !== 'operacion-ahora' ? 'is-active' : '' }}"
                    data-domain-key="{{ $domainKey }}"
                    data-navigation-targets="{{ json_encode($definition['targets'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                    href="{{ $definition['href'] }}"
                    @if ($domain === $domainKey && $office !== 'operacion-ahora') aria-current="true" @endif
                ><span aria-hidden="true">{{ $definition['icon'] }}</span><strong>{{ $definition['label'] }}</strong></a>
            @endforeach
        </nav>
        <nav class="estiba-office-offices" aria-label="Oficinas de {{ $activeDomain['label'] }}" @if ($office === 'operacion-ahora') hidden @endif>
            <p class="estiba-office-nav-label">{{ $activeDomain['label'] }}</p>
            @foreach ($activeOffices as $definition)
                @continue($definition['key'] === 'operacion-ahora')
                <a class="{{ $office === $definition['key'] ? 'is-active' : '' }}"
                    data-office-key="{{ $definition['key'] }}"
                    data-office-domain="{{ $domain }}"
                    data-navigation-permissions="{{ implode(',', $definition['permissions']) }}"
                    data-navigation-module="{{ $definition['module'] }}"
                    href="{{ $definition['href'] }}"
                    @if ($office === $definition['key']) aria-current="page" @endif
                >{{ $definition['label'] }}</a>
            @endforeach
        </nav>
        <details class="estiba-office-preferences">
            <summary>Preferencias de interfaz</summary>
            <label for="officeThemeSelector">Apariencia del contenido</label>
            <select id="officeThemeSelector" aria-label="Tema visual de las oficinas">
                <option value="dark-industrial">Oscuro industrial</option>
                <option value="light-professional">Claro profesional</option>
                <option value="light-natural">Claro natural</option>
                <option value="light-warm">Claro cálido</option>
            </select>
            <button type="button" data-office-context-refresh>Actualizar contexto</button>
            <p data-office-context-status role="status">Contexto sin consultar</p>
        </details>
    </aside>

    <div class="office-navigation-legacy" hidden aria-hidden="true">
        <a id="officeManagementNav" href="/oficina/gerencia" tabindex="-1"></a>
        <a id="officeRomanaNav" href="/oficina/romana" tabindex="-1"></a>
        <a id="officeRawMaterialNav" href="/oficina/materia-prima" tabindex="-1"></a>
        <a id="officeCamerasNav" href="/oficina/frigorifico/camaras" tabindex="-1"></a>
        <a id="officeLoadsNav" href="/oficina/cargas" tabindex="-1"></a>
        <a id="officeMaterialsNav" href="/oficina/materiales" tabindex="-1"></a>
        <a id="officePrefrioNav" href="/oficina/prefrio" tabindex="-1"></a>
        <a id="officeAccessesNav" href="/oficina/accesos" tabindex="-1"></a>
    </div>
</div>

@once
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/office-corporate.css', 'resources/css/office-shell.css', 'resources/js/office-navigation.js'])
    @endif
@endonce
