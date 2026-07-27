from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def replace_once(path: str, old: str, new: str) -> None:
    target = ROOT / path
    content = target.read_text(encoding="utf-8")
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{path}: se esperaba 1 coincidencia y se encontraron {count}")
    target.write_text(content.replace(old, new, 1), encoding="utf-8")


def replace_header(path: str, component: str) -> None:
    target = ROOT / path
    content = target.read_text(encoding="utf-8")
    pattern = re.compile(r"\n\s*<header class=\"office-topbar[^\"]*\">.*?</header>\n", re.S)
    replacement = f"\n            {component}\n"
    updated, count = pattern.subn(replacement, content, count=1)
    if count != 1:
        raise RuntimeError(f"{path}: no fue posible reemplazar la cabecera de oficina")
    target.write_text(updated, encoding="utf-8")


component = r'''@props([
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
    @vite('resources/js/office-navigation.js')
@endonce
'''
write('resources/views/components/office/navigation.blade.php', component)

navigation_js = r'''const tokenKey = 'estiba_wms_office_token';
const identityKey = 'estiba_wms_office_identity';
const lastDomainKey = 'estiba_wms_last_domain';

function readIdentity() {
    try {
        return JSON.parse(localStorage.getItem(identityKey) || 'null');
    } catch {
        return null;
    }
}

function capabilities(identity) {
    return {
        ...(identity?.capacidades || {}),
        ...(identity || {}),
    };
}

function can(identity, permission) {
    if (!identity || !permission) return false;
    if (identity.rol === 'administrador') return true;

    const values = capabilities(identity);
    if (permission === 'ambito_camaras') {
        return Boolean(values.puede_administrar_camaras)
            || (values.ambito_camaras && values.ambito_camaras !== 'ninguno');
    }
    if (permission === 'puede_consultar_existencias') {
        return Boolean(
            values.puede_consultar_despachos_materiales
            || values.puede_consultar_materia_prima
            || values.puede_consultar_cargas
            || values.puede_consultar_panel_gerencial,
        );
    }

    return values[permission] === true;
}

function refreshNavigation() {
    const identity = readIdentity();
    const hasSession = Boolean(localStorage.getItem(tokenKey) && identity);

    document.querySelectorAll('[data-navigation-permission]').forEach((link) => {
        link.classList.toggle('is-hidden', !hasSession || !can(identity, link.dataset.navigationPermission));
    });

    document.querySelectorAll('[data-navigation-permissions]').forEach((link) => {
        const permissions = String(link.dataset.navigationPermissions || '')
            .split(',')
            .map((value) => value.trim())
            .filter(Boolean);
        const visible = hasSession && permissions.some((permission) => can(identity, permission));
        link.classList.toggle('is-hidden', !visible);
    });

    const activeDomain = document.querySelector('.office-domain-topbar')?.dataset.activeDomain;
    if (hasSession && activeDomain) localStorage.setItem(lastDomainKey, activeDomain);
}

function scrollToOfficeTarget() {
    if (!location.hash) return;
    const id = decodeURIComponent(location.hash.slice(1));
    let attempts = 0;
    const timer = window.setInterval(() => {
        attempts += 1;
        const target = document.getElementById(id);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.classList.add('office-navigation-target');
            window.setTimeout(() => target.classList.remove('office-navigation-target'), 1800);
            window.clearInterval(timer);
        } else if (attempts >= 30) {
            window.clearInterval(timer);
        }
    }, 150);
}

function observeApplication() {
    const app = document.getElementById('officeApp');
    if (!app) return;
    new MutationObserver(() => refreshNavigation()).observe(app, {
        attributes: true,
        attributeFilter: ['class'],
    });
}

document.addEventListener('DOMContentLoaded', () => {
    refreshNavigation();
    observeApplication();
    scrollToOfficeTarget();

    document.querySelectorAll('[data-domain-key]').forEach((link) => {
        link.addEventListener('click', () => localStorage.setItem(lastDomainKey, link.dataset.domainKey));
    });
});

window.addEventListener('storage', refreshNavigation);
window.EstibaOfficeNavigation = { refresh: refreshNavigation };
'''
write('resources/js/office-navigation.js', navigation_js)

