<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Jobs\EjecutarRecalculoPendientePlanificador;
use App\Models\Carga;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Planificador\ServicioRecalculosPendientesPlanificador;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class RecalculosPendientesPlanificadorTest extends TestCase
{
    use RefreshDatabase;

    public function test_cambio_de_carga_queda_pendiente_hasta_que_el_worker_confirma_su_version(): void
    {
        $usuario = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $carga = Carga::create([
            'temporada_id' => Temporada::query()->where('activa', true)->firstOrFail()->id,
            'codigo' => 'CAR-PROY-001',
            'estado' => 'borrador',
            'creada_por_user_id' => $usuario->id,
            'actualizada_por_user_id' => $usuario->id,
        ]);
        $carga->update(['version' => 2]);
        $tipo = ServicioRecalculosPendientesPlanificador::CARGA;

        $this->assertDatabaseHas('recalculos_pendientes_planificador', [
            'tipo' => $tipo,
            'fuente_id' => $carga->id,
            'version_solicitada' => 1,
            'version_calculada' => 0,
        ]);

        app(ServicioRecalculosPendientesPlanificador::class)->ejecutar($tipo, $carga->id);

        $this->assertDatabaseHas('recalculos_pendientes_planificador', [
            'tipo' => $tipo,
            'fuente_id' => $carga->id,
            'version_solicitada' => 1,
            'version_calculada' => 1,
            'ultimo_error' => null,
        ]);

        $carga->update(['version' => 3]);
        $this->assertDatabaseHas('recalculos_pendientes_planificador', [
            'tipo' => $tipo,
            'fuente_id' => $carga->id,
            'version_solicitada' => 2,
            'version_calculada' => 1,
        ]);
    }

    public function test_una_mutacion_revertida_no_deja_solicitudes_huerfanas(): void
    {
        $usuario = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $carga = Carga::create([
            'temporada_id' => Temporada::query()->where('activa', true)->firstOrFail()->id,
            'codigo' => 'CAR-PROY-002',
            'estado' => 'borrador',
            'creada_por_user_id' => $usuario->id,
            'actualizada_por_user_id' => $usuario->id,
        ]);

        try {
            DB::transaction(function () use ($carga): void {
                $carga->update(['version' => 2]);
                throw new RuntimeException('Transacción abortada');
            });
        } catch (RuntimeException) {
            // El movimiento y la solicitud pendiente deben revertirse juntos.
        }

        $this->assertSame(1, $carga->fresh()->version);
        $this->assertDatabaseMissing('recalculos_pendientes_planificador', [
            'tipo' => ServicioRecalculosPendientesPlanificador::CARGA,
            'fuente_id' => $carga->id,
        ]);
    }

    public function test_un_fallo_del_calculo_conserva_la_solicitud_y_el_watchdog_la_reenvia(): void
    {
        $fuenteId = (string) Str::uuid();
        $tipo = ServicioRecalculosPendientesPlanificador::CARGA;
        $servicio = app(ServicioRecalculosPendientesPlanificador::class);
        $servicio->solicitar($tipo, $fuenteId);

        try {
            $servicio->ejecutar($tipo, $fuenteId);
            $this->fail('Se esperaba un error de recálculo.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseHas('recalculos_pendientes_planificador', [
                'fuente_id' => $fuenteId,
                'version_solicitada' => 1,
                'version_calculada' => 0,
            ]);
        }

        $this->assertSame(1, $servicio->salud()['fallidos']);
        $this->travel(6)->minutes();
        $this->assertSame(1, $servicio->salud()['atrasados']);
        $this->assertGreaterThanOrEqual(360, $servicio->salud()['mas_antiguo_segundos']);
        Queue::fake();
        config(['queue.default' => 'database']);
        $this->assertSame(1, $servicio->recuperar());
        Queue::assertPushed(EjecutarRecalculoPendientePlanificador::class, 1);

        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/administracion/planificador/salud')
            ->assertOk()
            ->assertJsonPath('data.proyecciones_pendientes.pendientes', 1)
            ->assertJsonPath('data.proyecciones_pendientes.fallidos', 1);
    }
}
