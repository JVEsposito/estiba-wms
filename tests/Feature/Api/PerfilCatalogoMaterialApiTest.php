<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\PerfilAcceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfilCatalogoMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfil_operativo_de_catalogos_materiales_abre_la_pantalla_sin_administrar_accesos(): void
    {
        $perfil = PerfilAcceso::create([
            'codigo' => 'DIGITADOR_MAT_PRUEBA',
            'nombre' => 'Digitador de materiales',
            'rol_base' => RolUsuario::Despachador,
            'modulos' => ['materiales.resumen', 'materiales.catalogos'],
            'modulos_tablet' => [],
            'activo' => true,
        ]);

        $usuario = User::factory()->create([
            'rol' => RolUsuario::Despachador,
            'perfil_acceso_id' => $perfil->id,
        ]);

        $token = $this->postJson('/api/acceso-oficina', [
            'email' => $usuario->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.puede_consultar_despachos_materiales', true)
            ->assertJsonPath('usuario.puede_administrar_catalogos_materiales', true)
            ->assertJsonPath('usuario.puede_administrar_accesos', false)
            ->json('token');

        $this->withToken($token);
        $this->getJson('/api/administracion/materiales/proveedores')->assertOk();
        $this->getJson('/api/administracion/materiales/items')->assertOk();
        $this->postJson('/api/administracion/usuarios', [])->assertForbidden();

        $perfil->update(['modulos' => ['materiales.resumen']]);
        $tokenSinCatalogos = $this->postJson('/api/acceso-oficina', [
            'email' => $usuario->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.puede_administrar_catalogos_materiales', false)
            ->json('token');

        $this->withToken($tokenSinCatalogos);
        $this->getJson('/api/administracion/materiales/proveedores')->assertForbidden();
    }

    public function test_perfil_solo_consulta_no_puede_administrar_catalogos_de_materiales(): void
    {
        $perfil = PerfilAcceso::create([
            'codigo' => 'CONSULTA_MAT_PRUEBA',
            'nombre' => 'Consulta de materiales',
            'rol_base' => RolUsuario::Consulta,
            'modulos' => ['materiales.catalogos'],
            'modulos_tablet' => [],
            'activo' => true,
        ]);

        $usuario = User::factory()->create([
            'rol' => RolUsuario::Consulta,
            'perfil_acceso_id' => $perfil->id,
        ]);

        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/administracion/materiales/proveedores')
            ->assertForbidden();
    }
}