headers = {
    'resources/views/office/raw-material.blade.php': '<x-office.navigation domain="materia-prima" office="digitacion" context="MATERIA PRIMA" icon="◫" />',
    'resources/views/office/weighbridge.blade.php': '<x-office.navigation domain="materia-prima" office="romana" context="MATERIA PRIMA" icon="⚖" />',
    'resources/views/office/container-accounts.blade.php': '<x-office.navigation domain="materia-prima" office="envases" context="MATERIA PRIMA" icon="▣" />',
    'resources/views/office/container-dispatches.blade.php': '<x-office.navigation domain="materia-prima" office="envases" context="MATERIA PRIMA" icon="▣" />',
    'resources/views/office/validation.blade.php': '<x-office.navigation domain="frigorifico" office="validacion" context="FRIGORÍFICO · PT" icon="✓" />',
    'resources/views/office/validation-catalog.blade.php': '<x-office.navigation domain="frigorifico" office="validacion" context="FRIGORÍFICO · PT" icon="✓" />',
    'resources/views/office/precooling.blade.php': '<x-office.navigation domain="frigorifico" office="prefrio" context="FRIGORÍFICO · PT" icon="❄" />',
    'resources/views/office/loads.blade.php': '<x-office.navigation domain="frigorifico" office="cargas" context="FRIGORÍFICO · PT" icon="▤" />',
    'resources/views/office/cameras.blade.php': '<x-office.navigation :domain="$navigationDomain ?? \'frigorifico\'" :office="$navigationOffice ?? \'camaras\'" context="CÁMARAS" icon="❄" />',
    'resources/views/office/materials.blade.php': '<x-office.navigation domain="materiales" :office="$navigationOffice ?? \'recepcion\'" context="MATERIALES" icon="▦" />',
    'resources/views/office/inventory-exports.blade.php': '<x-office.navigation domain="materiales" office="existencias" context="MATERIALES" icon="⇩" />',
    'resources/views/office/management.blade.php': '<x-office.navigation domain="administracion" office="panel" context="ADMINISTRACIÓN & GERENCIA" icon="◆" />',
    'resources/views/office/accesses.blade.php': '<x-office.navigation domain="administracion" office="accesos" context="ADMINISTRACIÓN & GERENCIA" icon="⚙" />',
}
for path, invocation in headers.items():
    replace_header(path, invocation)

replace_once(
    'resources/views/office/cameras.blade.php',
    '<main class="office-app is-hidden" id="officeApp">',
    '<main class="office-app is-hidden" id="officeApp" data-camera-mode="{{ $cameraMode ?? \'operacion\' }}">',
)
replace_once(
    'resources/views/office/cameras.blade.php',
    'Despachadores pueden revisar ocupación y disponibilidad. Supervisores y administradores conservan sus herramientas de configuración.',
    'Las áreas consultan ocupación y disponibilidad. La creación, estructura y desactivación de cámaras pertenecen exclusivamente a Administración.',
)
replace_once(
    'resources/views/office/cameras.blade.php',
    'Disponible para despachadores, supervisores y administradores.',
    'La consulta está disponible según el área; la configuración requiere perfil administrador.',
)
replace_once(
    'resources/views/office/materials.blade.php',
    '<div class="materials-operation-grid">',
    '<div class="materials-operation-grid" id="materialsOperationGrid">',
)

