<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Anden;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AndenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_administrador_crea_edita_y_desactiva_andenes(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $respuesta = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/andenes', [
                'codigo' => '  and-01  ',
                'nombre' => '  Andén principal  ',
                'codigo_externo' => '  ERP-01  ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.codigo', 'AND-01')
            ->assertJsonPath('data.nombre', 'Andén principal')
            ->assertJsonPath('data.codigo_externo', 'ERP-01')
            ->assertJsonPath('data.activo', true);

        $andenId = $respuesta->json('data.id');

        $this->assertDatabaseHas('andenes', [
            'id' => $andenId,
            'codigo' => 'AND-01',
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->putJson("/api/administracion/andenes/{$andenId}", [
                'codigo' => 'AND-01',
                'nombre' => 'Andén norte',
                'codigo_externo' => '',
                'activo' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Andén norte')
            ->assertJsonPath('data.codigo_externo', null)
            ->assertJsonPath('data.activo', false);

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/andenes')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/andenes?incluir_inactivos=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $andenId)
            ->assertJsonPath('data.0.activo', false);
    }

    public function test_un_supervisor_solo_lista_andenes_activos_y_no_puede_gestionarlos(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $activo = $this->crearAnden($administrador, 'AND-01', true);
        $inactivo = $this->crearAnden($administrador, 'AND-02', false);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/andenes?incluir_inactivos=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activo->id)
            ->assertJsonMissing(['id' => $inactivo->id]);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/administracion/andenes', [
                'codigo' => 'AND-03',
                'nombre' => 'Sin autorización',
            ])
            ->assertForbidden();

        $this->actingAs($supervisor, 'sanctum')
            ->putJson("/api/administracion/andenes/{$activo->id}", [
                'codigo' => 'AND-01',
                'nombre' => 'Cambio rechazado',
            ])
            ->assertForbidden();
    }

    public function test_valida_el_formato_y_la_unicidad_del_codigo_del_anden(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $this->crearAnden($administrador, 'AND-01', true);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/andenes', [
                'codigo' => 'and-01',
                'nombre' => 'Duplicado',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo']);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/andenes', [
                'codigo' => 'AND 02',
                'nombre' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo', 'nombre']);
    }

    private function crearAnden(User $usuario, string $codigo, bool $activo): Anden
    {
        return Anden::create([
            'codigo' => $codigo,
            'nombre' => "Andén {$codigo}",
            'activo' => $activo,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }
}
