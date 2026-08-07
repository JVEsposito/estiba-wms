@props([
    'domain',
    'office',
    'context' => 'OPERACIÓN DE OFICINA',
    'icon' => '◆',
])

@php
    $offices = [
        'materia-prima' => [
            ['key' => 'romana', 'module' => 'materia-prima.romana', 'label' => 'Romana', 'href' => '/oficina/romana', 'permissions' => ['puede_consultar_romana']],
            ['key' => 'digitacion', 'module' => 'materia-prima.digitacion', 'label' => 'Digitación de Lotes', 'href' => '/oficina/materia-prima', 'permissions' => ['puede_consultar_materia_prima']],
            ['key' => 'fruta-proceso', 'module' => 'materia-prima.fruta-proceso', 'label' => 'Fruta a Proceso', 'href' => '/oficina/materia-prima/fruta-a-proceso', 'permissions' => ['puede_consultar_fruta_proceso']],
            ['key' => 'existencias-mp', 'module' => 'materia-prima.digitacion', 'label' => 'Existencias MP', 'href' => '/oficina/materia-prima/existencias', 'permissions' => ['puede_consultar_materia_prima']],
            ['key' => 'envases', 'module' => 'materia-prima.cuenta-envases', 'label' => 'Cuenta Envases', 'href' => '/oficina/envases/cuenta-corriente', 'permissions' => ['puede_consultar_cuenta_envases']],
            ['key' => 'despacho-envases', 'module' => 'materia-prima.despacho-envases', 'label' => 'Despacho Envases', 'href' => '/oficina/envases/despachos', 'permissions' => ['puede_consultar_cuenta_envases', 'puede_gestionar_despacho_envases', 'puede_anular_despacho_envases']],
        ],
        'frigorifico' => [
            ['key' => 'validacion', 'module' => 'frigorifico.validacion', 'label' => 'Validación', 'href' => '/oficina/validacion', 'permissions' => ['puede_consultar_validaciones_pallet']],
            ['key' => 'repaletizajes', 'module' => 'frigorifico.validacion', 'label' => 'Repaletizajes', 'href' => '/oficina/validacion/repaletizajes', 'permissions' => ['puede_consultar_validaciones_pallet']],
            ['key' => 'anulaciones-validacion', 'module' => 'frigorifico.validacion', 'label' => 'Anulaciones', 'href' => '/oficina/validacion/anulaciones', 'permissions' => ['puede_consultar_validaciones_pallet']],
            ['key' => 'catalogo-validacion', 'module' => 'frigorifico.catalogos', 'label' => 'Catálogos PT', 'href' => '/oficina/validacion/catalogo', 'permissions' => ['puede_consultar_catalogos_validacion']],
            ['key' => 'prefrio', 'module' => 'frigorifico.prefrio', 'label' => 'Prefrío', 'href' => '/oficina/prefrio', 'permissions' => ['puede_consultar_prefrio']],
            ['key' => 'camaras', 'module' => 'frigorifico.camaras', 'label' => 'Cámaras', 'href' => '/oficina/frigorifico/camaras', 'permissions' => ['ambito_camaras_productos']],
            ['key' => 'cargas', 'module' => 'frigorifico.cargas', 'label' => 'Cargas & Despachos', 'href' => '/oficina/cargas', 'permissions' => ['puede_consultar_cargas']],
            ['key' => 'existencias-pt', 'module' => 'frigorifico.cargas', 'label' => 'Existencias PT', 'href' => '/oficina/frigorifico/existencias', 'permissions' => ['puede_consultar_cargas']],
        ],
        'materiales' => [
            ['key' => 'resumen', 'module' => 'materiales.resumen', 'label' => 'Resumen', 'href' => '/oficina/materiales', 'permissions' => ['puede_consultar_despachos_materiales']],
            ['key' => 'catalogos', 'module' => 'materiales.catalogos', 'label' => 'Catálogos', 'href' => '/oficina/materiales/catalogos', 'permissions' => ['puede_consultar_despachos_materiales']],
            ['key' => 'recepciones', 'module' => 'materiales.etiquetas', 'label' => 'Recepciones', 'href' => '/oficina/materiales/recepciones', 'permissions' => ['puede_consultar_recepciones_materiales']],
            ['key' => 'recepcion', 'module' => 'materiales.etiquetas', 'label' => 'Etiquetas', 'href' => '/oficina/materiales/recepcion', 'permissions' => ['puede_consultar_recepciones_materiales', 'puede_imprimir_etiquetas_materiales']],
            ['key' => 'inventario', 'module' => 'materiales.inventario', 'label' => 'Inventario', 'href' => '/oficina/materiales/inventario', 'permissions' => ['puede_consultar_despachos_materiales']],
            ['key' => 'custodia', 'module' => 'materiales.inventario', 'label' => 'Custodia', 'href' => '/oficina/materiales/almacenes', 'permissions' => ['puede_consultar_despachos_materiales']],
            ['key' => 'despachos', 'module' => 'materiales.despachos', 'label' => 'Despachos', 'href' => '/oficina/materiales/despachos', 'permissions' => ['puede_consultar_despachos_materiales']],
            ['key' => 'recetas', 'module' => 'materiales.recetas', 'label' => 'Recetas', 'href' => '/oficina/materiales/recetas', 'permissions' => ['puede_consultar_transformaciones_materiales']],
            ['key' => 'ordenes', 'module' => 'materiales.ordenes', 'label' => 'Órdenes', 'href' => '/oficina/materiales/ordenes', 'permissions' => ['puede_consultar_transformaciones_materiales']],
            ['key' => 'exportaciones', 'module' => 'materiales.exportaciones', 'label' => 'Existencias', 'href' => '/oficina/materiales/exportaciones', 'permissions' => ['puede_consultar_despachos_materiales']],
        ],
        'administracion' => [
            ['key' => 'panel', 'module' => 'gerencia.panel', 'label' => 'Panel Gerencial', 'href' => '/oficina/gerencia', 'permissions' => ['puede_consultar_panel_gerencial']],
            ['key' => 'accesos', 'module' => 'administracion.accesos', 'label' => 'Accesos & Temporadas', 'href' => '/oficina/accesos', 'permissions' => ['puede_consultar_accesos']],
            ['key' => 'configuracion-camaras', 'module' => 'administracion.camaras', 'label' => 'Configuración de cámaras', 'href' => '/oficina/administracion/camaras', 'permissions' => ['puede_consultar_configuracion_camaras']],
        ],
        'consultas' => [
            ['key' => 'busqueda', 'module' => 'consultas.busqueda', 'label' => 'Búsqueda Operacional', 'href' => '/oficina/consultas', 'permissions' => ['puede_consultar_oficina_consultas']],
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

<header class="office-topbar office-domain-topbar" data-active-domain="{{ $domain }}" data-active-office="{{ $office }}">
    <div class="brand-lockup">
        <span class="office-brand-mark" aria-hidden="true">
            <svg viewBox="0 0 40 40" role="img">
                <path d="M20 3 34 10.5 20 18 6 10.5 20 3Z" />
                <path d="M6 10.5V27L20 35V18L6 10.5Z" />
                <path d="M34 10.5V27L20 35V18L34 10.5Z" />
                <path d="m13 14.3 14-7.5" />
            </svg>
        </span>
        <span><strong>ESTIBA</strong><small>SUITE DE GESTIÓN WMS · {{ $context }}</small></span>
    </div>

    <nav class="office-domain-navigation" aria-label="Macromódulos del sistema">
        @foreach ($domains as $domainKey => $definition)
            <a
                class="office-domain-link {{ $domain === $domainKey ? 'is-active' : '' }}"
                data-domain-key="{{ $domainKey }}"
                data-navigation-targets="{{ json_encode($definition['targets'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                href="{{ $definition['href'] }}"
            >
                <span aria-hidden="true">{{ $definition['icon'] }}</span>
                <strong>{{ $definition['label'] }}</strong>
            </a>
        @endforeach
    </nav>

    <div class="identity">
        <span class="identity__avatar" id="officeInitials">OF</span>
        <span><strong id="officeUserName">Usuario</strong><small id="officeUserRole">Oficina</small></span>
        <button id="officeLogoutButton" type="button">Cerrar sesión</button>
    </div>

    <div class="office-navigation-legacy" aria-hidden="true">
        <a id="officeManagementNav" href="/oficina/gerencia" tabindex="-1"></a>
        <a id="officeRomanaNav" href="/oficina/romana" tabindex="-1"></a>
        <a id="officeRawMaterialNav" href="/oficina/materia-prima" tabindex="-1"></a>
        <a id="officeCamerasNav" href="/oficina/frigorifico/camaras" tabindex="-1"></a>
        <a id="officeLoadsNav" href="/oficina/cargas" tabindex="-1"></a>
        <a id="officeMaterialsNav" href="/oficina/materiales" tabindex="-1"></a>
        <a id="officePrefrioNav" href="/oficina/prefrio" tabindex="-1"></a>
        <a id="officeAccessesNav" href="/oficina/accesos" tabindex="-1"></a>
    </div>
</header>

<nav class="office-subnavigation" aria-label="Oficinas de {{ $activeDomain['label'] }}">
    <div class="office-subnavigation__heading">
        <span>MACROMÓDULO</span>
        <strong>{{ $activeDomain['label'] }}</strong>
    </div>
    <div class="office-subnavigation__links">
        @foreach ($activeOffices as $definition)
            <a
                class="{{ $office === $definition['key'] ? 'is-active' : '' }}"
                data-office-key="{{ $definition['key'] }}"
                data-office-domain="{{ $domain }}"
                data-navigation-permissions="{{ implode(',', $definition['permissions']) }}"
                data-navigation-module="{{ $definition['module'] }}"
                href="{{ $definition['href'] }}"
            >{{ $definition['label'] }}</a>
        @endforeach
    </div>
</nav>

@once
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/office-corporate.css', 'resources/js/office-navigation.js'])
    @endif
@endonce
