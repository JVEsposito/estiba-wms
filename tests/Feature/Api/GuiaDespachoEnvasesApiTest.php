<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Cliente;
use App\Models\MovimientoEnvase;
use App\Models\Temporada;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuiaDespachoEnvasesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfiere_envases_arrendados_a_un_cliente_y_anula_con_reversa_sin_mover_la_cuenta_del_arrendador(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-21 08:00:00'));
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $arrendador = Cliente::create(['codigo' => 'ARR-01', 'nombre' => 'Arrendadora Uno', 'activo' => true]);
        $cliente = Cliente::create(['codigo' => 'CLI-01', 'nombre' => 'Productor Uno', 'activo' => true]);
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $validador = User::factory()->create(['rol' => RolUsuario::ValidadorMp]);

        $recepcion = $this->actingAs($operador, 'sanctum')->postJson('/api/romana/recepciones', [
            'operacion_id' => (string) Str::uuid(),
            'temporada_id' => $temporada->id,
            'cliente_id' => $arrendador->id,
            'tipo_recepcion' => 'solo_envases',
            'concepto_envases' => 'arriendo',
            'envases' => [['tipo_envase' => 'bins', 'cantidad' => 100]],
            'numero_guia_despacho' => 'ARR-GD-100',
            'patente_camion' => 'ABCD12',
            'rut_conductor' => '12.345.678-5',
            'nombre_conductor' => 'Transportista Uno',
            'peso_bruto' => 16000,
        ])->assertCreated()->json('data');
        $this->actingAs($validador, 'sanctum');
        $validacion = $this->postJson('/api/validacion-mp/recepciones/'.$recepcion['id'].'/tomar', [
            'operacion_id' => (string) Str::uuid(),
        ])->assertOk()->json('data');
        $this->postJson('/api/validacion-mp/validaciones/'.$validacion['id'].'/confirmar', [
            'operacion_id' => (string) Str::uuid(),
            'envases' => [['tipo_envase' => 'bins', 'cantidad_validada' => 100]],
        ])->assertOk();
        $origen = MovimientoEnvase::query()->where('recepcion_romana_id', $recepcion['id'])->firstOrFail();

        $this->travelTo(CarbonImmutable::parse('2026-07-21 12:30:00'));
        $this->actingAs($operador, 'sanctum');
        $guia = $this->postJson('/api/envases/guias-despacho', [
            'operacion_id' => (string) Str::uuid(),
            'cliente_id' => $cliente->id,
            'salida_at' => '2026-07-21T12:15:00-04:00',
            'patente_camion' => 'WXYZ34',
            'nombre_conductor' => 'Transportista Dos',
            'detalles' => [[
                'tipo_envase' => 'bins',
                'cantidad' => 60,
                'propiedad' => 'arrendada',
                'movimiento_origen_id' => $origen->id,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.numero', 'GDE-2607-0001')
            ->assertJsonPath('data.estado', 'borrador')
            ->json('data');

        $this->postJson('/api/envases/guias-despacho/'.$guia['id'].'/confirmar')
            ->assertOk()
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.puede_anular', true);
        $this->assertDatabaseHas('movimientos_envases', [
            'documento_id' => $guia['id'],
            'cliente_id' => $cliente->id,
            'tipo_movimiento' => 'despacho_cliente',
            'cantidad' => 60,
            'signo_cuenta' => -1,
            'signo_existencia' => -1,
            'propiedad' => 'arrendada',
            'movimiento_origen_id' => $origen->id,
        ]);
        $this->assertSame(100, $this->saldoCuenta($arrendador));
        $this->assertSame(-60, $this->saldoCuenta($cliente));
        $this->assertSame(40, $this->existencia('arrendada'));

        $this->postJson('/api/envases/guias-despacho/'.$guia['id'].'/anular', [
            'motivo' => 'Camión rechazado antes de entrega.',
        ])->assertOk()->assertJsonPath('data.estado', 'anulada');
        $this->assertDatabaseHas('movimientos_envases', [
            'documento_id' => $guia['id'],
            'cliente_id' => $cliente->id,
            'tipo_movimiento' => 'reversion_despacho',
            'cantidad' => 60,
            'signo_cuenta' => 1,
            'signo_existencia' => 1,
            'movimiento_origen_id' => $origen->id,
        ]);
        $this->assertSame(100, $this->saldoCuenta($arrendador));
        $this->assertSame(0, $this->saldoCuenta($cliente));
        $this->assertSame(100, $this->existencia('arrendada'));
    }

    private function saldoCuenta(Cliente $cliente): int
    {
        return (int) MovimientoEnvase::query()->where('cliente_id', $cliente->id)
            ->get()->sum(fn (MovimientoEnvase $movimiento): int => $movimiento->cantidad * $movimiento->signo_cuenta);
    }

    private function existencia(string $propiedad): int
    {
        return (int) MovimientoEnvase::query()->where('propiedad', $propiedad)
            ->get()->sum(fn (MovimientoEnvase $movimiento): int => $movimiento->cantidad * $movimiento->signo_existencia);
    }
}
