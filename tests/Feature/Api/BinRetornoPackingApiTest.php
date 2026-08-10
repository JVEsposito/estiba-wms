<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\BinRetornoPacking;
use App\Models\Temporada;
use App\Models\TipoResultadoPacking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BinRetornoPackingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_rutas_y_esquema_del_nuevo_modelo_por_bin(): void
    {
        $this->assertTrue(Schema::hasTable('bins_retorno_packing'));
        $this->assertTrue(Schema::hasTable('bin_retorno_packing_origenes'));
        $this->assertTrue(Schema::hasTable('regularizaciones_retorno_packing_legacy'));
        $this->assertTrue(Schema::hasColumns('bins_retorno_packing', [
            'temporada_id',
            'folio_provisional',
            'folio_definitivo',
            'kilos_totales',
            'estado',
            'payload_regularizacion_hash',
            'retorno_packing_legacy_id',
        ]));

        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $this->actingAs($camarero, 'sanctum')
            ->getJson('/api/materia-prima/fruta-proceso/retornos-bin/resumen')
            ->assertOk()
            ->assertJsonPath('bins_registrados', 0)
            ->assertJsonPath('pendientes_regularizacion', 0)
            ->assertJsonPath('regularizados', 0);

        $this->getJson('/api/materia-prima/fruta-proceso/retornos-bin/procesos')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_rechaza_bin_si_no_existe_un_origen_operacional_valido(): void
    {
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);

        $this->actingAs($camarero, 'sanctum')
            ->postJson('/api/materia-prima/fruta-proceso/retornos-bin/bins', [
                'operacion_id' => (string) Str::uuid(),
                'kilos_totales' => 412,
                'origenes' => [[
                    'lote_materia_prima_id' => (string) Str::uuid(),
                    'numero_orden' => '0608A',
                    'linea_proceso' => '600',
                    'turno' => 'A',
                    'kilos_aportados' => 412,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('origenes.0.lote_materia_prima_id');
    }

    public function test_aisla_bins_por_temporada_y_valida_idempotencia_de_regularizacion(): void
    {
        $temporadaActiva = Temporada::query()->where('activa', true)->firstOrFail();
        $temporadaAnterior = Temporada::create([
            'codigo' => '2025-2026',
            'nombre' => 'Temporada anterior',
            'activa' => false,
            'version_catalogo' => 1,
        ]);
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $vigente = $this->bin($temporadaActiva, $camarero, 'PR-ACTIVA');
        $this->bin($temporadaAnterior, $camarero, 'PR-HISTORICA');

        $this->actingAs($camarero, 'sanctum')
            ->getJson('/api/materia-prima/fruta-proceso/retornos-bin/bins')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.folio_provisional', 'PR-ACTIVA');
        $this->getJson('/api/materia-prima/fruta-proceso/retornos-bin/resumen')
            ->assertOk()
            ->assertJsonPath('bins_registrados', 1)
            ->assertJsonPath('pendientes_regularizacion', 1);

        $tipo = TipoResultadoPacking::query()->firstOrCreate(
            ['codigo' => 'TEST-RETORNO'],
            [
                'nombre' => 'Retorno de prueba',
                'prefijo_sublote' => 'TR',
                'activo' => true,
                'orden' => 999,
            ],
        );
        $operacion = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacion,
            'folio_definitivo' => 'MP-RET-0001',
            'tipo_resultado_packing_id' => $tipo->id,
            'nombre_resultado' => 'Retorno comercial',
        ];
        $ruta = "/api/materia-prima/fruta-proceso/retornos-bin/bins/{$vigente->id}/regularizar";

        $this->postJson($ruta, $payload)
            ->assertOk()
            ->assertJsonPath('data.folio_definitivo', 'MP-RET-0001')
            ->assertJsonPath('data.estado', 'regularizado');
        $this->postJson($ruta, $payload)
            ->assertOk()
            ->assertJsonPath('data.folio_definitivo', 'MP-RET-0001');
        $this->postJson($ruta, [
            ...$payload,
            'folio_definitivo' => 'MP-RET-0002',
        ])->assertStatus(409)
            ->assertJsonPath('message', 'El bin ya fue regularizado con otra operación o datos diferentes.');

        $this->assertDatabaseHas('bins_retorno_packing', [
            'id' => $vigente->id,
            'temporada_id' => $temporadaActiva->id,
            'folio_definitivo' => 'MP-RET-0001',
        ]);
        $this->assertNotNull($vigente->fresh()->payload_regularizacion_hash);
    }

    public function test_oficina_presenta_recepcion_regularizacion_y_legado(): void
    {
        $this->get('/oficina/materia-prima/retornos-packing')
            ->assertOk()
            ->assertSee('Registrar un bin')
            ->assertSee('Pendientes de regularizar')
            ->assertSee('Registros anteriores')
            ->assertSee('folio provisional', false);
    }

    private function bin(
        Temporada $temporada,
        User $usuario,
        string $folioProvisional,
    ): BinRetornoPacking {
        return BinRetornoPacking::create([
            'temporada_id' => $temporada->id,
            'operacion_id' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', $folioProvisional),
            'folio_provisional' => $folioProvisional,
            'kilos_totales' => 400,
            'estado' => 'pendiente_regularizacion',
            'registrado_por_user_id' => $usuario->id,
            'registrado_at' => now(),
        ]);
    }
}
