<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Cliente;
use App\Models\Embarque;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbarqueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendario_expone_las_24_horas_y_no_asigna_el_siguiente_cupo(): void
    {
        $despachador = $this->usuario(RolUsuario::Despachador);
        $cliente = $this->cliente($despachador, 'MC');
        Temporada::query()->where('activa', true)->update(['intervalo_embarques_minutos' => 45]);

        $creado = $this->actingAs($despachador, 'sanctum')->postJson('/api/embarques', [
            'cliente_id' => $cliente->id,
            'fecha_programada' => '2026-08-12',
            'hora_programada' => '08:15',
            'modalidad' => 'maritimo',
            'instructivos' => [
                ['numero_externo' => 'INS-001', 'cantidad_pallets' => 18],
                ['numero_externo' => 'INS-002', 'cantidad_pallets' => 6],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.codigo', 'EMC0000001')
            ->assertJsonPath('data.hora_programada', '08:15')
            ->assertJsonPath('data.totales.instructivos', 2)
            ->assertJsonPath('data.totales.pallets', 24);

        $this->actingAs($despachador, 'sanctum')
            ->getJson('/api/embarques?desde=2026-08-12&hasta=2026-08-12')
            ->assertOk()
            ->assertJsonCount(32, 'ventanas')
            ->assertJsonPath('ventanas.0.hora', '00:00')
            ->assertJsonPath('ventanas.31.hora', '23:15')
            ->assertJsonFragment([
                'hora' => '08:15',
                'disponible' => false,
                'ocupada_por' => ['EMC0000001'],
            ]);

        $this->assertDatabaseHas('embarques', [
            'id' => $creado->json('data.id'),
            'fecha_programada' => '2026-08-12',
            'intervalo_minutos' => 45,
        ]);
    }

    public function test_sobrecupo_exige_supervisor_y_conserva_motivo_auditado(): void
    {
        $despachador = $this->usuario(RolUsuario::Despachador);
        $supervisor = $this->usuario(RolUsuario::SupervisorFrio);
        $cliente = $this->cliente($despachador, 'AG');
        $payload = [
            'cliente_id' => $cliente->id,
            'fecha_programada' => '2026-08-13',
            'hora_programada' => '10:00',
            'modalidad' => 'aereo',
            'instructivos' => [[]],
        ];

        $this->actingAs($despachador, 'sanctum')->postJson('/api/embarques', $payload)
            ->assertCreated();
        $this->actingAs($despachador, 'sanctum')->postJson('/api/embarques', $payload)
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');
        $this->actingAs($despachador, 'sanctum')->postJson('/api/embarques', [
            ...$payload,
            'autorizar_sobrecupo' => true,
            'motivo_sobrecupo' => 'Solicitud prioritaria autorizada por operaciones.',
        ])->assertForbidden()
            ->assertJsonPath('codigo', 'operacion_no_autorizada');

        $autorizado = $this->actingAs($supervisor, 'sanctum')->postJson('/api/embarques', [
            ...$payload,
            'autorizar_sobrecupo' => true,
            'motivo_sobrecupo' => 'Solicitud prioritaria autorizada por operaciones.',
        ])->assertCreated()
            ->assertJsonPath('data.codigo', 'EAG0000002')
            ->assertJsonPath('data.sobrecupo.autorizado_por', $supervisor->name);

        $this->assertDatabaseHas('eventos_embarque', [
            'embarque_id' => $autorizado->json('data.id'),
            'tipo' => 'sobrecupo_autorizado',
        ]);
    }

    public function test_confirmar_un_embarque_crea_una_sola_orden_car_para_todos_sus_instructivos(): void
    {
        $despachador = $this->usuario(RolUsuario::Despachador);
        $cliente = $this->cliente($despachador, 'GE');
        $embarqueId = $this->actingAs($despachador, 'sanctum')->postJson('/api/embarques', [
            'cliente_id' => $cliente->id,
            'fecha_programada' => '2026-08-14',
            'hora_programada' => '18:00',
            'modalidad' => 'maritimo',
            'instructivos' => [
                ['numero_externo' => 'A', 'cantidad_pallets' => 20],
                ['numero_externo' => 'B', 'cantidad_pallets' => 4],
            ],
        ])->assertCreated()->json('data.id');

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/embarques/{$embarqueId}/confirmar", [
                'version_esperada' => 1,
                'prioridad' => 'alta',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'confirmado')
            ->assertJsonPath('data.carga.codigo', 'CAR-000001')
            ->assertJsonPath('data.carga.estado', 'borrador')
            ->assertJsonPath('data.totales.instructivos', 2);

        $this->assertDatabaseHas('cargas', [
            'codigo' => 'CAR-000001',
            'numero_orden_externa' => 'EGE0000001',
        ]);
        $this->assertDatabaseCount('cargas', 1);
    }

    public function test_intervalo_existente_no_cambia_al_editar_la_temporada_sin_el_parametro(): void
    {
        $administrador = $this->usuario(RolUsuario::Administrador);
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $temporada->update(['intervalo_embarques_minutos' => 30]);

        $this->actingAs($administrador, 'sanctum')
            ->putJson("/api/administracion/temporadas/{$temporada->id}", [
                'codigo' => $temporada->codigo,
                'nombre' => $temporada->nombre,
                'fecha_inicio' => $temporada->fecha_inicio?->toDateString(),
                'fecha_fin' => $temporada->fecha_fin?->toDateString(),
                'activa' => true,
            ])->assertOk()
            ->assertJsonPath('data.intervalo_embarques_minutos', 30);
    }

    private function usuario(RolUsuario $rol): User
    {
        return User::factory()->create(['rol' => $rol, 'activo' => true]);
    }

    private function cliente(User $usuario, string $sigla): Cliente
    {
        return Cliente::query()->create([
            'codigo' => 'CLI-'.$sigla,
            'nombre' => 'Cliente '.$sigla,
            'codigo_folio_materiales' => $sigla,
            'activo' => true,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }
}
