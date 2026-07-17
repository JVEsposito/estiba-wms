<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministracionAccesoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_administrador_crea_usuarios_y_tablets_autorizadas(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/usuarios', [
                'nombre' => '  Camila Operadora  ',
                'email' => '  CAMILA@EMPRESA.CL  ',
                'rol' => RolUsuario::CamareroFrio->value,
                'password' => 'Temporal2026',
                'password_confirmation' => 'Temporal2026',
            ])
            ->assertCreated()
            ->assertJsonPath('usuario.nombre', 'Camila Operadora')
            ->assertJsonPath('usuario.email', 'camila@empresa.cl')
            ->assertJsonPath('usuario.rol', RolUsuario::CamareroFrio->value)
            ->assertJsonPath('usuario.activo', true);

        $usuario = User::query()->where('email', 'camila@empresa.cl')->firstOrFail();
        $this->assertTrue(Hash::check('Temporal2026', $usuario->password));

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/dispositivos', [
                'codigo' => '  tablet-02  ',
                'nombre' => '  Tablet cámara norte  ',
            ])
            ->assertCreated()
            ->assertJsonPath('dispositivo.codigo', 'TABLET-02')
            ->assertJsonPath('dispositivo.nombre', 'Tablet cámara norte')
            ->assertJsonPath('dispositivo.plataforma', 'android')
            ->assertJsonPath('dispositivo.activo', true);

        $this->assertDatabaseHas('dispositivos', [
            'codigo' => 'TABLET-02',
            'nombre' => 'Tablet cámara norte',
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/administracion/accesos')
            ->assertOk()
            ->assertJsonCount(2, 'usuarios')
            ->assertJsonCount(1, 'dispositivos');
    }

    public function test_un_usuario_no_administrador_no_puede_gestionar_accesos(): void
    {
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/administracion/accesos')
            ->assertForbidden();

        $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/administracion/usuarios', [
                'nombre' => 'Usuario no autorizado',
                'email' => 'sin-permiso@empresa.cl',
                'rol' => RolUsuario::CamareroFrio->value,
                'password' => 'Temporal2026',
                'password_confirmation' => 'Temporal2026',
            ])
            ->assertForbidden();

        $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/administracion/dispositivos', [
                'codigo' => 'TABLET-99',
                'nombre' => 'Tablet no autorizada',
            ])
            ->assertForbidden();
    }

    public function test_valida_duplicados_formato_y_contrasena(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
            'email' => 'existente@empresa.cl',
        ]);
        Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet existente',
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/usuarios', [
                'nombre' => 'Duplicado',
                'email' => 'EXISTENTE@EMPRESA.CL',
                'rol' => RolUsuario::CamareroFrio->value,
                'password' => 'solo-letras',
                'password_confirmation' => 'no-coincide',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/dispositivos', [
                'codigo' => 'tablet-01',
                'nombre' => 'Duplicada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo']);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/dispositivos', [
                'codigo' => 'tablet con espacios',
                'nombre' => 'Formato inválido',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo']);
    }

    public function test_informa_claramente_una_contrasena_demasiado_corta(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/usuarios', [
                'nombre' => 'Camarero de prueba',
                'email' => 'camarero@empresa.cl',
                'rol' => RolUsuario::CamareroFrio->value,
                'password' => 'Abc12',
                'password_confirmation' => 'Abc12',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.password.0',
                'La contraseña debe tener al menos 10 caracteres.',
            );
    }

    public function test_el_acceso_de_oficina_informa_el_permiso_administrativo(): void
    {
        User::factory()->create([
            'email' => 'admin@empresa.cl',
            'password' => 'password',
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->postJson('/api/acceso-oficina', [
            'email' => 'admin@empresa.cl',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.puede_administrar_accesos', true);
    }
}
