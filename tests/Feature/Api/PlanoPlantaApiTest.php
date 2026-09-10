<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Enums\TipoAlmacenMaterial;
use App\Models\AlmacenMaterial;
use App\Models\Anden;
use App\Models\Camara;
use App\Models\PlanoPlanta;
use App\Models\Temporada;
use App\Models\TunelPrefrio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanoPlantaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_vista_incluye_catalogo_operacional_y_solo_el_administrador_puede_editar(): void
    {
        Temporada::create([
            'codigo' => 'ACTUAL',
            'nombre' => 'Temporada actual',
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2027-03-31',
            'activa' => true,
        ]);
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $consulta = User::factory()->create(['rol' => RolUsuario::Consulta]);
        $camara = $this->crearCamara();
        $anden = $this->crearAnden($administrador);

        $this->actingAs($consulta, 'sanctum')
            ->getJson('/api/operacion-ahora')
            ->assertOk()
            ->assertJsonPath('data.planta.configurado', false)
            ->assertJsonPath('data.planta.version', 0)
            ->assertJsonPath('data.planta.puede_editar', false)
            ->assertJsonFragment(['tipo' => 'camara', 'id' => $camara->id])
            ->assertJsonFragment(['tipo' => 'anden', 'id' => $anden->id]);

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/operacion-ahora')
            ->assertOk()
            ->assertJsonPath('data.planta.puede_editar', true);
    }

    public function test_el_administrador_guarda_un_plano_con_recintos_reales_y_areas_dibujadas(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $consulta = User::factory()->create(['rol' => RolUsuario::Consulta]);
        $camara = $this->crearCamara();
        $tunel = $this->crearTunel($administrador);
        $anden = $this->crearAnden($administrador);
        $almacen = $this->crearAlmacen($administrador);
        $elementos = [
            $this->elemento('camara', $camara->id, 'Cámara uno', 200, 200),
            $this->elemento('tunel', $tunel->id, 'Túnel uno', 2300, 200),
            $this->elemento('anden', $anden->id, 'Andén uno', 4400, 200),
            $this->elemento('almacen', $almacen->id, 'Bodega uno', 6500, 200),
            $this->elemento('zona', null, 'Patio de recepción', 200, 2500, 'patio'),
        ];

        $this->actingAs($consulta, 'sanctum')
            ->putJson('/api/administracion/operacion-ahora/plano', [
                'version_esperada' => 0,
                'nombre' => 'Planta frigorífica',
                'elementos' => $elementos,
            ])
            ->assertForbidden();

        $this->actingAs($administrador, 'sanctum')
            ->putJson('/api/administracion/operacion-ahora/plano', [
                'version_esperada' => 0,
                'nombre' => 'Planta frigorífica',
                'elementos' => $elementos,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.nombre', 'Planta frigorífica')
            ->assertJsonCount(5, 'data.elementos');

        $this->assertDatabaseHas('planos_planta', [
            'codigo' => 'principal',
            'nombre' => 'Planta frigorífica',
            'version' => 1,
            'actualizado_por_user_id' => $administrador->id,
        ]);
    }

    public function test_rechaza_sobrescrituras_con_una_version_antigua(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        PlanoPlanta::create([
            'codigo' => 'principal',
            'nombre' => 'Plano vigente',
            'version' => 3,
            'elementos' => [],
            'actualizado_por_user_id' => $administrador->id,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->putJson('/api/administracion/operacion-ahora/plano', [
                'version_esperada' => 2,
                'nombre' => 'Plano atrasado',
                'elementos' => [],
            ])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->assertDatabaseHas('planos_planta', ['version' => 3, 'nombre' => 'Plano vigente']);
    }

    public function test_valida_limites_duplicados_y_referencias_inexistentes(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $camara = $this->crearCamara();
        $duplicado = $this->elemento('camara', $camara->id, 'Duplicada', 9200, 100);

        $this->actingAs($administrador, 'sanctum')
            ->putJson('/api/administracion/operacion-ahora/plano', [
                'version_esperada' => 0,
                'nombre' => 'Plano inválido',
                'elementos' => [
                    $duplicado,
                    [...$duplicado, 'id' => (string) Str::uuid(), 'x' => 200],
                    $this->elemento('anden', (string) Str::uuid(), 'No existe', 3000, 3000),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['elementos.camara', 'elementos.0', 'elementos.anden']);
    }

    /** @return array<string, mixed> */
    private function elemento(string $tipo, ?string $referencia, string $nombre, int $x, int $y, ?string $categoria = null): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tipo' => $tipo,
            'referencia_id' => $referencia,
            'nombre' => $nombre,
            'categoria' => $categoria,
            'x' => $x,
            'y' => $y,
            'ancho' => 1200,
            'alto' => 900,
            'rotacion' => 0,
        ];
    }

    private function crearCamara(): Camara
    {
        return Camara::create([
            'codigo' => 'CAM-PLANO-01',
            'nombre' => 'Cámara plano',
            'contenido' => ContenidoCamara::Productos,
        ]);
    }

    private function crearTunel(User $usuario): TunelPrefrio
    {
        return TunelPrefrio::create([
            'codigo' => 'TUN-PLANO-01',
            'nombre' => 'Túnel plano',
            'capacidad_posiciones' => 10,
            'creado_por_user_id' => $usuario->id,
        ]);
    }

    private function crearAnden(User $usuario): Anden
    {
        return Anden::create([
            'codigo' => 'AND-PLANO-01',
            'nombre' => 'Andén plano',
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }

    private function crearAlmacen(User $usuario): AlmacenMaterial
    {
        return AlmacenMaterial::create([
            'codigo' => 'BOD-PLANO-01',
            'nombre' => 'Bodega plano',
            'tipo' => TipoAlmacenMaterial::Fisica,
            'centro_costo' => 'FRIO',
            'requiere_ubicacion_fisica' => true,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }
}