css_path = ROOT / 'resources/css/office.css'
css = css_path.read_text(encoding='utf-8')
css += r'''

/* Navegación por macromódulos y oficinas */
.office-domain-topbar {
    grid-template-columns: minmax(220px, .7fr) minmax(620px, 1.6fr) minmax(280px, .7fr);
    position: sticky;
    top: 0;
    z-index: 40;
}
.office-domain-navigation {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(145px, 1fr));
    width: 100%;
    gap: 3px !important;
    padding: 4px !important;
}
.office-domain-navigation .office-domain-link {
    display: flex;
    min-height: 48px;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 8px 12px;
    text-align: center;
}
.office-domain-navigation .office-domain-link > span { padding: 0; color: var(--cyan); font-size: 1rem; }
.office-domain-navigation .office-domain-link > strong { font-size: .76rem; }
.office-domain-navigation .office-domain-link.is-active {
    border-bottom: 2px solid var(--cyan);
    background: linear-gradient(180deg, rgba(22, 201, 194, .15), rgba(22, 201, 194, .06));
    color: var(--cyan-light);
}
.office-subnavigation {
    display: flex;
    min-height: 60px;
    align-items: center;
    gap: 22px;
    padding: 8px 24px;
    border-bottom: 1px solid var(--line);
    background: rgba(6, 19, 27, .96);
}
.office-subnavigation__heading { display: grid; min-width: 175px; gap: 2px; }
.office-subnavigation__heading span { color: var(--muted); font-size: .58rem; font-weight: 900; letter-spacing: .14em; }
.office-subnavigation__heading strong { color: var(--text); font-size: .82rem; }
.office-subnavigation__links {
    display: flex;
    flex: 1;
    gap: 5px;
    overflow-x: auto;
    padding: 4px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: var(--panel);
}
.office-subnavigation__links a {
    min-width: max-content;
    padding: 9px 16px;
    border-radius: 7px;
    color: var(--muted);
    font-size: .76rem;
    font-weight: 850;
    text-decoration: none;
}
.office-subnavigation__links a.is-active { background: var(--soft); color: var(--cyan-light); }
.office-navigation-legacy { display: none !important; }
.office-navigation-target { animation: office-navigation-highlight 1.8s ease; }
@keyframes office-navigation-highlight {
    0%, 100% { box-shadow: inherit; }
    35% { box-shadow: 0 0 0 3px rgba(85, 229, 223, .45), 0 0 34px rgba(22, 201, 194, .22); }
}
.office-app[data-camera-mode="operacion"] .configuration-module-tabs,
.office-app[data-camera-mode="operacion"] .configuration { display: none !important; }
.office-app[data-camera-mode="operacion"] .office-workspace { grid-template-columns: 1fr; }
.office-app[data-camera-mode="operacion"] .camera-catalog { min-height: auto; }
.office-app[data-camera-mode="operacion"] .camera-catalog__list { grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
@media (max-width: 1250px) {
    .office-domain-topbar { grid-template-columns: minmax(190px, .55fr) minmax(520px, 1.45fr) minmax(240px, .65fr); gap: 12px; padding-inline: 14px; }
    .office-domain-navigation .office-domain-link > strong { font-size: .68rem; }
    .identity > span:nth-child(2) { display: none; }
}
@media (max-width: 900px) {
    .office-domain-topbar { position: static; grid-template-columns: 1fr auto; }
    .office-domain-navigation { grid-column: 1 / -1; grid-row: 2; overflow-x: auto; grid-template-columns: repeat(4, minmax(170px, 1fr)); }
    .office-subnavigation { align-items: stretch; flex-direction: column; gap: 8px; padding: 10px 14px; }
    .office-subnavigation__heading { min-width: 0; }
}
'''
css_path.write_text(css, encoding='utf-8')

replace_once(
    'vite.config.js',
    "                'resources/css/office.css',\n",
    "                'resources/css/office.css',\n                'resources/js/office-navigation.js',\n",
)

routes = r'''<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/oficina/camaras', '/oficina/frigorifico/camaras');
Route::view('/oficina/frigorifico/camaras', 'office.cameras', [
    'navigationDomain' => 'frigorifico',
    'navigationOffice' => 'camaras',
    'cameraMode' => 'operacion',
]);
Route::view('/oficina/administracion/camaras', 'office.cameras', [
    'navigationDomain' => 'administracion',
    'navigationOffice' => 'configuracion-camaras',
    'cameraMode' => 'configuracion',
]);
Route::view('/oficina/cargas', 'office.loads');
Route::view('/oficina/accesos', 'office.accesses');
Route::view('/oficina/materiales', 'office.materials', ['navigationOffice' => 'recepcion']);
Route::view('/oficina/materiales/recepcion', 'office.materials', ['navigationOffice' => 'recepcion']);
Route::view('/oficina/materiales/transformacion', 'office.materials', ['navigationOffice' => 'transformacion']);
Route::redirect('/oficina/materiales/existencias', '/oficina/existencias');
Route::view('/oficina/validacion', 'office.validation');
Route::view('/oficina/validacion/catalogo', 'office.validation-catalog');
Route::view('/oficina/prefrio', 'office.precooling');
Route::view('/oficina/gerencia', 'office.management');
Route::view('/oficina/existencias', 'office.inventory-exports');
Route::view('/oficina/romana', 'office.weighbridge');
Route::view('/oficina/envases/cuenta-corriente', 'office.container-accounts');
Route::view('/oficina/envases/despachos', 'office.container-dispatches');
Route::view('/oficina/materia-prima', 'office.raw-material');
Route::view('/oficina/materia-prima/lotes', 'office.raw-material');
Route::redirect('/oficina/materia-prima/romana', '/oficina/romana');
Route::redirect('/oficina/materia-prima/envases', '/oficina/envases/cuenta-corriente');
'''
write('routes/web.php', routes)

