<?php

namespace Tests\Feature\Api;

use App\Models\PlanOperacional;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CiclosReferenciaPlanOperacionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_referencias_sin_ciclo_explicito_conservan_su_unicidad(): void
    {
        $plan = $this->crearPlan();
        $this->assertSame(1, $plan->refresh()->ciclo_referencia);

        $this->expectException(UniqueConstraintViolationException::class);
        $plan->replicate()->save();
    }

    public function test_rollback_rechaza_perder_ciclos_antes_de_modificar_el_esquema(): void
    {
        $primero = $this->crearPlan();
        $segundo = $primero->replicate();
        $segundo->ciclo_referencia = 2;
        $segundo->save();
        $migracion = require database_path('migrations/2026_09_06_190000_agregar_ciclos_referencia_planes_operacionales.php');

        try {
            $migracion->down();
            $this->fail('El rollback no debe eliminar la identidad de los ciclos históricos.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ciclos históricos', $exception->getMessage());
        }

        $this->assertSame(1, $primero->refresh()->ciclo_referencia);
        $this->assertSame(2, $segundo->refresh()->ciclo_referencia);
    }

    private function crearPlan(): PlanOperacional
    {
        return PlanOperacional::create([
            'temporada_id' => Temporada::query()->where('activa', true)->firstOrFail()->id,
            'tipo' => 'recepcion_tunel',
            'estado' => 'completado',
            'titulo' => 'Referencia histórica',
            'referencia_tipo' => 'proceso_prefrio',
            'referencia_id' => (string) Str::uuid(),
            'creado_por_user_id' => User::factory()->create()->id,
            'programado_at' => now(),
        ]);
    }
}
