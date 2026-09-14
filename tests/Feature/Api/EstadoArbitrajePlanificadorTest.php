<?php

namespace Tests\Feature\Api;

use App\Jobs\RecalcularArbitrajePlanificador;
use App\Models\EstadoArbitrajePlanificador;
use App\Models\Temporada;
use App\Services\Planificador\ServicioEstadoArbitrajePlanificador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EstadoArbitrajePlanificadorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'planificador.mode' => 'guided',
            'planificador.compute' => 'tablet',
            'planificador.horizon' => 'rolling',
            'planificador.arbitraje_atrasado_segundos' => 300,
        ]);
    }

    public function test_calcula_en_cola_y_conserva_el_ultimo_ciclo_mientras_hay_cambios(): void
    {
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $servicio = app(ServicioEstadoArbitrajePlanificador::class);
        config(['queue.default' => 'database']);
        Queue::fake();

        $servicio->solicitar($temporada, 'estado_inicial');
        Queue::assertPushed(
            RecalcularArbitrajePlanificador::class,
            fn (RecalcularArbitrajePlanificador $job): bool => $job->temporadaId === $temporada->id,
        );
        app()->call([
            new RecalcularArbitrajePlanificador($temporada->id),
            'handle',
        ]);

        $actual = $servicio->consultar($temporada);
        $this->assertSame('actual', $actual['estado']);
        $this->assertTrue($actual['vigente']);
        $this->assertNotNull($actual['ciclo']);
        $this->assertDatabaseHas('estados_arbitraje_planificador', [
            'temporada_id' => $temporada->id,
            'version_solicitada' => 1,
            'version_calculada' => 1,
            'calculos_exitosos' => 1,
            'calculos_fallidos' => 0,
        ]);
        $this->assertDatabaseCount('ciclos_arbitraje_maniobras', 1);

        $servicio->solicitar($temporada, 'cambio_operacional');
        Queue::assertPushed(
            RecalcularArbitrajePlanificador::class,
            fn (RecalcularArbitrajePlanificador $job): bool => $job->temporadaId === $temporada->id,
        );

        $pendiente = $servicio->consultar($temporada);
        $this->assertSame('pendiente', $pendiente['estado']);
        $this->assertFalse($pendiente['vigente']);
        $this->assertSame($actual['ciclo']->id, $pendiente['ciclo']->id);

        $this->travel(301)->seconds();
        $this->assertSame('atrasado', $servicio->consultar($temporada)['estado']);
        $this->assertSame(1, EstadoArbitrajePlanificador::query()->sole()->version_calculada);
        $this->assertDatabaseCount('ciclos_arbitraje_maniobras', 1);
    }
}
