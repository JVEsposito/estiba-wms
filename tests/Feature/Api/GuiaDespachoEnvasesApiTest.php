<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Cliente;
use App\Models\MovimientoEnvase;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Temporadas\ServicioTemporadaGlobal;
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

        $this->getJson('/api/envases/guias-despacho/catalogos')
            ->assertOk()
            ->assertJsonPath('inventario.0.fisico', 100)
            ->assertJsonPath('inventario.0.reservado', 60)
            ->assertJsonPath('inventario.0.disponible', 40);
        $pdfBorrador = $this->get('/api/envases/guias-despacho/'.$guia['id'].'/documento')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', (string) $pdfBorrador->getContent());

        $this->postJson('/api/envases/guias-despacho/'.$guia['id'].'/confirmar')
            ->assertOk()
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.puede_anular', true)
            ->assertJsonPath('data.documento_hash', fn ($hash): bool => is_string($hash) && strlen($hash) === 64);
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

        $documentoOriginal = (string) $this
            ->get('/api/envases/guias-despacho/'.$guia['id'].'/documento')
            ->assertOk()
            ->getContent();
        $this->postJson('/api/envases/guias-despacho/'.$guia['id'].'/anular', [
            'motivo' => 'Camión rechazado antes de entrega.',
        ])->assertForbidden();
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $this->actingAs($administrador, 'sanctum');
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
        $this->assertSame(
            $documentoOriginal,
            (string) $this->get('/api/envases/guias-despacho/'.$guia['id'].'/documento')
                ->assertOk()
                ->getContent(),
        );
        $this->assertStringStartsWith(
            '%PDF-1.4',
            (string) $this->get('/api/envases/guias-despacho/'.$guia['id'].'/comprobante-anulacion')
                ->assertOk()
                ->getContent(),
        );
        $this->assertDatabaseCount('eventos_guias_despacho_envases', 3);
    }

    public function test_valida_idempotencia_y_el_stock_conjunto_de_las_lineas_propias(): void
    {
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $cliente = Cliente::create(['codigo' => 'CLI-STOCK', 'nombre' => 'Cliente stock', 'activo' => true]);
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $origen = $this->movimientoIngreso($temporada, $cliente, $operador, 'propia', 10);

        $this->actingAs($operador, 'sanctum');
        $duplicada = $this->payloadGuia($cliente, (string) Str::uuid(), [
            ['tipo_envase' => 'bins', 'cantidad' => 3, 'propiedad' => 'propia', 'movimiento_origen_id' => null],
            ['tipo_envase' => 'bins', 'cantidad' => 2, 'propiedad' => 'propia', 'movimiento_origen_id' => null],
        ]);
        $this->postJson('/api/envases/guias-despacho', $duplicada)
            ->assertConflict()
            ->assertJsonPath('message', 'No repitas el mismo tipo, propiedad y origen dentro de una guía.');

        $operacionId = (string) Str::uuid();
        $payload = $this->payloadGuia($cliente, $operacionId, [
            ['tipo_envase' => 'bins', 'cantidad' => 6, 'propiedad' => 'propia', 'movimiento_origen_id' => $origen->id],
        ]);
        $guia = $this->postJson('/api/envases/guias-despacho', $payload)
            ->assertCreated()
            ->json('data');
        $this->postJson('/api/envases/guias-despacho', $payload)->assertCreated();

        $cambiado = $payload;
        $cambiado['detalles'][0]['cantidad'] = 4;
        $this->postJson('/api/envases/guias-despacho', $cambiado)
            ->assertConflict()
            ->assertJsonPath('message', 'El identificador de operación ya fue utilizado con datos diferentes.');

        $this->postJson('/api/envases/guias-despacho', $this->payloadGuia(
            $cliente,
            (string) Str::uuid(),
            [['tipo_envase' => 'bins', 'cantidad' => 5, 'propiedad' => 'propia', 'movimiento_origen_id' => null]],
        ))
            ->assertConflict()
            ->assertJsonPath('message', 'No existe disponibilidad suficiente de bins propia; faltan 1 unidades.');
        $this->assertSame(10, $this->existencia('propia'));
        $this->getJson('/api/envases/guias-despacho/catalogos')
            ->assertJsonPath('inventario.0.reservado', 6)
            ->assertJsonPath('inventario.0.disponible', 4);
    }

    public function test_guias_y_origenes_quedan_aislados_por_temporada(): void
    {
        $temporadaAnterior = Temporada::query()->where('activa', true)->firstOrFail();
        $cliente = Cliente::create(['codigo' => 'CLI-TEMP', 'nombre' => 'Cliente temporada', 'activo' => true]);
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $origenAnterior = $this->movimientoIngreso($temporadaAnterior, $cliente, $operador, 'arrendada', 20);

        $this->actingAs($operador, 'sanctum');
        $guiaAnterior = $this->postJson('/api/envases/guias-despacho', $this->payloadGuia(
            $cliente,
            (string) Str::uuid(),
            [['tipo_envase' => 'bins', 'cantidad' => 5, 'propiedad' => 'arrendada', 'movimiento_origen_id' => $origenAnterior->id]],
        ))->assertCreated()->json('data');

        app(ServicioTemporadaGlobal::class)->guardar([
            'codigo' => 'ENV-NUEVA',
            'nombre' => 'Temporada nueva de envases',
            'activa' => true,
        ], usuarioId: $operador->id);

        $this->getJson('/api/envases/guias-despacho/catalogos')
            ->assertOk()
            ->assertJsonCount(0, 'origenes');
        $this->getJson('/api/envases/guias-despacho')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->postJson("/api/envases/guias-despacho/{$guiaAnterior['id']}/confirmar")->assertNotFound();
        $this->getJson("/api/envases/guias-despacho?temporada_id={$temporadaAnterior->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $guiaAnterior['id']);

        $this->postJson('/api/envases/guias-despacho', $this->payloadGuia(
            $cliente,
            (string) Str::uuid(),
            [['tipo_envase' => 'bins', 'cantidad' => 1, 'propiedad' => 'arrendada', 'movimiento_origen_id' => $origenAnterior->id]],
        ))
            ->assertConflict()
            ->assertJsonPath('message', 'El movimiento de origen pertenece a otra temporada.');
    }

    public function test_edita_y_cancela_un_borrador_liberando_la_reserva_sin_crear_movimientos(): void
    {
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $cliente = Cliente::create(['codigo' => 'CLI-CAN', 'nombre' => 'Cliente cancelación', 'activo' => true]);
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $this->movimientoIngreso($temporada, $cliente, $operador, 'cliente', 100);
        $this->actingAs($operador, 'sanctum');

        $guia = $this->postJson('/api/envases/guias-despacho', $this->payloadGuia(
            $cliente,
            (string) Str::uuid(),
            [['tipo_envase' => 'bins', 'cantidad' => 60, 'propiedad' => 'cliente', 'movimiento_origen_id' => null]],
        ))->assertCreated()
            ->assertJsonPath('data.resumen.0.reservado', 60)
            ->json('data');

        $actualizada = $this->putJson('/api/envases/guias-despacho/'.$guia['id'], [
            ...$this->payloadGuia(
                $cliente,
                (string) Str::uuid(),
                [['tipo_envase' => 'bins', 'cantidad' => 45, 'propiedad' => 'cliente', 'movimiento_origen_id' => null]],
            ),
            'version' => $guia['version'],
        ])->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.resumen.0.reservado', 45)
            ->json('data');

        $this->getJson('/api/envases/guias-despacho/catalogos')
            ->assertJsonPath('inventario.0.reservado', 45)
            ->assertJsonPath('inventario.0.disponible', 55);
        $this->postJson('/api/envases/guias-despacho/'.$guia['id'].'/cancelar', [
            'motivo' => 'El cliente postergó el retiro.',
        ])->assertOk()
            ->assertJsonPath('data.estado', 'cancelada')
            ->assertJsonPath('data.motivo_cancelacion', 'El cliente postergó el retiro.');
        $this->getJson('/api/envases/guias-despacho/catalogos')
            ->assertJsonPath('inventario.0.reservado', 0)
            ->assertJsonPath('inventario.0.disponible', 100);
        $this->assertDatabaseCount('movimientos_envases', 1);
        $this->assertDatabaseCount('eventos_guias_despacho_envases', 3);
        $this->postJson('/api/envases/guias-despacho/'.$actualizada['id'].'/confirmar')
            ->assertConflict();
    }

    public function test_fifo_automatico_divide_la_reserva_y_la_salida_entre_varios_origenes(): void
    {
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $cliente = Cliente::create(['codigo' => 'CLI-FIFO', 'nombre' => 'Cliente FIFO', 'activo' => true]);
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $origenUno = $this->movimientoIngreso($temporada, $cliente, $operador, 'cliente', 30);
        $this->travel(1)->minute();
        $origenDos = $this->movimientoIngreso($temporada, $cliente, $operador, 'cliente', 40);
        $this->actingAs($operador, 'sanctum');

        $guia = $this->postJson('/api/envases/guias-despacho', $this->payloadGuia(
            $cliente,
            (string) Str::uuid(),
            [['tipo_envase' => 'bins', 'cantidad' => 60, 'propiedad' => 'cliente', 'movimiento_origen_id' => null]],
        ))->assertCreated()
            ->assertJsonCount(2, 'data.detalles')
            ->assertJsonPath('data.detalles.0.movimiento_origen_id', $origenUno->id)
            ->assertJsonPath('data.detalles.0.cantidad', 30)
            ->assertJsonPath('data.detalles.1.movimiento_origen_id', $origenDos->id)
            ->assertJsonPath('data.detalles.1.cantidad', 30)
            ->json('data');

        $this->postJson('/api/envases/guias-despacho/'.$guia['id'].'/confirmar', [
            'version' => $guia['version'],
        ])->assertOk();
        $this->assertDatabaseHas('movimientos_envases', [
            'documento_id' => $guia['id'],
            'movimiento_origen_id' => $origenUno->id,
            'cantidad' => 30,
            'signo_existencia' => -1,
        ]);
        $this->assertDatabaseHas('movimientos_envases', [
            'documento_id' => $guia['id'],
            'movimiento_origen_id' => $origenDos->id,
            'cantidad' => 30,
            'signo_existencia' => -1,
        ]);
        $this->assertSame(10, $this->existencia('cliente'));
        $this->assertSame(10, $this->saldoCuenta($cliente));
    }

    /** @param array<int, array<string, mixed>> $detalles */
    private function payloadGuia(Cliente $cliente, string $operacionId, array $detalles): array
    {
        return [
            'operacion_id' => $operacionId,
            'cliente_id' => $cliente->id,
            'salida_at' => '2026-07-21T12:15:00-04:00',
            'detalles' => $detalles,
        ];
    }

    private function movimientoIngreso(
        Temporada $temporada,
        Cliente $cliente,
        User $usuario,
        string $propiedad,
        int $cantidad,
    ): MovimientoEnvase {
        return MovimientoEnvase::create([
            'operacion_id' => (string) Str::uuid(),
            'temporada_id' => $temporada->id,
            'cliente_id' => $cliente->id,
            'documento_tipo' => 'recepcion_romana',
            'numero_documento' => 'REC-TEST',
            'tipo_movimiento' => match ($propiedad) {
                'arrendada' => 'recepcion_arriendo',
                'cliente' => 'recepcion_fruta',
                default => 'recepcion_compra',
            },
            'tipo_envase' => 'bins',
            'cantidad' => $cantidad,
            'signo_cuenta' => $propiedad === 'propia' ? 0 : 1,
            'signo_existencia' => 1,
            'propiedad' => $propiedad,
            'ocurrido_at' => now(),
            'ingreso_at' => now(),
            'estado_revision' => 'pendiente',
            'creado_por_user_id' => $usuario->id,
        ]);
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
