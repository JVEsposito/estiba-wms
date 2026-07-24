<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PermisosRecepcionMaterialCamareroTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_camarero_de_materiales_puede_crear_y_confirmar_recepciones_pero_no_anularlas(): void
    {
        $camarero = User::factory()->create([
            'email' => 'camat@estiba.local',
            'password' => 'Password2026',
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        Dispositivo::create([
            'codigo' => 'TABLET-RECEPCION-CAMAT',
            'nombre' => 'Tablet recepción Camat',
            'activo' => true,
        ]);

        $acceso = $this->postJson('/api/acceso-tablet', [
            'email' => $camarero->email,
            'password' => 'Password2026',
            'codigo_dispositivo' => 'TABLET-RECEPCION-CAMAT',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.capacidades.puede_consultar_recepciones_materiales', true)
            ->assertJsonPath('usuario.capacidades.puede_gestionar_recepciones_materiales', true)
            ->assertJsonPath('usuario.capacidades.puede_anular_recepciones_materiales', false);

        $this->withToken($acceso->json('token'))
            ->postJson('/api/materiales/recepciones', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'operacion_id',
                'cliente_id',
                'proveedor_material_id',
                'numero_guia_despacho',
                'detalles',
            ]);
    }

    public function test_el_administrador_puede_editar_un_usuario_mediante_la_ruta_put(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $usuario = User::factory()->create([
            'name' => 'Nombre anterior',
            'email' => 'anterior@estiba.local',
            'password' => 'ClaveActual2026',
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->putJson("/api/administracion/usuarios/{$usuario->id}", [
                'nombre' => 'Camarero de materiales',
                'email' => 'camat@estiba.local',
                'rol' => RolUsuario::CamareroMateriales->value,
                'activo' => true,
            ])
            ->assertOk()
            ->assertJsonPath('usuario.nombre', 'Camarero de materiales')
            ->assertJsonPath('usuario.email', 'camat@estiba.local')
            ->assertJsonPath('usuario.rol', RolUsuario::CamareroMateriales->value)
            ->assertJsonPath('usuario.permisos.puede_gestionar_recepciones_materiales', true)
            ->assertJsonPath('usuario.permisos.puede_anular_recepciones_materiales', false);

        $usuario->refresh();
        $this->assertSame(RolUsuario::CamareroMateriales, $usuario->rol);
        $this->assertTrue(Hash::check('ClaveActual2026', $usuario->password));
    }

    public function test_un_usuario_no_administrador_no_puede_editar_usuarios(): void
    {
        $camarero = User::factory()->create([
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        $usuario = User::factory()->create([
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);

        $this->actingAs($camarero, 'sanctum')
            ->putJson("/api/administracion/usuarios/{$usuario->id}", [
                'nombre' => $usuario->name,
                'email' => $usuario->email,
                'rol' => RolUsuario::CamareroMateriales->value,
                'activo' => true,
            ])
            ->assertForbidden();
    }
}
