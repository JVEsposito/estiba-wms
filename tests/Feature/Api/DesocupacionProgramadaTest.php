<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\ContenidoCamara;
use App\Enums\EstadoCarga;
use App\Enums\EstadoCargaFolio;
use App\Enums\EstadoManiobraOperacional;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoRetencionOperacional;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\FuenteHabilitacionAlmacenamiento;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\ModoBandaOperacional;
use App\Enums\PrioridadCarga;
use App\Enums\PrioridadOperacional;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoPasoManiobra;
use App\Enums\TipoPlanOperacional;
use App\Enums\UsoBandaOperacional;
use App\Models\BandaOperacional;
use App\Models\Camara;
use App\Models\Carga;
use App\Models\CargaFolio;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\ReservaCargaFolio;
use App\Models\RetencionOperacionalFolio;
use App\Models\SesionEstiba;
use App\Models\Temporada;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Observers\ReplanificarDesocupacionMovimientoObserver;
use App\Services\Camaras\ServicioBandasOperacionales;
use App\Services\Camaras\ServicioDesocupacionProgramada;
use App\Services\Estiba\ServicioMovimientoEstiba;
use App\Services\Estiba\ServicioPlanesOperacionales;
use App\Services\Estiba\ServicioSesionEstiba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DesocupacionProgramadaTest extends TestCase
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

    public function test_recomienda_primero_la_camara_elegible_de_menor_costo(): void
    {
        $contexto = $this->crearContexto();
        $otra = $this->crearCamara($contexto['supervisor'], 'CAM-258-B', 3);
        $contexto['destino']->bandasOperacionales()->update([
            'modo' => ModoBandaOperacional::Bloqueada,
            'motivo_estado' => 'Fuera de la recomendación de prueba.',
        ]);
        $this->ubicar($contexto, $contexto['origen'], 1, 'PAL-258-A1');
        $this->ubicar($contexto, $otra, 1, 'PAL-258-B1');
        $this->ubicar($contexto, $otra, 2, 'PAL-258-B2');

        $respuesta = $this->actingAs($contexto['supervisor'], 'sanctum')
            ->getJson('/api/desocupaciones-camara/candidatas')
            ->assertOk();

        $this->assertSame($contexto['origen']->id, $respuesta->json('data.0.camara.id'));
        $this->assertSame(1, $respuesta->json('data.0.costo_movimientos_estimado'));
        $this->assertTrue($respuesta->json('data.0.elegible'));
    }

    public function test_inicio_bloquea_ingresos_y_publica_solo_el_pallet_accesible(): void
    {
        $contexto = $this->crearContexto();
        $interior = $this->ubicar($contexto, $contexto['origen'], 1, 'PAL-258-INTERIOR');
        $exterior = $this->ubicar($contexto, $contexto['origen'], 2, 'PAL-258-EXTERIOR');

        $respuesta = $this->actingAs($contexto['supervisor'], 'sanctum')
            ->postJson("/api/desocupaciones-camara/{$contexto['origen']->id}", [
                'motivo' => 'Apagado programado por mantención.',
            ])
            ->assertOk()
            ->assertJsonPath('data.tipo', TipoPlanOperacional::DesocupacionCamara->value)
            ->assertJsonPath('data.prioridad', PrioridadOperacional::Alta->value)
            ->assertJsonPath('data.contexto.estado_desocupacion', 'publicada')
            ->assertJsonPath('data.contexto.pallets_restantes', 2)
            ->assertJsonPath('data.contexto.porcentaje_actual', 0);

        $this->assertSame(
            ModoBandaOperacional::EnVaciado,
            $contexto['origen']->bandasOperacionales()->sole()->modo,
        );
        $planId = $respuesta->json('data.id');
        $tarea = $exterior->tareasMovimiento()->where('plan_operacional_id', $planId)->sole();
        $this->assertSame($exterior->id, $tarea->folio_id);
        $this->assertNotSame($interior->id, $tarea->folio_id);
        $this->assertSame($contexto['destino']->id, $tarea->camara_destino_id);
        $this->assertSame(TipoPasoManiobra::MovimientoPermanente, $tarea->tipo_paso_maniobra);
        $this->assertTrue($tarea->contexto['destino_precalculado_inmutable']);
    }

    public function test_recalcula_tras_cada_movimiento_y_completa_solo_al_cien_por_ciento(): void
    {
        $contexto = $this->crearContexto();
        $this->ubicar($contexto, $contexto['origen'], 1, 'PAL-258-ROLLING-1');
        $this->ubicar($contexto, $contexto['origen'], 2, 'PAL-258-ROLLING-2');
        $plan = app(ServicioDesocupacionProgramada::class)->iniciar(
            $contexto['origen'],
            $contexto['supervisor'],
            'Vaciado rolling completo.',
        );
        [$operador, $dispositivo] = $this->crearOperador();
        $sesionOrigen = app(ServicioSesionEstiba::class)->abrir(
            $contexto['origen'],
            $operador,
            $dispositivo,
        );
        $sesionDestino = app(ServicioSesionEstiba::class)->abrir(
            $contexto['destino'],
            $operador,
            $dispositivo,
        );

        $this->ejecutarSiguiente($plan, $operador, $dispositivo, $sesionOrigen, $sesionDestino);
        $this->assertSame(EstadoPlanOperacional::EnEjecucion, $plan->refresh()->estado);
        $this->assertSame(50, $plan->contexto['porcentaje_actual']);
        $this->assertSame(1, $plan->contexto['pallets_restantes']);

        $this->ejecutarSiguiente($plan, $operador, $dispositivo, $sesionOrigen, $sesionDestino);
        $this->assertSame(EstadoPlanOperacional::Completado, $plan->refresh()->estado);
        $this->assertSame(100, $plan->contexto['porcentaje_actual']);
        $this->assertSame(0, $plan->contexto['pallets_restantes']);
        $this->assertTrue($plan->contexto['lista_para_apagar']);
        $this->assertSame(2, $plan->maniobras()->count());
        $this->assertSame(2, $plan->maniobras()
            ->where('estado', EstadoManiobraOperacional::Completada->value)
            ->count());
        $this->assertSame(
            ModoBandaOperacional::EnVaciado,
            $contexto['origen']->bandasOperacionales()->sole()->modo,
        );
    }

    public function test_sin_destino_compatible_conserva_el_objetivo_sin_publicar_trabajo_incompleto(): void
    {
        $contexto = $this->crearContexto();
        $this->ubicar($contexto, $contexto['origen'], 1, 'PAL-258-SIN-DESTINO');
        $contexto['destino']->bandasOperacionales()->sole()->update([
            'usos_permitidos' => [UsoBandaOperacional::Inspeccion->value],
        ]);

        $plan = app(ServicioDesocupacionProgramada::class)->iniciar(
            $contexto['origen'],
            $contexto['supervisor'],
            'Esperar capacidad compatible.',
        );

        $this->assertSame('pendiente', $plan->contexto['estado_desocupacion']);
        $this->assertSame('sin_destino_compatible', $plan->contexto['motivo_pendiente']);
        $this->assertSame(0, $plan->tareas()->count());
        $this->assertSame(1, $plan->contexto['pallets_restantes']);
    }

    public function test_preserva_la_carga_prefiriendo_una_banda_con_pallets_de_la_misma_carga(): void
    {
        $contexto = $this->crearContexto();
        $destinoCarga = $this->crearCamara($contexto['supervisor'], 'ZZ-CAM-258-CARGA', 3);
        $objetivo = $this->ubicar(
            $contexto,
            $contexto['origen'],
            1,
            'PAL-258-CARGA-1',
            'Cliente carga',
        );
        $companero = $this->ubicar(
            $contexto,
            $destinoCarga,
            1,
            'PAL-258-CARGA-2',
            'Cliente carga',
        );
        $this->ubicar(
            $contexto,
            $contexto['destino'],
            1,
            'PAL-258-AFINIDAD-1',
            'Cliente carga',
        );
        $this->ubicar(
            $contexto,
            $contexto['destino'],
            2,
            'PAL-258-AFINIDAD-2',
            'Cliente carga',
        );
        $carga = Carga::create([
            'temporada_id' => $contexto['temporada']->id,
            'codigo' => 'CAR-258-000001',
            'estado' => EstadoCarga::Pendiente,
            'prioridad' => PrioridadCarga::Alta,
            'version' => 1,
            'creada_por_user_id' => $contexto['supervisor']->id,
            'actualizada_por_user_id' => $contexto['supervisor']->id,
            'publicada_por_user_id' => $contexto['supervisor']->id,
            'publicada_at' => now(),
        ]);
        foreach ([$objetivo, $companero] as $folio) {
            $asignacion = CargaFolio::create([
                'carga_id' => $carga->id,
                'folio_id' => $folio->id,
                'estado' => EstadoCargaFolio::Pendiente,
                'asignado_por_user_id' => $contexto['supervisor']->id,
                'asignado_at' => now(),
            ]);
            ReservaCargaFolio::create([
                'folio_id' => $folio->id,
                'carga_folio_id' => $asignacion->id,
            ]);
        }

        $plan = app(ServicioDesocupacionProgramada::class)->iniciar(
            $contexto['origen'],
            $contexto['supervisor'],
            'Preservar la carga durante el vaciado.',
        );
        $tarea = $plan->tareas()->sole();

        $this->assertSame($destinoCarga->id, $tarea->camara_destino_id);
        $this->assertSame($carga->id, $tarea->contexto['carga_id']);
        $this->assertSame($carga->id, $plan->contexto['siguiente']['carga_id']);
    }

    public function test_retencion_expuesta_mantiene_prioridad_critica_y_no_se_duplica(): void
    {
        $contexto = $this->crearContexto();
        $contexto['destino']->bandasOperacionales()->sole()->update([
            'usos_permitidos' => [UsoBandaOperacional::Retenidos->value],
        ]);
        $retenido = $this->ubicar($contexto, $contexto['origen'], 1, 'PAL-258-RETENIDO');
        $retenido->update([
            'estado_operacional' => EstadoOperacionalFolio::Bloqueado,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Retenido,
        ]);
        RetencionOperacionalFolio::create([
            'folio_id' => $retenido->id,
            'bloqueo_folio_id' => $retenido->id,
            'estado' => EstadoRetencionOperacional::Activa,
            'motivo' => 'Retención de calidad prioritaria.',
            'estado_operacional_anterior' => EstadoOperacionalFolio::Disponible,
            'condicion_termica_anterior' => CondicionTermicaFolio::PrefrioAprobado,
            'habilitacion_almacenamiento_anterior' => HabilitacionAlmacenamientoFolio::Habilitado,
            'retenido_por_user_id' => $contexto['supervisor']->id,
            'retenido_at' => now(),
        ]);

        $plan = app(ServicioDesocupacionProgramada::class)->iniciar(
            $contexto['origen'],
            $contexto['supervisor'],
            'Vaciado con retención preservada.',
        );
        $tareaRetencion = $retenido->tareasMovimiento()->sole();

        $this->assertSame(PrioridadOperacional::Critica, $tareaRetencion->prioridad);
        $this->assertNotSame($plan->id, $tareaRetencion->plan_operacional_id);
        $this->assertSame('retencion_prioritaria', $plan->contexto['motivo_pendiente']);
        $this->assertSame(1, $plan->contexto['pendientes_retencion']);
        $this->assertSame(0, $plan->tareas()->count());
    }

    public function test_cancelacion_reversible_reabre_bandas_sin_deshacer_movimientos_confirmados(): void
    {
        $contexto = $this->crearContexto();
        $this->ubicar($contexto, $contexto['origen'], 1, 'PAL-258-CANCELAR-1');
        $this->ubicar($contexto, $contexto['origen'], 2, 'PAL-258-CANCELAR-2');
        $plan = app(ServicioDesocupacionProgramada::class)->iniciar(
            $contexto['origen'],
            $contexto['supervisor'],
            'Vaciado que luego será cancelado.',
        );
        [$operador, $dispositivo] = $this->crearOperador();
        $sesionOrigen = app(ServicioSesionEstiba::class)->abrir(
            $contexto['origen'],
            $operador,
            $dispositivo,
        );
        $sesionDestino = app(ServicioSesionEstiba::class)->abrir(
            $contexto['destino'],
            $operador,
            $dispositivo,
        );
        $this->ejecutarSiguiente($plan, $operador, $dispositivo, $sesionOrigen, $sesionDestino);

        $this->actingAs($contexto['supervisor'], 'sanctum')
            ->postJson("/api/desocupaciones-camara/{$contexto['origen']->id}/cancelar", [
                'motivo' => 'Mantención reprogramada por operaciones.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoPlanOperacional::Cancelado->value)
            ->assertJsonPath('data.contexto.movimientos_completados', 1)
            ->assertJsonPath('data.contexto.bandas_restauradas', 1);

        $this->assertSame(1, UbicacionActual::query()
            ->where('camara_id', $contexto['destino']->id)
            ->count());
        $this->assertSame(
            ModoBandaOperacional::Operativa,
            $contexto['origen']->bandasOperacionales()->sole()->modo,
        );
        $this->assertSame(EstadoTareaMovimiento::Cancelada, $plan->tareas()
            ->where('estado', EstadoTareaMovimiento::Cancelada->value)
            ->sole()
            ->estado);
    }

    public function test_shadow_simula_sin_bloquear_bandas_y_off_no_inicia(): void
    {
        $contexto = $this->crearContexto();
        $this->ubicar($contexto, $contexto['origen'], 1, 'PAL-258-SHADOW');
        config(['planificador.mode' => 'shadow']);

        $plan = app(ServicioDesocupacionProgramada::class)->iniciar(
            $contexto['origen'],
            $contexto['supervisor'],
            'Simulación de vaciado.',
        );

        $this->assertSame('shadow', $plan->contexto['estado_desocupacion']);
        $this->assertSame(0, $plan->tareas()->count());
        $this->assertSame(
            ModoBandaOperacional::Operativa,
            $contexto['origen']->bandasOperacionales()->sole()->modo,
        );

        config(['planificador.mode' => 'off']);
        $otra = $this->crearCamara($contexto['supervisor'], 'CAM-258-OFF', 2);
        $this->actingAs($contexto['supervisor'], 'sanctum')
            ->postJson("/api/desocupaciones-camara/{$otra->id}", [
                'motivo' => 'No debe comenzar en off.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');
        $this->assertDatabaseMissing('planes_operacionales', [
            'referencia_tipo' => ServicioDesocupacionProgramada::REFERENCIA,
            'referencia_id' => $otra->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function crearContexto(): array
    {
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-258-'.Str::upper(Str::random(5)),
            'nombre' => 'Temporada desocupación programada',
            'fecha_inicio' => '2026-09-01',
            'activa' => true,
        ]);
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $origen = $this->crearCamara($supervisor, 'CAM-258-ORIGEN', 3);
        $destino = $this->crearCamara($supervisor, 'CAM-258-DESTINO', 4);

        return compact('temporada', 'supervisor', 'origen', 'destino');
    }

    private function crearCamara(User $usuario, string $codigo, int $profundidad): Camara
    {
        $camara = Camara::create([
            'codigo' => $codigo,
            'nombre' => $codigo,
            'contenido' => ContenidoCamara::Productos,
            'estado' => 'activa',
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => $profundidad,
            'cantidad_niveles' => 1,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
        app(ServicioBandasOperacionales::class)->sincronizar($camara, $usuario);
        $camara->bandasOperacionales()->update([
            'usos_permitidos' => [UsoBandaOperacional::TransitoProductoTerminado->value],
        ]);
        for ($posicion = 1; $posicion <= $profundidad; $posicion++) {
            Posicion::create([
                'camara_id' => $camara->id,
                'banda' => 1,
                'posicion' => $posicion,
                'nivel' => 1,
                'etiqueta' => sprintf('%s-B01-P%02d-N1', $codigo, $posicion),
            ]);
        }

        return $camara;
    }

    private function ubicar(
        array $contexto,
        Camara $camara,
        int $profundidad,
        string $numero,
        string $cliente = 'Cliente común',
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
        $posicion = $camara->posiciones()->where('posicion', $profundidad)->sole();
        UbicacionActual::withoutEvents(fn (): UbicacionActual => UbicacionActual::create([
            'folio_id' => $folio->id,
            'camara_id' => $camara->id,
            'posicion_id' => $posicion->id,
            'ubicado_at' => now(),
        ]));

        return $folio;
    }

    /** @return array{User, Dispositivo} */
    private function crearOperador(): array
    {
        return [
            User::factory()->create([
                'rol' => RolUsuario::CamareroFrio,
                'activo' => true,
            ]),
            Dispositivo::create([
                'codigo' => 'TABLET-258-'.Str::upper(Str::random(5)),
                'nombre' => 'Tablet desocupación',
            ]),
        ];
    }

    private function ejecutarSiguiente(
        PlanOperacional $plan,
        User $operador,
        Dispositivo $dispositivo,
        SesionEstiba $sesionOrigen,
        SesionEstiba $sesionDestino,
    ): void {
        $tarea = $plan->tareas()
            ->where('estado', EstadoTareaMovimiento::Pendiente->value)
            ->firstOrFail();
        $planes = app(ServicioPlanesOperacionales::class);
        $tarea = $planes->asumir($tarea, $operador, $dispositivo);
        $planes->iniciar($tarea, $operador, $dispositivo);
        $movimiento = app(ServicioMovimientoEstiba::class)->mover(
            operacionId: (string) Str::uuid(),
            folio: $tarea->folio,
            posicionDestino: $tarea->posicionDestino,
            sesionOrigen: $sesionOrigen,
            sesionDestino: $sesionDestino,
            usuario: $operador,
            dispositivo: $dispositivo,
            versionOrigenConocida: $sesionOrigen->camara()->firstOrFail()->version_plano,
            versionDestinoConocida: $sesionDestino->camara()->firstOrFail()->version_plano,
            generadoDispositivoAt: now(),
            tareaMovimiento: $tarea->refresh(),
        );
        app(ReplanificarDesocupacionMovimientoObserver::class)->created($movimiento);
    }
}
