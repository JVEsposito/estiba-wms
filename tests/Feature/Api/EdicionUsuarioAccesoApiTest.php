<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EdicionUsuarioAccesoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_modifica_datos_rol_password_estado_y_revoca_sesiones(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $usuario = User::factory()->create([
            'name' => 'Operador original',
            'email' => 'original@empresa.cl',
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);
        $usuario->createToken('sesion-anterior', ['oficina']);

        $this->actingAs($administrador, 'sanctum')
            ->putJson("/api/administracion/usuarios/{$usuario->id}", [
                'nombre' => '  Supervisora de materiales  ',
                'email' => '  SUPERVISORA@EMPRESA.CL ',
                'rol' => RolUsuario::SupervisorMateriales->value,
                'activo' => true,
                'password' => 'NuevaClave2026',
                'password_confirmation' => 'NuevaClave2026',
            ])
            ->assertOk()
            ->assertJsonPath('usuario.nombre', 'Supervisora de materiales')
            ->assertJsonPath('usuario.email', 'supervisora@empresa.cl')
            ->assertJsonPath('usuario.rol', RolUsuario::SupervisorMateriales->value)
            ->assertJsonPath('usuario.activo', true)
            ->assertJsonPath('usuario.permisos.puede_consultar_kardex_materiales', true)
            ->assertJsonPath('sesiones_revocadas', true)
            ->assertJsonPath('sesion_actual_invalidada', false);

        $usuario->refresh();
        $this->assertTrue(Hash::check('NuevaClave2026', $usuario->password));
        $this->assertSame(0, $usuario->tokens()->count());

        $this->putJson("/api/administracion/usuarios/{$usuario->id}", [
            'nombre' => $usuario->name,
            'email' => $usuario->email,
            'rol' => $usuario->rol->value,
            'activo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('usuario.activo', false)
            ->assertJsonPath('usuario.permisos.puede_consultar_kardex_materiales', false);
    }

    public function test_password_es_opcional_al_editar_y_email_debe_seguir_siendo_unico(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $usuario = User::factory()->create([
            'email' => 'editable@empresa.cl',
            'rol' => RolUsuario::Consulta,
            'activo' => true,
        ]);
        $otro = User::factory()->create(['email' => 'ocupado@empresa.cl']);
        $hashAnterior = $usuario->password;

        $this->actingAs($administrador, 'sanctum')
            ->putJson("/api/administracion/usuarios/{$usuario->id}", [
                'nombre' => 'Usuario editable',
                'email' => $usuario->email,
                'rol' => RolUsuario::Despachador->value,
                'activo' => true,
            ])
            ->assertOk();

        $this->assertSame($hashAnterior, $usuario->refresh()->password);

        $this->putJson("/api/administracion/usuarios/{$usuario->id}", [
            'nombre' => 'Usuario editable',
            'email' => $otro->email,
            'rol' => RolUsuario::Despachador->value,
            'activo' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_no_se_puede_desactivar_o_degradar_al_ultimo_administrador_activo(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->putJson("/api/administracion/usuarios/{$administrador->id}", [
                'nombre' => $administrador->name,
                'email' => $administrador->email,
                'rol' => RolUsuario::Consulta->value,
                'activo' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');

        $this->assertSame(RolUsuario::Administrador, $administrador->refresh()->rol);
        $this->assertTrue($administrador->activo);
    }

    public function test_usuario_no_administrador_no_puede_modificar_usuarios(): void
    {
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $usuario = User::factory()->create();

        $this->actingAs($supervisor, 'sanctum')
            ->putJson("/api/administracion/usuarios/{$usuario->id}", [
                'nombre' => 'Intento no autorizado',
                'email' => $usuario->email,
                'rol' => RolUsuario::Consulta->value,
                'activo' => true,
            ])
            ->assertForbidden();
    }
}
