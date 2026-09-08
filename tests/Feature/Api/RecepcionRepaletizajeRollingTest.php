<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoIntegracionFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Camara;
use App\Models\Folio;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\Repaletizaje;
use App\Models\Temporada;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Services\Validacion\ServicioRepaletizaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecepcionRepaletizajeRollingTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_genera_objetivo_si_la_generacion_automatica_esta_apagada(): void
    {
        config(['planificador.generacion_automatica' => false]);
        [$temporada, $usuario] = $this->contexto();

        $this->registrar(
            $temporada,
            $usuario,
            'OFF',
            CondicionTermicaFolio::PrefrioAprobado,
        );

        $this->assertDatabaseCount('planes_operacionales', 0);
    }

    public function test_genera_retiro_rolling_con_origen_real_sin_carga_y_es_idempotente(): void
    {
        $this->habilitarGeneracion();
        [$temporada, $usuario] = $this->contexto();
        [$repa, $payload] = $this->registrar(
            $temporada,
            $usuario,
            'REAL',
            CondicionTermicaFolio::PrefrioAprobado,
            conUbicacion: true,
        );

        $repetido = app(ServicioRepaletizaje::class)->registrar($payload, $usuario);
        $plan = PlanOperacional::query()
            ->where('referencia_tipo', 'repaletizaje')
            ->where('referencia_id', $repa->id)
            ->firstOrFail();
        $tarea = $plan->tareas()->firstOrFail();
        $ubicacion = $repa->resultados()->firstOrFail()->folio->ubicacionActual;

        $this->assertSame($repa->id, $repetido->id);
        $this->assertSame('recepcion_repaletizaje', $plan->tipo->value);
        $this->assertSame('normal', $plan->prioridad->value);
        $this->assertSame('rolling', $plan->contexto['planner_horizon']);
        $this->assertSame('repaletizaje', $plan->contexto['origen_logico']);
        $this->assertTrue($plan->contexto['origen_repa_sin_blockers']);
        $this->assertSame(1, $plan->tareas()->count());
        $this->assertSame(1, PlanOperacional::query()
            ->where('referencia_tipo', 'repaletizaje')
            ->where('referencia_id', $repa->id)
            ->count());
        $this->assertSame('traslado_entre_camaras', $tarea->tipo_movimiento->value);
        $this->assertSame($ubicacion?->camara_id, $tarea->camara_origen_id);
        $this->assertSame($ubicacion?->posicion_id, $tarea->posicion_origen_id);
        $this->assertNull($tarea->camara_destino_id);
        $this->assertNull($tarea->posicion_destino_id);
        $this->assertSame($repa->id, $tarea->contexto['repaletizaje_id']);
        $this->assertTrue($tarea->contexto['origen_repa_sin_blockers']);

        $tarea->update([
            'estado' => EstadoTareaMovimiento::Completada,
            'responsable_user_id' => $usuario->id,
            'completada_at' => now(),
            'version' => $tarea->version + 1,
        ]);

        $this->assertSame('completado', $plan->refresh()->estado->value);
    }

    public function test_sin_ubicacion_fisica_crea_ubicacion_inicial_sin_preasignar_destino(): void
    {
        $this->habilitarGeneracion();
        [$temporada, $usuario] = $this->contexto();
        [$repa] = $this->registrar(
            $temporada,
            $usuario,
            'LOGICO',
            CondicionTermicaFolio::PrefrioAprobado,
        );

        $tarea = PlanOperacional::query()
            ->where('referencia_id', $repa->id)
            ->firstOrFail()
            ->tareas()
            ->firstOrFail();

        $this->assertSame('ubicacion_inicial', $tarea->tipo_movimiento->value);
        $this->assertNull($tarea->camara_origen_id);
        $this->assertNull($tarea->posicion_origen_id);
        $this->assertNull($tarea->camara_destino_id);
        $this->assertNull($tarea->posicion_destino_id);
        $this->assertSame('repaletizaje', $tarea->contexto['origen_logico']);
    }

    public function test_escala_todo_el_buffer_a_alta_desde_ocho_y_a_urgente_desde_diez_pallets(): void
    {
        $this->habilitarGeneracion();
        [$temporada, $usuario] = $this->contexto();

        foreach (range(1, 7) as $indice) {
            $this->registrar(
                $temporada,
                $usuario,
                sprintf('BUFFER-%02d', $indice),
                CondicionTermicaFolio::PrefrioAprobado,
            );
        }

        $this->assertSame(
            7,
            PlanOperacional::query()->where('prioridad', 'normal')->count(),
        );

        $this->registrar(
            $temporada,
            $usuario,
            'BUFFER-08',
            CondicionTermicaFolio::PrefrioAprobado,
        );

        $planesAltos = PlanOperacional::query()->get();
        $this->assertCount(8, $planesAltos);
        $this->assertTrue($planesAltos->every(
            fn (PlanOperacional $plan): bool => $plan->prioridad->value === 'alta'
                && $plan->contexto['buffer_repa_pallets_pendientes'] === 8
                && $plan->contexto['buffer_repa_umbral_alta'] === 8
                && $plan->contexto['buffer_repa_maximo'] === 10,
        ));
        $this->assertSame(
            8,
            $planesAltos->flatMap->tareas->where('prioridad.value', 'alta')->count(),
        );

        $this->registrar(
            $temporada,
            $usuario,
            'BUFFER-09',
            CondicionTermicaFolio::PrefrioAprobado,
        );
        $this->registrar(
            $temporada,
            $usuario,
            'BUFFER-10',
            CondicionTermicaFolio::PrefrioAprobado,
        );

        $planesUrgentes = PlanOperacional::query()->with('tareas')->get();
        $this->assertCount(10, $planesUrgentes);
        $this->assertTrue($planesUrgentes->every(
            fn (PlanOperacional $plan): bool => $plan->prioridad->value === 'urgente'
                && $plan->contexto['buffer_repa_prioridad'] === 'urgente'
                && $plan->contexto['buffer_repa_pallets_pendientes'] === 10,
        ));
        $this->assertSame(
            10,
            $planesUrgentes->flatMap->tareas->where('prioridad.value', 'urgente')->count(),
        );
    }

    public function test_desescala_las_labores_pendientes_cuando_el_movimiento_libera_el_buffer(): void
    {
        $this->habilitarGeneracion();
        config([
            'planificador.repa_buffer_high_from_pallets' => 2,
            'planificador.repa_buffer_max_pallets' => 3,
        ]);
        [$temporada, $usuario] = $this->contexto();

        foreach (range(1, 3) as $indice) {
            $this->registrar(
                $temporada,
                $usuario,
                "LIBERA-{$indice}",
                CondicionTermicaFolio::PrefrioAprobado,
            );
        }

        $tareas = PlanOperacional::query()
            ->with('tareas')
            ->orderBy('created_at')
            ->get()
            ->map->tareas
            ->flatten()
            ->values();
        $this->assertTrue($tareas->every(
            fn ($tarea): bool => $tarea->prioridad->value === 'urgente',
        ));

        $tareas[0]->update(['estado' => EstadoTareaMovimiento::Asumida]);
        $tareas[0]->update(['estado' => EstadoTareaMovimiento::EnProceso]);

        $restantes = PlanOperacional::query()
            ->whereKeyNot($tareas[0]->plan_operacional_id)
            ->with('tareas')
            ->get();
        $this->assertTrue($restantes->every(
            fn (PlanOperacional $plan): bool => $plan->prioridad->value === 'alta'
                && $plan->contexto['buffer_repa_pallets_pendientes'] === 2
                && $plan->tareas->first()->prioridad->value === 'alta',
        ));

        $tareas[1]->refresh()->update(['estado' => EstadoTareaMovimiento::Asumida]);
        $tareas[1]->refresh()->update(['estado' => EstadoTareaMovimiento::EnProceso]);

        $ultimo = PlanOperacional::query()
            ->whereKeyNot($tareas[0]->plan_operacional_id)
            ->whereKeyNot($tareas[1]->plan_operacional_id)
            ->with('tareas')
            ->sole();
        $this->assertSame('normal', $ultimo->prioridad->value);
        $this->assertSame(1, $ultimo->contexto['buffer_repa_pallets_pendientes']);
        $this->assertSame('normal', $ultimo->tareas->first()->prioridad->value);
    }

    public function test_excluye_resultados_saldo_y_pallets_pendientes_de_prefrio(): void
    {
        $this->habilitarGeneracion();
        [$temporada, $usuario] = $this->contexto();
        [$pendiente] = $this->registrar(
            $temporada,
            $usuario,
            'PEND',
            CondicionTermicaFolio::PendientePrefrio,
        );
        [$saldo] = $this->registrar(
            $temporada,
            $usuario,
            'SALDO',
            CondicionTermicaFolio::PrefrioAprobado,
            tipoResultado: TipoBulto::Saldo,
        );

        $this->assertFalse(PlanOperacional::query()
            ->where('referencia_tipo', 'repaletizaje')
            ->whereIn('referencia_id', [$pendiente->id, $saldo->id])
            ->exists());
    }

    public function test_anular_repa_cancela_su_objetivo_pendiente(): void
    {
        $this->habilitarGeneracion();
        [$temporada, $usuario] = $this->contexto();
        [$repa] = $this->registrar(
            $temporada,
            $usuario,
            'ANULA',
            CondicionTermicaFolio::PrefrioAprobado,
        );
        $plan = PlanOperacional::query()->where('referencia_id', $repa->id)->firstOrFail();

        app(ServicioRepaletizaje::class)->anular(
            $repa,
            (string) Str::uuid(),
            'Operación REPA ingresada por error.',
            $usuario,
        );

        $this->assertSame('cancelado', $plan->refresh()->estado->value);
        $this->assertSame('cancelada', $plan->tareas()->firstOrFail()->estado->value);
        $this->assertSame($usuario->id, $plan->cancelado_por_user_id);
    }

    /** @return array{Temporada, User} */
    private function contexto(): array
    {
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-276',
            'nombre' => 'Temporada PR 276',
            'fecha_inicio' => '2026-09-01',
            'activa' => true,
        ]);
        $usuario = User::factory()->create([
            'rol' => RolUsuario::Validador,
            'activo' => true,
        ]);

        return [$temporada, $usuario];
    }

    /**
     * @return array{Repaletizaje, array<string, mixed>}
     */
    private function registrar(
        Temporada $temporada,
        User $usuario,
        string $sufijo,
        CondicionTermicaFolio $condicion,
        bool $conUbicacion = false,
        TipoBulto $tipoResultado = TipoBulto::Pallet,
    ): array {
        $cantidadPrimera = 60;
        $cantidadSegunda = $tipoResultado === TipoBulto::Pallet ? 60 : 50;
        $primero = $this->folio($temporada, "SAL-{$sufijo}-1", $cantidadPrimera, $condicion);
        $segundo = $this->folio($temporada, "SAL-{$sufijo}-2", $cantidadSegunda, $condicion);

        if ($conUbicacion) {
            $camara = Camara::create([
                'codigo' => "CAM-{$sufijo}",
                'nombre' => "Cámara {$sufijo}",
            ]);
            $posicion = Posicion::create([
                'camara_id' => $camara->id,
                'banda' => 1,
                'posicion' => 1,
                'nivel' => 1,
                'etiqueta' => 'B01-P01-N1',
            ]);
            UbicacionActual::create([
                'folio_id' => $primero->id,
                'camara_id' => $camara->id,
                'posicion_id' => $posicion->id,
                'ubicado_at' => now(),
            ]);
        }

        $payload = [
            'operacion_id' => (string) Str::uuid(),
            'tipo_resultado' => $tipoResultado->value,
            'estrategia_folio' => 'nuevo',
            'numero_folio_resultante' => "RES-{$sufijo}",
            'cantidad_objetivo' => 120,
            'origenes' => [
                ['folio_id' => $primero->id, 'cantidad_aportada' => $cantidadPrimera],
                ['folio_id' => $segundo->id, 'cantidad_aportada' => $cantidadSegunda],
            ],
        ];

        return [
            app(ServicioRepaletizaje::class)->registrar($payload, $usuario),
            $payload,
        ];
    }

    private function folio(
        Temporada $temporada,
        string $numero,
        int $cantidad,
        CondicionTermicaFolio $condicion,
    ): Folio {
        return Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => $numero,
            'tipo_bulto' => TipoBulto::Saldo,
            'estado_operacional' => $condicion === CondicionTermicaFolio::PrefrioAprobado
                ? EstadoOperacionalFolio::PendienteUbicacion
                : EstadoOperacionalFolio::PendientePrefrio,
            'condicion_termica' => $condicion,
            'habilitacion_almacenamiento' => $condicion === CondicionTermicaFolio::PrefrioAprobado
                ? HabilitacionAlmacenamientoFolio::Habilitado
                : HabilitacionAlmacenamientoFolio::NoHabilitado,
            'fecha_ingreso' => now(),
            'activo' => true,
            'variedad' => 'Santina',
            'calibre' => '2J',
            'marca' => 'Marca 276',
            'exportadora' => 'Exportadora 276',
            'origen_sistema' => 'validacion',
            'identificador_externo' => (string) Str::uuid(),
            'estado_integracion' => EstadoIntegracionFolio::NoVinculado,
            'datos_externos' => [
                'especie' => 'Cereza',
                'categoria' => 'Exportación',
                'envase' => 'Caja 5 kg',
                'csg' => '111',
                'predio' => 'Predio 276',
                'cuartel' => 'Cuartel 276',
                'cantidad_cajas' => $cantidad,
            ],
        ]);
    }

    private function habilitarGeneracion(): void
    {
        config([
            'planificador.generacion_automatica' => true,
            'planificador.mode' => 'guided',
            'planificador.compute' => 'tablet',
        ]);
    }
}
