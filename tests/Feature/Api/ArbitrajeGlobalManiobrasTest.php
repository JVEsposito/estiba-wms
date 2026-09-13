<?php

namespace Tests\Feature\Api;

use App\Enums\DecisionArbitrajeManiobra;
use App\Enums\EstadoManiobraOperacional;
use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\PrioridadOperacional;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPlanOperacional;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\ManiobraOperacional;
use App\Models\Posicion;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Camaras\ServicioBandasOperacionales;
use App\Services\Estiba\ServicioPlanesOperacionales;
use App\Services\Planificador\ServicioArbitrajeManiobras;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArbitrajeGlobalManiobrasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'planificador.mode' => 'guided',
            'planificador.compute' => 'server',
            'planificador.horizon' => 'rolling',
            'planificador.frontier_max' => 4,
            'planificador.maniobras_simultaneas_max' => 3,
        ]);
    }

    public function test_prioridad_y_objetivo_dominan_el_beneficio_y_el_ciclo_es_idempotente(): void
    {
        $contexto = $this->crearContexto();
        $normal = $this->crearManiobra(
            $contexto,
            0,
            TipoPlanOperacional::ConcentracionCarga,
            PrioridadOperacional::Normal,
            1_000_000,
        );
        $urgente = $this->crearManiobra(
            $contexto,
            1,
            TipoPlanOperacional::ConcentracionCarga,
            PrioridadOperacional::Urgente,
            5_000,
        );
        $retenido = $this->crearManiobra(
            $contexto,
            2,
            TipoPlanOperacional::SegregacionRetenido,
            PrioridadOperacional::Critica,
            500,
        );
        $despacho = $this->crearManiobra(
            $contexto,
            3,
            TipoPlanOperacional::DespachoDirecto,
            PrioridadOperacional::Critica,
            100,
        );
        $emergencia = $this->crearManiobra(
            $contexto,
            4,
            TipoPlanOperacional::EvacuacionEmergencia,
            PrioridadOperacional::Critica,
            0,
        );

        $servicio = app(ServicioArbitrajeManiobras::class);
        $ciclo = $servicio->arbitrar($contexto['temporada']);
        $decisiones = $ciclo->decisiones->keyBy('maniobra_operacional_id');

        $this->assertSame(
            DecisionArbitrajeManiobra::Seleccionada,
            $decisiones[$emergencia->id]->decision,
        );
        $this->assertSame(
            DecisionArbitrajeManiobra::Seleccionada,
            $decisiones[$despacho->id]->decision,
        );
        $this->assertSame(
            DecisionArbitrajeManiobra::Seleccionada,
            $decisiones[$retenido->id]->decision,
        );
        $this->assertSame(
            DecisionArbitrajeManiobra::Alternativa,
            $decisiones[$urgente->id]->decision,
        );
        $this->assertSame(
            DecisionArbitrajeManiobra::FueraFrontera,
            $decisiones[$normal->id]->decision,
        );
        $this->assertSame(
            [$emergencia->id, $despacho->id, $retenido->id, $urgente->id],
            $servicio->idsPublicables($ciclo)->all(),
        );

        $repetido = $servicio->arbitrar($contexto['temporada']);
        $this->assertSame($ciclo->id, $repetido->id);
        $this->assertDatabaseCount('ciclos_arbitraje_maniobras', 1);
        $this->assertDatabaseCount('decisiones_arbitraje_maniobras', 5);

        $planEmergencia = $emergencia->planOperacional()->firstOrFail();
        $planEmergencia->update([
            'estado' => EstadoPlanOperacional::Pausado,
            'version' => $planEmergencia->version + 1,
        ]);
        $actualizado = $servicio->arbitrar($contexto['temporada']);

        $this->assertNotSame($ciclo->id, $actualizado->id);
        $this->assertSame(
            DecisionArbitrajeManiobra::FueraFrontera,
            $actualizado->decisiones
                ->firstWhere('maniobra_operacional_id', $emergencia->id)
                ?->decision,
        );
        $this->assertDatabaseCount('ciclos_arbitraje_maniobras', 2);
        $this->assertDatabaseCount('decisiones_arbitraje_maniobras', 10);
    }

    public function test_bandeja_publica_solo_compatibles_y_servidor_protege_la_alternativa(): void
    {
        $contexto = $this->crearContexto();
        $principal = $this->crearManiobra(
            $contexto,
            0,
            TipoPlanOperacional::ConcentracionCarga,
            PrioridadOperacional::Alta,
            500,
            0,
        );
        $conflictiva = $this->crearManiobra(
            $contexto,
            1,
            TipoPlanOperacional::ConcentracionCarga,
            PrioridadOperacional::Alta,
            400,
            0,
        );
        $segunda = $this->crearManiobra(
            $contexto,
            2,
            TipoPlanOperacional::ConcentracionCarga,
            PrioridadOperacional::Alta,
            300,
            1,
        );
        $tercera = $this->crearManiobra(
            $contexto,
            3,
            TipoPlanOperacional::ConcentracionCarga,
            PrioridadOperacional::Alta,
            200,
            2,
        );
        $alternativa = $this->crearManiobra(
            $contexto,
            4,
            TipoPlanOperacional::ConcentracionCarga,
            PrioridadOperacional::Alta,
            100,
            3,
        );

        $respuesta = $this->conToken($contexto['token'])
            ->getJson('/api/tareas-movimiento?asignacion=disponibles')
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('arbitraje.capacidad_ejecucion', 3)
            ->assertJsonPath('arbitraje.frontera_max', 4)
            ->assertJsonPath('arbitraje.seleccionadas', 3)
            ->assertJsonPath('arbitraje.alternativas', 1)
            ->assertJsonPath('data.0.maniobra.id', $principal->id)
            ->assertJsonPath('data.0.maniobra.arbitraje.decision', 'seleccionada')
            ->assertJsonPath('data.1.maniobra.id', $segunda->id)
            ->assertJsonPath('data.2.maniobra.id', $tercera->id)
            ->assertJsonPath('data.3.maniobra.id', $alternativa->id)
            ->assertJsonPath('data.3.maniobra.arbitraje.decision', 'alternativa');

        $this->assertNotContains(
            $conflictiva->id,
            collect($respuesta->json('data'))->pluck('maniobra.id')->all(),
        );
        $decisionConflictiva = $conflictiva->ultimaDecisionArbitraje()->firstOrFail();
        $this->assertSame(
            DecisionArbitrajeManiobra::ExcluidaConflicto,
            $decisionConflictiva->decision,
        );
        $this->assertSame(
            $principal->id,
            $decisionConflictiva->conflictos[0]['maniobra_id'],
        );

        $tareaAlternativa = $alternativa->pasos()->sole();
        $this->conToken($contexto['token'])
            ->postJson("/api/tareas-movimiento/{$tareaAlternativa->id}/asumir")
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');
    }

    public function test_realidad_fisica_iniciada_conserva_el_recurso_ante_una_prioridad_mayor(): void
    {
        $contexto = $this->crearContexto();
        $iniciada = $this->crearManiobra(
            $contexto,
            0,
            TipoPlanOperacional::ConcentracionCarga,
            PrioridadOperacional::Normal,
            100,
            0,
        );
        $iniciada->update([
            'estado' => EstadoManiobraOperacional::EnEjecucion,
            'version' => $iniciada->version + 1,
        ]);
        $pasoIniciado = $iniciada->pasos()->sole();
        $pasoIniciado->update([
            'estado' => EstadoTareaMovimiento::EnProceso,
            'version' => $pasoIniciado->version + 1,
        ]);
        $urgente = $this->crearManiobra(
            $contexto,
            1,
            TipoPlanOperacional::EvacuacionEmergencia,
            PrioridadOperacional::Critica,
            10_000,
            0,
        );

        $ciclo = app(ServicioArbitrajeManiobras::class)
            ->arbitrar($contexto['temporada']);
        $decisiones = $ciclo->decisiones->keyBy('maniobra_operacional_id');

        $this->assertSame(
            DecisionArbitrajeManiobra::EnEjecucion,
            $decisiones[$iniciada->id]->decision,
        );
        $this->assertSame(
            DecisionArbitrajeManiobra::ExcluidaConflicto,
            $decisiones[$urgente->id]->decision,
        );
        $this->assertSame(
            $iniciada->id,
            $decisiones[$urgente->id]->conflictos[0]['maniobra_id'],
        );
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    private function crearManiobra(
        array $contexto,
        int $indiceFolio,
        TipoPlanOperacional $tipo,
        PrioridadOperacional $prioridad,
        int $beneficio,
        ?int $indicePosicion = null,
    ): ManiobraOperacional {
        $indicePosicion ??= $indiceFolio;
        $plan = app(ServicioPlanesOperacionales::class)->crear(
            temporada: $contexto['temporada'],
            tipo: $tipo,
            titulo: "Objetivo arbitraje {$indiceFolio}",
            creadoPor: $contexto['supervisor'],
            tareas: [[
                'folio_id' => $contexto['folios'][$indiceFolio]->id,
                'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
                'camara_destino_id' => $contexto['camara']->id,
                'posicion_destino_id' => $contexto['posiciones'][$indicePosicion]->id,
                'contexto' => [
                    'beneficio_estimado' => $beneficio,
                    'riesgo_operacional' => 0,
                ],
            ]],
            prioridad: $prioridad,
            contexto: ['planner_horizon' => 'rolling'],
        );

        return $plan->maniobras()->sole();
    }

    /** @return array<string, mixed> */
    private function crearContexto(): array
    {
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-ARB-2026',
            'nombre' => 'Temporada arbitraje global',
            'fecha_inicio' => '2026-09-01',
            'activa' => true,
        ]);
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $camarero = User::factory()->create([
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-ARB-01',
            'nombre' => 'Tablet arbitraje 01',
        ]);
        $token = $camarero
            ->crearTokenParaDispositivo($dispositivo, 'tablet-arbitraje')
            ->plainTextToken;
        $camara = Camara::create([
            'codigo' => 'CAM-ARB-01',
            'nombre' => 'Cámara arbitraje',
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 6,
            'cantidad_niveles' => 1,
        ]);
        app(ServicioBandasOperacionales::class)->sincronizar($camara->refresh(), $supervisor);
        $posiciones = [];
        $folios = [];

        for ($indice = 0; $indice < 6; $indice++) {
            $posiciones[] = Posicion::create([
                'camara_id' => $camara->id,
                'banda' => 1,
                'posicion' => $indice + 1,
                'nivel' => 1,
                'etiqueta' => sprintf('B01-P%02d-N1', $indice + 1),
            ]);
            $folios[] = Folio::create([
                'temporada_id' => $temporada->id,
                'numero_folio' => sprintf('ARB-%03d', $indice + 1),
                'tipo_bulto' => TipoBulto::Pallet,
                'fecha_ingreso' => now()->subMinutes($indice),
            ]);
        }

        return compact(
            'temporada',
            'supervisor',
            'camarero',
            'dispositivo',
            'token',
            'camara',
            'posiciones',
            'folios',
        );
    }

    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
