<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
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
            'folio_provisional',
            'folio_definitivo',
            'kilos_totales',
            'estado',
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

    public function test_oficina_presenta_recepcion_regularizacion_y_legado(): void
    {
        $this->get('/oficina/materia-prima/retornos-packing')
            ->assertOk()
            ->assertSee('Registrar un bin')
            ->assertSee('Pendientes de regularizar')
            ->assertSee('Registros anteriores')
            ->assertSee('folio provisional', false);
    }
}
