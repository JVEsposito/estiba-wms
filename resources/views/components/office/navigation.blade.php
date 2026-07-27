@props([
    'domain',
    'office',
    'context' => 'OPERACIÓN DE OFICINA',
    'icon' => '◆',
])

@php
    $domains = [
        'materia-prima' => [
            'label' => 'Materia Prima',
            'icon' => '◫',
            'href' => '/oficina/materia-prima',
            'permissions' => 'puede_consultar_romana,puede_consultar_materia_prima,puede_consultar_cuenta_envases',
        ],
        'frigorifico' => [
            'label' => 'Frigorífico (PT)',
            'icon' => '❄',
            'href' => '/oficina/validacion',
            'permissions' => 'puede_consultar_validaciones_pallet,puede_consultar_prefrio,puede_consultar_cargas,ambito_camaras',
        ],
        'materiales' => [
            'label' => 'Materiales',
            'icon' => '▦',
            'href' => '/oficina/materiales',
            'permissions' => 'puede_consultar_recepciones_materiales,puede_consultar_despachos_materiales,puede_consultar_transformaciones_materiales,puede_consultar_materia_prima,puede_consultar_cargas',
        ],
        'administracion' => [
            'label' => 'Administración & Gerencia',
            'icon' => '⚙',
            'href' => '/oficina/gerencia',
            'permissions' => 'puede_consultar_panel_gerencial,puede_administrar_accesos,puede_administrar_camaras',
        ],
    ];

    $offices = [
        'materia-prima' => [
            ['key' => 'romana', 'label' => 'Romana', 'href' => '/oficina/romana', 'permission' => 'puede_consultar_romana'],
            ['key' => 'digitacion', 'label' => 'Digitación de Lotes', 'href' => '/oficina/materia-prima', 'permission' => 'puede_consultar_materia_prima'],
            ['key' => 'envases', 'label' => 'Cuenta Envases', 'href' => '/oficina/envases/cuenta-corriente', 'permission' => 'puede_consultar_cuenta_envases'],
        ],
        'frigorifico' => [
            ['key' => 'validacion', 'label' => 'Validación', 'href' => '/oficina/validacion', 'permission' => 'puede_consultar_validaciones_pallet'],
            ['key' => 'prefrio', 'label' => 'Prefrío', 'href' => '/oficina/prefrio', 'permission' => 'puede_consultar_prefrio'],
            ['key' => 'camaras', 'label' => 'Cámaras', 'href' => '/oficina/frigorifico/camaras', 'permission' => 'ambito_camaras'],
            ['key' => 'cargas', 'label' => 'Cargas & Despachos', 'href' => '/oficina/cargas', 'permission' => 'puede_consultar_cargas'],
        ],
        'materiales' => [
            ['key' => 'recepcion', 'label' => 'Recepción', 'href' => '/oficina/materiales/recepcion#materialLabelWorkspace', 'permission' => 'puede_consultar_recepciones_materiales'],
            ['key' => 'existencias', 'label' => 'Existencias', 'href' => '/oficina/existencias', 'permission' => 'puede_consultar_existencias'],
            ['key' => 'transformacion', 'label' => 'Transformación', 'href' => '/oficina/materiales/transformacion#materialsRecipesPanel', 'permission' => 'puede_consultar_transformaciones_materiales'],
        ],
        'administracion' => [
            ['key' => 'panel', 'label' => 'Panel Gerencial', 'href' => '/oficina/gerencia', 'permission' => 'puede_consultar_panel_gerencial'],
            ['key' => 'accesos', 'label' => 'Accesos & Temporadas', 'href' => '/oficina/accesos', 'permission' => 'puede_administrar_accesos'],
            ['key' => 'configuracion-camaras', 'label' => 'Configuración de cámaras', 'href' => '/oficina/administracion/camaras', 'permission' => 'puede_administrar_camaras'],
        ],
    ];

    $activeDomain = $domains[$domain] ?? $domains['materia-prima'];
    $activeOffices = $offices[$domain] ?? [];
@endphp

<header class="office-topbar office-domain-topbar" data-active-domain="{{ $domain }}" data-active-office="{{ $office }}">
    <div class="brand-lockup">
        <span class="office-logo office-logo--small" aria-hidden="true">{{ $icon }}</span>
        <span><strong>ESTIBA WMS</strong><small>{{ $context }}</small></span>
    </div>

    <nav class="office-domain-navigation" aria-label="Macromódulos del sistema">
        @foreach ($domains as $domainKey => $definition)
            <a
                class="office-domain-link {{ $domain === $domainKey ? 'is-active' : '' }}"
                data-domain-key="{{ $domainKey }}"
                data-navigation-permissions="{{ $definition['permissions'] }}"
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
                data-navigation-permission="{{ $definition['permission'] }}"
                href="{{ $definition['href'] }}"
            >{{ $definition['label'] }}</a>
        @endforeach
    </div>
</nav>

@once
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/js/office-navigation.js')
    @endif
@endonce
