<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PermisosRecepcionMaterialCamareroTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_camarero_de_materiales_puede_gestionar_recepciones_pero_no_anularlas(): void
    {
        $camarero = User::factory()->make([
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        $alcance = app(AlcanceOperacionalUsuario::class);
        $capacidades = $alcance->capacidadesApi($camarero);

        $this->assertTrue($alcance->puedeGestionarRecepcionesMateriales($camarero));
        $this->assertFalse($alcance->puedeAnularRecepcionesMateriales($camarero));
        $this->assertTrue($capacidades['puede_gestionar_recepciones_materiales']);
        $this->assertFalse($capacidades['puede_anular_recepciones_materiales']);
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
                'email' => 'camat.editado@estiba.local',
                'rol' => RolUsuario::CamareroMateriales->value,
                'activo' => true,
            ])
            ->assertOk()
            ->assertJsonPath('usuario.nombre', 'Camarero de materiales')
            ->assertJsonPath('usuario.email', 'camat.editado@estiba.local')
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
