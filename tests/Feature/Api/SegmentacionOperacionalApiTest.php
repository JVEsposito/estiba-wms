<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Exceptions\OperacionNoAutorizada;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Posicion;
use App\Models\User;
use App\Services\Cargas\ServicioCarga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SegmentacionOperacionalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_listado_y_plano_de_camaras_respetan_el_area_del_usuario(): void
    {
        $producto = $this->crearCamara('CAM-FRIO', ContenidoCamara::Productos);
        $material = $this->crearCamara('CAM-MAT', ContenidoCamara::Materiales);
        $camareroFrio = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $camareroMateriales = User::factory()->create(['rol' => RolUsuario::CamareroMateriales]);

        $this->actingAs($camareroFrio, 'sanctum')
            ->getJson('/api/camaras')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $producto->id);

        $this->actingAs($camareroFrio, 'sanctum')
            ->getJson("/api/camaras/{$material->id}/plano")
            ->assertForbidden();

        $this->actingAs($camareroFrio, 'sanctum')
            ->getJson("/api/movimientos/recientes?camara_id={$material->id}")
            ->assertForbidden();

        $this->actingAs($camareroMateriales, 'sanctum')
            ->getJson('/api/camaras')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $material->id);
    }

    public function test_consulta_y_despachador_ven_ambas_areas_sin_abrir_sesiones(): void
    {
        $this->crearCamara('CAM-FRIO', ContenidoCamara::Productos);
        $this->crearCamara('CAM-MAT', ContenidoCamara::Materiales);

        foreach ([RolUsuario::Consulta, RolUsuario::Despachador] as $rol) {
            $usuario = User::factory()->create(['rol' => $rol]);
            $dispositivo = Dispositivo::create([
                'codigo' => 'TABLET-'.$rol->value,
                'nombre' => 'Tablet de consulta',
            ]);
            $token = $usuario
                ->crearTokenParaDispositivo($dispositivo, 'tablet-consulta')
                ->plainTextToken;

            $this->withToken($token)
                ->getJson('/api/camaras')
                ->assertOk()
                ->assertJsonCount(2, 'data');

            $camara = Camara::query()
                ->where('contenido', ContenidoCamara::Productos->value)
                ->firstOrFail();
            $this->withToken($token)
                ->postJson("/api/camaras/{$camara->id}/sesiones")
                ->assertForbidden();
        }
    }

    public function test_supervisores_crean_solo_camaras_de_su_area(): void
    {
        $supervisorFrio = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);
        $supervisorMateriales = User::factory()->create(['rol' => RolUsuario::SupervisorMateriales]);

        $payload = [
            'nombre' => 'Cámara segmentada',
            'tipo' => 'transito',
            'contenido' => ContenidoCamara::Materiales->value,
            'bandas' => 1,
            'posiciones_por_banda' => 1,
            'niveles' => 1,
        ];

        $this->actingAs($supervisorFrio, 'sanctum')
            ->postJson('/api/configuracion/camaras', $payload)
            ->assertCreated()
            ->assertJsonPath('data.contenido', ContenidoCamara::Productos->value);

        $this->actingAs($supervisorMateriales, 'sanctum')
            ->postJson('/api/configuracion/camaras', [
                ...$payload,
                'contenido' => ContenidoCamara::Productos->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.contenido', ContenidoCamara::Materiales->value);
    }

    public function test_modulos_de_cargas_y_materiales_rechazan_el_area_contraria(): void
    {
        $supervisorFrio = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);
        $supervisorMateriales = User::factory()->create(['rol' => RolUsuario::SupervisorMateriales]);

        $this->actingAs($supervisorFrio, 'sanctum')
            ->getJson('/api/materiales/inventario')
            ->assertForbidden();
        $this->actingAs($supervisorFrio, 'sanctum')
            ->getJson('/api/materiales/kardex')
            ->assertForbidden();
        $this->actingAs($supervisorMateriales, 'sanctum')
            ->getJson('/api/cargas')
            ->assertForbidden();

        $this->expectException(OperacionNoAutorizada::class);
        app(ServicioCarga::class)->crear([], $supervisorMateriales);
    }

    public function test_camarero_frio_consulta_operacion_publicada_pero_no_catalogo_de_oficina(): void
    {
        $camareroFrio = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);

        $this->actingAs($camareroFrio, 'sanctum')
            ->getJson('/api/cargas/pendientes')
            ->assertOk();

        $this->actingAs($camareroFrio, 'sanctum')
            ->getJson('/api/cargas')
            ->assertForbidden();

        $this->actingAs($camareroFrio, 'sanctum')
            ->getJson('/api/cargas/folios-disponibles')
            ->assertForbidden();
    }

    public function test_migracion_convierte_roles_y_revoca_tokens_afectados(): void
    {
        $usuario = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $tokenId = $usuario->createToken('token-anterior')->accessToken->id;
        DB::table('users')->where('id', $usuario->id)->update(['rol' => 'operador']);
        $migration = require database_path(
            'migrations/2026_07_17_120000_segmentar_roles_operacionales_por_area.php',
        );

        $migration->up();

        $this->assertDatabaseHas('users', [
            'id' => $usuario->id,
            'rol' => RolUsuario::CamareroFrio->value,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_rollback_unifica_ambas_areas_en_los_roles_anteriores(): void
    {
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroMateriales]);
        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorMateriales]);
        $tokenCamarero = $camarero->createToken('token-camarero')->accessToken->id;
        $tokenSupervisor = $supervisor->createToken('token-supervisor')->accessToken->id;
        $migration = require database_path(
            'migrations/2026_07_17_120000_segmentar_roles_operacionales_por_area.php',
        );

        $migration->down();

        $this->assertDatabaseHas('users', [
            'id' => $camarero->id,
            'rol' => 'operador',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $supervisor->id,
            'rol' => 'supervisor',
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenCamarero]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenSupervisor]);
    }

    private function crearCamara(string $codigo, ContenidoCamara $contenido): Camara
    {
        $camara = Camara::create([
            'codigo' => $codigo,
            'nombre' => "Cámara {$codigo}",
            'contenido' => $contenido,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);
        Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => 'B01-P01-N1',
        ]);

        return $camara;
    }
}
