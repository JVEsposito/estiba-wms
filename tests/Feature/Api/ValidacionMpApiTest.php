<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Cliente;
use App\Models\CsgValidacion;
use App\Models\EspecieValidacion;
use App\Models\Temporada;
use App\Models\User;
use App\Models\VariedadValidacion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ValidacionMpApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_toma_recepcion_por_correlativo_y_confirma_diferencias_y_segregacion_sin_crear_folios(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-21 10:10:00'));
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $cliente = $this->cliente();
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $validador = User::factory()->create(['rol' => RolUsuario::ValidadorMp]);
        $otroValidador = User::factory()->create(['rol' => RolUsuario::ValidadorMp]);
        $recepcion = $this->actingAs($operador, 'sanctum')
            ->postJson('/api/romana/recepciones', $this->recepcion($temporada, $cliente))
            ->assertCreated()
            ->json('data');

        $especie = EspecieValidacion::create(['temporada_id' => $temporada->id, 'nombre' => 'Cereza', 'activo' => true]);
        $variedad = VariedadValidacion::create(['especie_validacion_id' => $especie->id, 'nombre' => 'Santina', 'activo' => true]);
        $csg = CsgValidacion::create(['temporada_id' => $temporada->id, 'codigo' => 'CSG-001', 'activo' => true]);

        $this->actingAs($validador, 'sanctum')
            ->getJson('/api/validacion-mp/pendientes')
            ->assertOk()
            ->assertJsonPath('data.0.numero_recepcion', 'REC-2607-0001')
            ->assertJsonPath('data.0.numero_guia_despacho', 'GD-MP-001')
            ->assertJsonPath('data.0.envases.1.tipo_envase', 'totes');

        $validacion = $this->postJson('/api/validacion-mp/recepciones/'.$recepcion['id'].'/tomar', [
            'operacion_id' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('data.estado', 'en_curso')->json('data');

        $this->actingAs($otroValidador, 'sanctum')
            ->postJson('/api/validacion-mp/recepciones/'.$recepcion['id'].'/tomar', [
                'operacion_id' => (string) Str::uuid(),
            ])
            ->assertConflict()
            ->assertJsonPath('message', 'La recepción ya fue tomada por otro validador MP.');

        $this->actingAs($validador, 'sanctum');
        $operacionConfirmacion = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacionConfirmacion,
            'envases' => [
                ['tipo_envase' => 'bins', 'cantidad_validada' => 45],
                ['tipo_envase' => 'totes', 'cantidad_validada' => 10],
            ],
            'tarjas_verificadas' => true,
            'requiere_segregacion' => true,
            'segmentos' => [
                [
                    'motivos' => ['csg'],
                    'csg_validacion_id' => $csg->id,
                    'envases' => [
                        ['tipo_envase' => 'bins', 'cantidad' => 20],
                        ['tipo_envase' => 'totes', 'cantidad' => 4],
                    ],
                ],
                [
                    'motivos' => ['cuartel', 'variedad'],
                    'cuartel' => 'C-12',
                    'variedad_validacion_id' => $variedad->id,
                    'envases' => [
                        ['tipo_envase' => 'bins', 'cantidad' => 25],
                        ['tipo_envase' => 'totes', 'cantidad' => 6],
                    ],
                ],
            ],
        ];
        $this->postJson('/api/validacion-mp/validaciones/'.$validacion['id'].'/confirmar', $payload)
            ->assertOk()
            ->assertJsonPath('data.estado', 'validada')
            ->assertJsonPath('data.requiere_segregacion', true)
            ->assertJsonPath('data.segmentos.0.estado', 'pendiente_lote')
            ->assertJsonPath('data.segmentos.1.cuartel', 'C-12')
            ->assertJsonCount(2, 'data.segmentos');
        $this->postJson('/api/validacion-mp/validaciones/'.$validacion['id'].'/confirmar', $payload)->assertOk();

        $this->assertDatabaseHas('detalles_envases_recepcion_romana', [
            'recepcion_romana_id' => $recepcion['id'],
            'tipo_envase' => 'bins',
            'cantidad_declarada' => 48,
            'cantidad_validada' => 45,
        ]);
        $this->assertDatabaseHas('movimientos_envases', [
            'recepcion_romana_id' => $recepcion['id'],
            'tipo_envase' => 'bins',
            'cantidad' => 45,
            'signo_cuenta' => 1,
            'signo_existencia' => 1,
            'propiedad' => 'cliente',
        ]);
        $this->assertDatabaseCount('movimientos_envases', 2);
        $this->assertDatabaseCount('segmentos_validacion_mp', 2);
        $this->assertDatabaseCount('folios', 0);
        $this->assertDatabaseCount('validaciones_pallet', 0);

        $this->getJson('/api/envases/cuenta-corriente/movimientos')
            ->assertForbidden();
        $this->actingAs($operador, 'sanctum')
            ->getJson('/api/envases/cuenta-corriente/movimientos')
            ->assertOk()
            ->assertJsonPath('resumen.lineas_pendientes_validacion', 0)
            ->assertJsonPath('data.0.ingreso_at', '2026-07-21T10:10:00+00:00');
    }

    public function test_recepcion_de_compra_solo_envases_no_exige_tarjas_y_no_afecta_cuenta_del_cliente(): void
    {
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $cliente = $this->cliente();
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $validador = User::factory()->create(['rol' => RolUsuario::ValidadorMp]);
        $datos = $this->recepcion($temporada, $cliente);
        $datos['tipo_recepcion'] = 'solo_envases';
        $datos['concepto_envases'] = 'compra';
        $datos['tipo_servicio'] = null;
        $datos['envases'] = [['tipo_envase' => 'esponjas', 'cantidad' => 500]];
        $recepcion = $this->actingAs($operador, 'sanctum')->postJson('/api/romana/recepciones', $datos)->assertCreated()->json('data');

        $this->actingAs($validador, 'sanctum');
        $validacion = $this->postJson('/api/validacion-mp/recepciones/'.$recepcion['id'].'/tomar', [
            'operacion_id' => (string) Str::uuid(),
        ])->assertOk()->json('data');
        $this->postJson('/api/validacion-mp/validaciones/'.$validacion['id'].'/confirmar', [
            'operacion_id' => (string) Str::uuid(),
            'envases' => [['tipo_envase' => 'esponjas', 'cantidad_validada' => 498]],
        ])->assertOk()->assertJsonPath('data.tarjas_verificadas', null)->assertJsonCount(0, 'data.segmentos');

        $this->assertDatabaseHas('movimientos_envases', [
            'recepcion_romana_id' => $recepcion['id'],
            'tipo_movimiento' => 'recepcion_compra',
            'cantidad' => 498,
            'signo_cuenta' => 0,
            'signo_existencia' => 1,
            'propiedad' => 'propia',
        ]);
    }

    private function cliente(): Cliente
    {
        return Cliente::create(['codigo' => 'CLI-MP', 'nombre' => 'Cliente MP', 'activo' => true]);
    }

    /** @return array<string, mixed> */
    private function recepcion(Temporada $temporada, Cliente $cliente): array
    {
        return [
            'operacion_id' => (string) Str::uuid(),
            'temporada_id' => $temporada->id,
            'cliente_id' => $cliente->id,
            'tipo_recepcion' => 'fruta_con_envases',
            'tipo_servicio' => 'proceso',
            'envases' => [
                ['tipo_envase' => 'bins', 'cantidad' => 48],
                ['tipo_envase' => 'totes', 'cantidad' => 10],
            ],
            'numero_guia_despacho' => 'GD-MP-001',
            'patente_camion' => 'ABCD12',
            'rut_conductor' => '12.345.678-5',
            'nombre_conductor' => 'Conductor MP',
            'peso_bruto' => 28000,
        ];
    }
}
