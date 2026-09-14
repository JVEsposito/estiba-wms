<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPasoManiobra;
use App\Enums\TipoPlanOperacional;
use App\Models\Camara;
use App\Models\CustodiaTemporalManiobra;
use App\Models\DiscrepanciaManiobra;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\ManiobraOperacional;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\ReservaTareaMovimiento;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaludPlanificadorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_publica_snapshot_operacional_filtrable_solo_para_roles_autorizados(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);

        $this->getJson('/api/administracion/planificador/salud')->assertUnauthorized();
        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/administracion/planificador/salud')
            ->assertForbidden();

        $respuesta = $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/administracion/planificador/salud')
            ->assertOk()
            ->assertJsonPath('data.despliegue.mode_global', 'off')
            ->assertJsonPath('data.salud.estado', 'saludable')
            ->assertJsonPath('data.metricas.planes.total', 0)
            ->assertJsonPath('data.metricas.ejecucion.movimientos_por_pallet', 0)
            ->assertJsonPath('data.metricas.arbitraje.ciclos_nuevos', 0)
            ->assertJsonPath('data.metricas.arbitraje.ultimo_ciclo', null);

        $this->assertSame(64, strlen((string) $respuesta->json('data.snapshot_version')));
    }

    public function test_agrega_metricas_y_detecta_riesgos_del_planner_en_una_camara(): void
    {
        config([
            'planificador.mode' => 'guided',
            'planificador.compute' => 'tablet',
            'planificador.horizon' => 'rolling',
            'planificador.generacion_automatica' => true,
            'planificador.rollout_camaras' => ['CAM-MET-01'],
            'planificador.tarea_estancada_minutos' => 30,
        ]);
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $camara = Camara::create([
            'codigo' => 'CAM-MET-01',
            'nombre' => 'Cámara métricas',
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 2,
            'cantidad_niveles' => 1,
        ]);
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => 'B01-P01-N1',
        ]);
        $folio = Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => 'PAL-MET-001',
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TAB-MET-01',
            'nombre' => 'Tablet métricas',
        ]);
        $plan = PlanOperacional::create([
            'temporada_id' => $temporada->id,
            'tipo' => TipoPlanOperacional::ReordenamientoCamara,
            'estado' => 'en_ejecucion',
            'prioridad' => 'normal',
            'titulo' => 'Plan de métricas',
            'referencia_tipo' => 'camara_reordenamiento',
            'referencia_id' => $camara->id,
            'creado_por_user_id' => $administrador->id,
            'programado_at' => now(),
            'iniciado_at' => now()->subHour(),
        ]);
        $maniobra = ManiobraOperacional::create([
            'plan_operacional_id' => $plan->id,
            'creado_por_user_id' => $administrador->id,
            'estado' => 'completada',
            'prioridad' => 'normal',
            'candidate_key' => 'metrica-001',
            'titulo' => 'Resolver blockers',
            'costo_movimientos' => 3,
            'beneficio_estimado' => 800,
            'riesgo_operacional' => 20,
            'contexto' => [
                'blockers' => 2,
                'blockers_retorno' => 1,
                'es_movimiento_oportunidad' => true,
            ],
            'completada_at' => now(),
        ]);
        $tarea = TareaMovimiento::create([
            'plan_operacional_id' => $plan->id,
            'maniobra_operacional_id' => $maniobra->id,
            'secuencia' => 1,
            'secuencia_maniobra' => 1,
            'tipo_movimiento' => TipoMovimiento::Reubicacion,
            'tipo_paso_maniobra' => TipoPasoManiobra::ExtraccionTemporal,
            'estado' => 'en_proceso',
            'prioridad' => 'normal',
            'folio_id' => $folio->id,
            'camara_origen_id' => $camara->id,
            'posicion_origen_id' => $posicion->id,
            'iniciada_at' => now()->subMinutes(31),
        ]);
        ReservaTareaMovimiento::create([
            'tarea_movimiento_id' => $tarea->id,
            'bloqueo_tarea_id' => $tarea->id,
            'estado' => 'activa',
            'user_id' => $administrador->id,
            'dispositivo_id' => $dispositivo->id,
            'reservada_at' => now()->subMinutes(40),
            'renovada_at' => now()->subMinutes(40),
            'vence_at' => now()->subMinutes(30),
        ]);
        CustodiaTemporalManiobra::create([
            'maniobra_operacional_id' => $maniobra->id,
            'folio_id' => $folio->id,
            'tarea_extraccion_id' => $tarea->id,
            'camara_origen_id' => $camara->id,
            'posicion_origen_id' => $posicion->id,
            'banda_origen' => 1,
            'posicion_origen' => 1,
            'nivel_origen' => 1,
            'estado' => 'activa',
            'bloqueo_folio_id' => $folio->id,
            'user_id' => $administrador->id,
            'dispositivo_id' => $dispositivo->id,
            'extraido_at' => now()->subMinutes(31),
        ]);
        DiscrepanciaManiobra::create([
            'maniobra_operacional_id' => $maniobra->id,
            'tarea_movimiento_id' => $tarea->id,
            'folio_id' => $folio->id,
            'tipo' => 'destino_no_coincide',
            'estado' => 'abierta',
            'reportada_por_user_id' => $administrador->id,
            'dispositivo_id' => $dispositivo->id,
            'reportada_at' => now(),
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->getJson("/api/administracion/planificador/salud?camara_id={$camara->id}")
            ->assertOk()
            ->assertJsonPath('data.despliegue.rollout_limitado', true)
            ->assertJsonPath('data.despliegue.camaras.0.mode', 'guided')
            ->assertJsonPath('data.metricas.planes.total', 1)
            ->assertJsonPath('data.metricas.maniobras.total', 1)
            ->assertJsonPath('data.metricas.maniobras.blockers', 2)
            ->assertJsonPath('data.metricas.maniobras.blockers_con_retorno', 1)
            ->assertJsonPath('data.metricas.maniobras.oportunidades_aprovechadas', 1)
            ->assertJsonPath('data.metricas.tareas.extracciones_temporales', 1)
            ->assertJsonPath('data.metricas.reservas.por_estado.activa', 1)
            ->assertJsonPath('data.metricas.discrepancias.por_estado.abierta', 1)
            ->assertJsonPath('data.salud.estado', 'critico')
            ->assertJsonPath('data.salud.riesgos.leases_vencidos_activos', 1)
            ->assertJsonPath('data.salud.riesgos.tareas_estancadas', 1)
            ->assertJsonPath('data.salud.riesgos.maniobras_completadas_con_custodia', 1);
    }

    public function test_rechaza_ventanas_mayores_a_siete_dias(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/administracion/planificador/salud?desde=2026-01-01&hasta=2026-01-09')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('desde');
    }

    public function test_observa_arbitraje_shadow_conflictos_y_rollout_sin_materializar(): void
    {
        config([
            'planificador.mode' => 'shadow',
            'planificador.compute' => 'tablet',
            'planificador.horizon' => 'rolling',
            'planificador.generacion_automatica' => false,
            'planificador.rollout_camaras' => ['CAM-SHADOW-DIRIGIDA'],
            'planificador.frontier_max' => 4,
            'planificador.maniobras_simultaneas_max' => 3,
        ]);
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $camaraDirigida = Camara::create([
            'codigo' => 'CAM-SHADOW-DIRIGIDA',
            'nombre' => 'Cámara shadow dirigida',
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 2,
            'cantidad_niveles' => 1,
        ]);
        $camaraFuera = Camara::create([
            'codigo' => 'CAM-SHADOW-FUERA',
            'nombre' => 'Cámara shadow fuera',
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);
        $folioCompartido = Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => 'PAL-SHADOW-COMPARTIDO',
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
        ]);
        $folioFuera = Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => 'PAL-SHADOW-FUERA',
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
        ]);
        $crearManiobra = function (
            string $sufijo,
            Folio $folio,
            Camara $camara,
            int $beneficio,
        ) use ($administrador, $temporada): void {
            $plan = PlanOperacional::create([
                'temporada_id' => $temporada->id,
                'tipo' => TipoPlanOperacional::ConcentracionCarga,
                'estado' => 'en_ejecucion',
                'prioridad' => 'normal',
                'titulo' => "Plan shadow {$sufijo}",
                'creado_por_user_id' => $administrador->id,
                'programado_at' => now(),
                'iniciado_at' => now(),
                'contexto' => ['planner_horizon' => 'rolling'],
            ]);
            $maniobra = ManiobraOperacional::create([
                'plan_operacional_id' => $plan->id,
                'creado_por_user_id' => $administrador->id,
                'estado' => 'pendiente',
                'prioridad' => 'normal',
                'candidate_key' => "shadow-{$sufijo}",
                'titulo' => "Maniobra shadow {$sufijo}",
                'costo_movimientos' => 1,
                'beneficio_estimado' => $beneficio,
                'riesgo_operacional' => 0,
            ]);
            TareaMovimiento::create([
                'plan_operacional_id' => $plan->id,
                'maniobra_operacional_id' => $maniobra->id,
                'secuencia' => 1,
                'secuencia_maniobra' => 1,
                'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
                'estado' => 'pendiente',
                'prioridad' => 'normal',
                'folio_id' => $folio->id,
                'camara_destino_id' => $camara->id,
            ]);
        };
        $crearManiobra('principal', $folioCompartido, $camaraDirigida, 300);
        $crearManiobra('conflicto', $folioCompartido, $camaraDirigida, 200);
        $crearManiobra('fuera', $folioFuera, $camaraFuera, 10_000);
        $consulta = http_build_query([
            'desde' => now()->subHour()->toIso8601String(),
            'hasta' => now()->addMinute()->toIso8601String(),
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->getJson("/api/administracion/planificador/salud?{$consulta}")
            ->assertOk()
            ->assertJsonPath('data.despliegue.mode_global', 'shadow')
            ->assertJsonPath('data.metricas.arbitraje.unidad_ciclo', 'estado_nuevo')
            ->assertJsonPath('data.metricas.arbitraje.ciclos_nuevos', 1)
            ->assertJsonPath('data.metricas.arbitraje.decisiones_total', 3)
            ->assertJsonPath('data.metricas.arbitraje.maniobras_unicas', 3)
            ->assertJsonPath('data.metricas.arbitraje.por_decision.seleccionada', 1)
            ->assertJsonPath('data.metricas.arbitraje.por_decision.excluida_conflicto', 1)
            ->assertJsonPath('data.metricas.arbitraje.por_decision.fuera_rollout', 1)
            ->assertJsonPath('data.metricas.arbitraje.conflictos.maniobras_excluidas', 1)
            ->assertJsonPath('data.metricas.arbitraje.conflictos.por_recurso.folio', 1)
            ->assertJsonPath('data.metricas.arbitraje.ultimo_ciclo.por_decision.fuera_rollout', 1);

        $this->actingAs($administrador, 'sanctum')
            ->getJson("/api/administracion/planificador/salud?{$consulta}")
            ->assertOk()
            ->assertJsonPath('data.metricas.arbitraje.ciclos_nuevos', 1);
        $this->assertDatabaseCount('ciclos_arbitraje_maniobras', 1);
    }
}
