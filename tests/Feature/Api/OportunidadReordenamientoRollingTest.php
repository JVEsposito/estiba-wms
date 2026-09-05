<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\ContenidoCamara;
use App\Enums\EstadoManiobraOperacional;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\FuenteHabilitacionAlmacenamiento;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\PrioridadOperacional;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPasoManiobra;
use App\Enums\TipoPlanOperacional;
use App\Enums\UsoBandaOperacional;
use App\Models\BandaOperacional;
use App\Models\Camara;
use App\Models\Folio;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Services\Camaras\ServicioBandasOperacionales;
use App\Services\Camaras\ServicioOportunidadReordenamiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OportunidadReordenamientoRollingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'planificador.mode' => 'guided',
            'planificador.generacion_automatica' => true,
            'planificador.compute' => 'tablet',
            'planificador.horizon' => 'rolling',
            'planificador.frontier_max' => 4,
        ]);
    }

    public function test_publica_reordenamiento_que_aprovecha_un_hueco_reciente(): void
    {
        $contexto = $this->crearContexto();
        $this->ubicarPerfil($contexto, 'PAL-257-A1', 'Cliente A', 1, 1);
        $this->ubicarPerfil($contexto, 'PAL-257-A2', 'Cliente A', 1, 2);
        $objetivo = $this->ubicarPerfil($contexto, 'PAL-257-B1', 'Cliente B', 1, 3);
        $this->ubicarPerfil($contexto, 'PAL-257-B2', 'Cliente B', 2, 1);
        $hueco = $contexto['posiciones']['2:2'];
        $movimientoId = (string) Str::uuid();

        $plan = $this->sincronizar($contexto, $hueco, $movimientoId);

        $this->assertNotNull($plan);
        $this->assertSame(TipoPlanOperacional::ReordenamientoCamara, $plan->tipo);
        $this->assertSame(PrioridadOperacional::Normal, $plan->prioridad);
        $this->assertSame('publicada', $plan->contexto['estado_decision']);
        $maniobra = $plan->maniobras()->sole();
        $this->assertTrue($maniobra->contexto['es_movimiento_oportunidad']);
        $this->assertSame($movimientoId, $maniobra->contexto['movimiento_disparador_id']);
        $this->assertSame([
            TipoPlanOperacional::ReordenamientoCamara->value,
            TipoPlanOperacional::MovimientoOportunidad->value,
        ], $maniobra->contexto['objetivos']);
        $this->assertGreaterThan(0, $maniobra->contexto['beneficio_neto']);
        $paso = $maniobra->pasos()->sole();
        $this->assertSame($objetivo->id, $paso->folio_id);
        $this->assertSame($hueco->id, $paso->posicion_destino_id);
        $this->assertSame(TipoPasoManiobra::MovimientoPermanente, $paso->tipo_paso_maniobra);
    }

    public function test_no_publica_un_movimiento_equivalente_o_cosmetico(): void
    {
        $contexto = $this->crearContexto();
        $this->ubicarPerfil($contexto, 'PAL-257-B1', 'Cliente B', 1, 1);
        $this->ubicarPerfil($contexto, 'PAL-257-B2', 'Cliente B', 1, 2);
        $this->ubicarPerfil($contexto, 'PAL-257-B3', 'Cliente B', 1, 3);
        $this->ubicarPerfil($contexto, 'PAL-257-B4', 'Cliente B', 2, 1);

        $plan = $this->sincronizar($contexto, $contexto['posiciones']['2:2']);

        $this->assertNull($plan);
        $this->assertDatabaseCount('planes_operacionales', 0);
        $this->assertDatabaseCount('tareas_movimiento', 0);
    }

    public function test_blockers_generan_extraccion_objetivo_y_retorno_a_profundidad_resultante(): void
    {
        $contexto = $this->crearContexto();
        $objetivo = $this->ubicarPerfil($contexto, 'PAL-257-B1', 'Cliente B', 1, 1);
        $blockerInterior = $this->ubicarPerfil($contexto, 'PAL-257-A1', 'Cliente A', 1, 2);
        $blockerExterior = $this->ubicarPerfil($contexto, 'PAL-257-A2', 'Cliente A', 1, 3);
        $this->ubicarPerfil($contexto, 'PAL-257-B2', 'Cliente B', 2, 1);

        $plan = $this->sincronizar($contexto, $contexto['posiciones']['2:2']);
        $maniobra = $plan->maniobras()->sole();
        $pasos = $maniobra->pasos()->get();

        $this->assertSame(5, $maniobra->costo_movimientos);
        $this->assertSame([
            TipoPasoManiobra::ExtraccionTemporal,
            TipoPasoManiobra::ExtraccionTemporal,
            TipoPasoManiobra::MovimientoPermanente,
            TipoPasoManiobra::RetornoBanda,
            TipoPasoManiobra::RetornoBanda,
        ], $pasos->pluck('tipo_paso_maniobra')->all());
        $this->assertSame([
            $blockerExterior->id,
            $blockerInterior->id,
            $objetivo->id,
            $blockerInterior->id,
            $blockerExterior->id,
        ], $pasos->pluck('folio_id')->all());
        $this->assertSame($contexto['posiciones']['1:1']->id, $pasos[3]->posicion_destino_id);
        $this->assertSame($contexto['posiciones']['1:2']->id, $pasos[4]->posicion_destino_id);
        $this->assertSame(2, $maniobra->reservasBandas()->count());
        $this->assertTrue($maniobra->contexto['cerrable']);
        $this->assertSame(2, $maniobra->contexto['blockers_retorno']);
    }

    public function test_conserva_postergada_la_mejora_si_el_pallet_tiene_una_labor_previa(): void
    {
        $contexto = $this->crearContexto();
        $this->ubicarPerfil($contexto, 'PAL-257-A1', 'Cliente A', 1, 1);
        $this->ubicarPerfil($contexto, 'PAL-257-A2', 'Cliente A', 1, 2);
        $objetivo = $this->ubicarPerfil($contexto, 'PAL-257-B1', 'Cliente B', 1, 3);
        $this->ubicarPerfil($contexto, 'PAL-257-B2', 'Cliente B', 2, 1);
        $planObligatorio = PlanOperacional::create([
            'temporada_id' => $contexto['temporada']->id,
            'tipo' => TipoPlanOperacional::AlmacenamientoPallet,
            'estado' => EstadoPlanOperacional::EnEjecucion,
            'prioridad' => PrioridadOperacional::Alta,
            'titulo' => 'Labor previa obligatoria',
            'creado_por_user_id' => $contexto['supervisor']->id,
            'programado_at' => now(),
        ]);
        TareaMovimiento::create([
            'plan_operacional_id' => $planObligatorio->id,
            'secuencia' => 1,
            'tipo_movimiento' => TipoMovimiento::Reubicacion,
            'estado' => EstadoTareaMovimiento::EnProceso,
            'prioridad' => PrioridadOperacional::Alta,
            'folio_id' => $objetivo->id,
            'camara_origen_id' => $contexto['camara']->id,
            'posicion_origen_id' => $contexto['posiciones']['1:3']->id,
            'camara_destino_id' => $contexto['camara']->id,
            'posicion_destino_id' => $contexto['posiciones']['3:1']->id,
            'iniciada_at' => now(),
        ]);

        $plan = $this->sincronizar($contexto, $contexto['posiciones']['2:2']);

        $this->assertSame('postergada', $plan->contexto['estado_decision']);
        $this->assertSame('labor_activa_previa', $plan->contexto['motivo_pendiente']);
        $this->assertSame($objetivo->id, $plan->contexto['candidato']['folio_objetivo_id']);
        $this->assertSame(0, $plan->maniobras()->count());
        $this->assertSame(EstadoTareaMovimiento::EnProceso, $planObligatorio->tareas()->sole()->estado);
    }

    public function test_shadow_registra_la_decision_sin_materializar_trabajo(): void
    {
        config(['planificador.mode' => 'shadow']);
        $contexto = $this->crearContexto();
        $this->ubicarPerfil($contexto, 'PAL-257-A1', 'Cliente A', 1, 1);
        $this->ubicarPerfil($contexto, 'PAL-257-A2', 'Cliente A', 1, 2);
        $this->ubicarPerfil($contexto, 'PAL-257-B1', 'Cliente B', 1, 3);
        $this->ubicarPerfil($contexto, 'PAL-257-B2', 'Cliente B', 2, 1);

        $plan = $this->sincronizar($contexto, $contexto['posiciones']['2:2']);

        $this->assertSame('shadow', $plan->contexto['estado_decision']);
        $this->assertTrue($plan->contexto['candidato']['es_movimiento_oportunidad']);
        $this->assertSame(0, $plan->maniobras()->count());
        $this->assertSame(0, $plan->tareas()->count());
    }

    public function test_off_preserva_el_flujo_historico_sin_crear_plan(): void
    {
        config(['planificador.mode' => 'off']);
        $contexto = $this->crearContexto();
        $this->ubicarPerfil($contexto, 'PAL-257-A1', 'Cliente A', 1, 1);
        $this->ubicarPerfil($contexto, 'PAL-257-A2', 'Cliente A', 1, 2);
        $this->ubicarPerfil($contexto, 'PAL-257-B1', 'Cliente B', 1, 3);
        $this->ubicarPerfil($contexto, 'PAL-257-B2', 'Cliente B', 2, 1);

        $plan = $this->sincronizar($contexto, $contexto['posiciones']['2:2']);

        $this->assertNull($plan);
        $this->assertDatabaseCount('planes_operacionales', 0);
        $this->assertDatabaseCount('tareas_movimiento', 0);
    }

    public function test_recalculo_reemplaza_una_oportunidad_pendiente_si_cambia_la_geometria(): void
    {
        $contexto = $this->crearContexto();
        $this->ubicarPerfil($contexto, 'PAL-257-A1', 'Cliente A', 1, 1);
        $this->ubicarPerfil($contexto, 'PAL-257-A2', 'Cliente A', 1, 2);
        $this->ubicarPerfil($contexto, 'PAL-257-B1', 'Cliente B', 1, 3);
        $this->ubicarPerfil($contexto, 'PAL-257-B2', 'Cliente B', 2, 1);
        $plan = $this->sincronizar($contexto, $contexto['posiciones']['2:2']);
        $anterior = $plan->maniobras()->sole();
        $this->ubicarPerfil($contexto, 'PAL-257-B3', 'Cliente B', 2, 2);

        $plan = $this->sincronizar($contexto, $contexto['posiciones']['2:3']);

        $this->assertSame(EstadoManiobraOperacional::Cancelada, $anterior->refresh()->estado);
        $actual = $plan->maniobras()
            ->where('estado', EstadoManiobraOperacional::Pendiente->value)
            ->sole();
        $this->assertNotSame($anterior->candidate_key, $actual->candidate_key);
        $this->assertSame(
            $contexto['posiciones']['2:3']->id,
            $actual->pasos()->where('tipo_paso_maniobra', TipoPasoManiobra::MovimientoPermanente->value)
                ->sole()
                ->posicion_destino_id,
        );
    }

    /** @return array<string, mixed> */
    private function crearContexto(): array
    {
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-257-'.Str::upper(Str::random(5)),
            'nombre' => 'Temporada oportunidad y reordenamiento',
            'fecha_inicio' => '2026-09-01',
            'activa' => true,
        ]);
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $camara = Camara::create([
            'codigo' => 'CAM-257-'.Str::upper(Str::random(5)),
            'nombre' => 'Cámara oportunidad y reordenamiento',
            'contenido' => ContenidoCamara::Productos,
            'estado' => 'activa',
            'cantidad_bandas' => 3,
            'posiciones_por_banda' => 3,
            'cantidad_niveles' => 1,
            'creado_por_user_id' => $supervisor->id,
            'actualizado_por_user_id' => $supervisor->id,
        ]);
        app(ServicioBandasOperacionales::class)->sincronizar($camara, $supervisor);
        BandaOperacional::query()
            ->where('camara_id', $camara->id)
            ->update(['usos_permitidos' => [UsoBandaOperacional::TransitoProductoTerminado->value]]);
        $posiciones = [];
        for ($banda = 1; $banda <= 3; $banda++) {
            for ($profundidad = 1; $profundidad <= 3; $profundidad++) {
                $posiciones["{$banda}:{$profundidad}"] = Posicion::create([
                    'camara_id' => $camara->id,
                    'banda' => $banda,
                    'posicion' => $profundidad,
                    'nivel' => 1,
                    'etiqueta' => sprintf('B%02d-P%02d-N1', $banda, $profundidad),
                ]);
            }
        }

        return compact('temporada', 'supervisor', 'camara', 'posiciones');
    }

    private function ubicarPerfil(
        array $contexto,
        string $numero,
        string $cliente,
        int $banda,
        int $profundidad,
    ): Folio {
        $folio = Folio::create([
            'temporada_id' => $contexto['temporada']->id,
            'numero_folio' => $numero,
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
            'fuente_habilitacion_almacenamiento' => FuenteHabilitacionAlmacenamiento::PrefrioAprobado,
            'fecha_ingreso' => now(),
            'activo' => true,
            'exportadora' => $cliente,
            'marca' => 'Marca común',
            'datos_externos' => ['envase' => 'Caja común'],
        ]);
        $posicion = $contexto['posiciones']["{$banda}:{$profundidad}"];
        UbicacionActual::withoutEvents(fn (): UbicacionActual => UbicacionActual::create([
            'folio_id' => $folio->id,
            'camara_id' => $contexto['camara']->id,
            'posicion_id' => $posicion->id,
            'ubicado_at' => now(),
        ]));

        return $folio;
    }

    private function sincronizar(
        array $contexto,
        Posicion $hueco,
        ?string $movimientoId = null,
    ): ?PlanOperacional {
        return app(ServicioOportunidadReordenamiento::class)->sincronizarCamara(
            $contexto['camara'],
            $contexto['supervisor'],
            [1, 2, 3],
            $hueco->id,
            $movimientoId ?? (string) Str::uuid(),
        );
    }
}
