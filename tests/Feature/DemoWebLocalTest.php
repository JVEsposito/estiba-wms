<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoWebLocalTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_un_administrador_puede_autorizar_la_demo_sin_modificar_temporadas(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $temporadasAntes = Temporada::query()->orderBy('id')->get([
            'id',
            'codigo',
            'activa',
        ])->toArray();

        $respuesta = $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/demo/autorizar')
            ->assertOk()
            ->assertJsonPath('data.autorizado', true)
            ->assertJsonPath('data.version_escenario', 1)
            ->assertJsonPath('data.administrador.id', $administrador->id)
            ->assertJsonPath('data.persistencia', 'sesion_navegador');

        $this->assertStringContainsString(
            'no-store',
            (string) $respuesta->headers->get('Cache-Control'),
        );
        $this->assertSame(
            $temporadasAntes,
            Temporada::query()->orderBy('id')->get(['id', 'codigo', 'activa'])->toArray(),
        );

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/demo/autorizar')
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un administrador puede habilitar la versión demo.');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/demo/autorizar')->assertUnauthorized();
    }

    public function test_acceso_de_oficina_expone_la_capacidad_demo_solo_al_administrador(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
            'email' => 'administracion.demo@estiba.local',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/acceso-oficina', [
            'email' => $administrador->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.rol', RolUsuario::Administrador->value)
            ->assertJsonPath('usuario.puede_habilitar_demo', true);
    }

    public function test_publica_la_oficina_demo_y_el_acceso_desde_administracion(): void
    {
        $this->get('/oficina/demo')
            ->assertOk()
            ->assertSee('Habilitar versión demo')
            ->assertSee('data-demo-activation', false)
            ->assertSee('DEMO LOCAL')
            ->assertSee('Resumen gerencial')
            ->assertSee('Trazabilidad');

        $this->get('/oficina/administracion')
            ->assertOk()
            ->assertSee('href="/oficina/demo"', false)
            ->assertSee('puede_habilitar_demo');
    }

    public function test_la_demo_usa_almacenamiento_de_sesion_y_no_consulta_endpoints_productivos(): void
    {
        $controller = file_get_contents(resource_path('js/office-demo.js'));
        $scenario = file_get_contents(resource_path('js/demo/demo-session.js'));

        $this->assertIsString($controller);
        $this->assertIsString($scenario);
        $this->assertStringContainsString('sessionStorage', $controller);
        $this->assertStringContainsString("'/api/demo/autorizar'", $controller);
        $this->assertStringContainsString("'/api/acceso-oficina'", $controller);
        $this->assertStringNotContainsString("'/api/temporadas", $controller);
        $this->assertStringNotContainsString("'/api/gerencia/resumen", $controller);
        $this->assertStringNotContainsString("'/api/camaras", $controller);
        $this->assertStringNotContainsString('localStorage', $scenario);
        $this->assertStringContainsString('pumpsWorking', $scenario);
    }
}