service_path = ROOT / 'app/Services/Autorizacion/AlcanceOperacionalUsuario.php'
service = service_path.read_text(encoding='utf-8')
pattern = re.compile(
    r"    public function puedeCrearCamara\(User \$usuario, ContenidoCamara \$contenido\): bool\n    \{\n        return \$this->puedeSupervisarCamara\(\$usuario, \$contenido\);\n    \}",
)
service, count = pattern.subn(
    "    public function puedeCrearCamara(User $usuario, ContenidoCamara $contenido): bool\n    {\n        return $this->puedeAdministrarCamaras($usuario);\n    }",
    service,
    count=1,
)
if count != 1:
    raise RuntimeError('No fue posible restringir la creación de cámaras al administrador.')
service_path.write_text(service, encoding='utf-8')

unit_test = r'''<?php

namespace Tests\Unit;

use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutorizacionCreacionCamarasTest extends TestCase
{
    #[DataProvider('contenidos')]
    public function test_solo_el_administrador_puede_crear_camaras(ContenidoCamara $contenido): void
    {
        $alcance = app(AlcanceOperacionalUsuario::class);
        $administrador = (new User)->forceFill([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $supervisorFrio = (new User)->forceFill([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $supervisorMateriales = (new User)->forceFill([
            'rol' => RolUsuario::SupervisorMateriales,
            'activo' => true,
        ]);

        $this->assertTrue($alcance->puedeCrearCamara($administrador, $contenido));
        $this->assertFalse($alcance->puedeCrearCamara($supervisorFrio, $contenido));
        $this->assertFalse($alcance->puedeCrearCamara($supervisorMateriales, $contenido));
    }

    public static function contenidos(): array
    {
        return collect(ContenidoCamara::cases())
            ->mapWithKeys(fn (ContenidoCamara $contenido): array => [
                $contenido->value => [$contenido],
            ])
            ->all();
    }
}
'''
write('tests/Unit/AutorizacionCreacionCamarasTest.php', unit_test)

feature_test = r'''<?php

namespace Tests\Feature;

use Tests\TestCase;

class NavegacionOficinasPorDominioTest extends TestCase
{
    public function test_materia_prima_muestra_solo_sus_oficinas_secundarias(): void
    {
        $this->get('/oficina/materia-prima')
            ->assertOk()
            ->assertSee('data-active-domain="materia-prima"', false)
            ->assertSee('Romana')
            ->assertSee('Digitación de Lotes')
            ->assertSee('Cuenta Envases')
            ->assertDontSee('Cargas &amp; Despachos', false);
    }

    public function test_frigorifico_muestra_sus_oficinas_y_no_la_configuracion_administrativa(): void
    {
        $this->get('/oficina/frigorifico/camaras')
            ->assertOk()
            ->assertSee('data-active-domain="frigorifico"', false)
            ->assertSee('Validación')
            ->assertSee('Prefrío')
            ->assertSee('Cargas &amp; Despachos', false)
            ->assertDontSee('Configuración de cámaras');
    }

    public function test_configuracion_de_camaras_pertenece_a_administracion(): void
    {
        $this->get('/oficina/administracion/camaras')
            ->assertOk()
            ->assertSee('data-active-domain="administracion"', false)
            ->assertSee('Configuración de cámaras')
            ->assertSee('data-camera-mode="configuracion"', false);

        $this->get('/oficina/camaras')
            ->assertRedirect('/oficina/frigorifico/camaras');
    }
}
'''
write('tests/Feature/NavegacionOficinasPorDominioTest.php', feature_test)

print('Navegación por dominios aplicada correctamente.')
