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
use App\Enums\PrioridadCarga;
use App\Enums\PrioridadOperacional;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
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
use App\Models\Temporada;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Observers\ReplanificarSegregacionMovimientoObserver;
use App\Services\Camaras\ServicioBandasOperacionales;
use App\Services\Estiba\ServicioMovimientoEstiba;
use App\Services\Estiba\ServicioPlanesOperacionales;
use App\Services\Estiba\ServicioSesionEstiba;
use App\Services\Folios\ServicioHabilitacionAlmacenamiento;
use App\Services\Retenciones\ServicioPlanSegregacionRetenidos;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SegregacionRetenidosRollingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'planificador.mode' => 'off',
            'planificador.generacion_automatica' => false,
            'planificador.compute' => 'server',
            'planificador.horizon' => 'batch',
        ]);
    }

    public function test_retencion_dirigida_publica_y_completa_ubicacion_inicial_critica(): void
    {
        $contexto = $this->crearContextoDirigido();
        $folio = $this->crearFolio(
            $contexto['temporada'],
            'PAL-256-SIN-UBICACION',
            EstadoOperacionalFolio::PendienteUbicacion,
        );

        app(ServicioHabilitacionAlmacenamiento::class)->retener(
            $folio,
            CondicionTermicaFolio::Retenido,
            'Retención de calidad.',
            $contexto['supervisor'],
        );

        $retencion = RetencionOperacionalFolio::query()->sole();
        $plan = $this->planDe($retencion);
        $tarea = $plan->tareas()->sole();
        $this->assertSame(TipoPlanOperacional::SegregacionRetenido, $plan->tipo);
        $this->assertSame(PrioridadOperacional::Critica, $plan->prioridad);
        $this->assertSame(TipoMovimiento::UbicacionInicial, $tarea->tipo_movimiento);
        $this->assertSame($contexto['destino']->id, $tarea->posicion_destino_id);
        $this->assertSame(UsoBandaOperacional::Retenidos->value, $tarea->contexto['uso_banda_destino']);

        [$operador, $dispositivo] = $this->crearOperador();
        $sesion = app(ServicioSesionEstiba::class)->abrir(
            $contexto['camara'],
            $operador,
            $dispositivo,
        );
        $planes = app(ServicioPlanesOperacionales::class);
        $tarea = $planes->asumir($tarea, $operador, $dispositivo);
        $this->assertSame($contexto['destino']->id, $tarea->reservaActiva->bloqueo_posicion_id);
        $planes->iniciar($tarea, $operador, $dispositivo);
        $movimiento = app(ServicioMovimientoEstiba::class)->ubicar(
            operacionId: (string) Str::uuid(),
            numeroFolio: $folio->numero_folio,
            tipoBulto: TipoBulto::Pallet,
            posicionDestino: $contexto['destino'],
            sesionDestino: $sesion,
            usuario: $operador,
            dispositivo: $dispositivo,
            versionDestinoConocida: $contexto['camara']->refresh()->version_plano,
            generadoDispositivoAt: now(),
            tareaMovimiento: $tarea->refresh(),
        );
        // RefreshDatabase mantiene una transacción externa y, por eso, los
        // observers after-commit se ejercitan explícitamente en esta prueba.
        app(ReplanificarSegregacionMovimientoObserver::class)->created($movimiento);

        $this->assertSame(EstadoOperacionalFolio::Bloqueado, $folio->refresh()->estado_operacional);
        $this->assertSame($contexto['destino']->id, $folio->ubicacionActual->posicion_id);
        $this->assertSame(EstadoPlanOperacional::Completado, $plan->refresh()->estado);
        $this->assertSame(100, $plan->contexto['porcentaje_actual']);
    }

    public function test_blocker_publica_una_maniobra_cerrada_con_retorno(): void
    {
        $contexto = $this->crearContextoDirigido();
        $objetivo = $this->crearFolio($contexto['temporada'], 'PAL-256-OBJETIVO');
        $blocker = $this->crearFolio($contexto['temporada'], 'PAL-256-BLOCKER');
        $this->ubicarSinEventos($objetivo, $contexto['camara'], $contexto['origen']);
        $this->ubicarSinEventos($blocker, $contexto['camara'], $contexto['blocker']);

        app(ServicioHabilitacionAlmacenamiento::class)->retener(
            $objetivo,
            CondicionTermicaFolio::Retenido,
            'Separación preventiva.',
            $contexto['supervisor'],
        );

        $plan = $this->planDe(RetencionOperacionalFolio::query()->sole());
        $maniobra = $plan->maniobras()->sole();
        $pasos = $maniobra->pasos()->get();
        $this->assertSame(EstadoManiobraOperacional::Pendiente, $maniobra->estado);
        $this->assertSame(3, $maniobra->costo_movimientos);
        $this->assertSame([
            TipoPasoManiobra::ExtraccionTemporal,
            TipoPasoManiobra::MovimientoPermanente,
            TipoPasoManiobra::RetornoBanda,
        ], $pasos->pluck('tipo_paso_maniobra')->all());
        $this->assertSame($blocker->id, $pasos[0]->folio_id);
        $this->assertSame($objetivo->id, $pasos[1]->folio_id);
        $this->assertSame($blocker->id, $pasos[2]->folio_id);
        $this->assertSame($contexto['blocker']->id, $pasos[2]->posicion_destino_id);
        $this->assertSame(2, $maniobra->reservasBandas()->count());
        $this->assertTrue($maniobra->contexto['cerrable']);
    }

    public function test_retener_suspende_carga_y_liberar_restaura_su_asignacion(): void
    {
        $contexto = $this->crearContexto();
        $folio = $this->crearFolio($contexto['temporada'], 'PAL-256-CARGA');
        $this->ubicarSinEventos($folio, $contexto['camara'], $contexto['origen']);
        $carga = Carga::create([
            'temporada_id' => $contexto['temporada']->id,
            'codigo' => 'CAR-256-000001',
            'estado' => EstadoCarga::Pendiente,
            'prioridad' => PrioridadCarga::Alta,
            'version' => 1,
            'creada_por_user_id' => $contexto['supervisor']->id,
            'actualizada_por_user_id' => $contexto['supervisor']->id,
            'publicada_por_user_id' => $contexto['supervisor']->id,
            'publicada_at' => now(),
        ]);
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

        $habilitacion = app(ServicioHabilitacionAlmacenamiento::class);
        $habilitacion->retener(
            $folio,
            CondicionTermicaFolio::Retenido,
            'Retención que preempta la carga.',
            $contexto['supervisor'],
        );

        $retencion = RetencionOperacionalFolio::query()->sole();
        $this->assertSame($carga->id, $retencion->carga_id_original);
        $this->assertDatabaseMissing('reservas_carga_folio', ['folio_id' => $folio->id]);
        $this->assertSame(EstadoCargaFolio::Descartado, $asignacion->refresh()->estado);

        $habilitacion->habilitar(
            $folio->refresh(),
            CondicionTermicaFolio::PrefrioAprobado,
            FuenteHabilitacionAlmacenamiento::ContingenciaAutorizada,
            $contexto['supervisor'],
        );

        $this->assertSame(EstadoRetencionOperacional::Liberada, $retencion->refresh()->estado);
        $this->assertNull($retencion->bloqueo_folio_id);
        $reservaRestaurada = ReservaCargaFolio::query()->where('folio_id', $folio->id)->sole();
        $this->assertNotSame($asignacion->id, $reservaRestaurada->carga_folio_id);
        $this->assertSame($carga->id, $reservaRestaurada->asignacion->carga_id);
        $this->assertSame(
            $asignacion->id,
            $reservaRestaurada->asignacion->reemplaza_a_carga_folio_id,
        );
        $this->assertTrue($retencion->contexto['flujo_restaurado']);
    }

    public function test_retencion_cancela_una_tarea_incompatible_reversible(): void
    {
        $contexto = $this->crearContexto();
        $folio = $this->crearFolio($contexto['temporada'], 'PAL-256-TAREA');
        $this->ubicarSinEventos($folio, $contexto['camara'], $contexto['origen']);
        $plan = app(ServicioPlanesOperacionales::class)->crear(
            $contexto['temporada'],
            TipoPlanOperacional::AlmacenamientoPallet,
            'Almacenar pallet antes de retención',
            $contexto['supervisor'],
            [[
                'folio_id' => $folio->id,
                'tipo_movimiento' => TipoMovimiento::Reubicacion,
                'camara_origen_id' => $contexto['camara']->id,
                'posicion_origen_id' => $contexto['origen']->id,
                'camara_destino_id' => $contexto['camara']->id,
                'posicion_destino_id' => $contexto['destino']->id,
            ]],
        );

        app(ServicioHabilitacionAlmacenamiento::class)->retener(
            $folio,
            CondicionTermicaFolio::Retenido,
            'La retención tiene prioridad.',
            $contexto['supervisor'],
        );

        $this->assertSame(
            EstadoTareaMovimiento::Cancelada,
            $plan->tareas()->sole()->estado,
        );
    }

    public function test_retencion_espera_una_tarea_previa_que_ya_esta_en_proceso(): void
    {
        $contexto = $this->crearContextoDirigido();
        $folio = $this->crearFolio($contexto['temporada'], 'PAL-256-EN-MOVIMIENTO');
        $this->ubicarSinEventos($folio, $contexto['camara'], $contexto['origen']);
        $planAnterior = app(ServicioPlanesOperacionales::class)->crear(
            $contexto['temporada'],
            TipoPlanOperacional::AlmacenamientoPallet,
            'Movimiento iniciado antes de la retención',
            $contexto['supervisor'],
            [[
                'folio_id' => $folio->id,
                'tipo_movimiento' => TipoMovimiento::Reubicacion,
                'camara_origen_id' => $contexto['camara']->id,
                'posicion_origen_id' => $contexto['origen']->id,
                'camara_destino_id' => $contexto['camara']->id,
                'posicion_destino_id' => $contexto['blocker']->id,
            ]],
        );
        $tareaPrevia = $planAnterior->tareas()->sole();
        $tareaPrevia->update([
            'estado' => EstadoTareaMovimiento::EnProceso,
            'iniciada_at' => now(),
        ]);

        app(ServicioHabilitacionAlmacenamiento::class)->retener(
            $folio,
            CondicionTermicaFolio::Retenido,
            'La labor previa debe terminar sin perder la retención.',
            $contexto['supervisor'],
        );

        $planRetencion = $this->planDe(RetencionOperacionalFolio::query()->sole());
        $this->assertSame(EstadoTareaMovimiento::EnProceso, $tareaPrevia->refresh()->estado);
        $this->assertSame('tarea_fisica_previa_en_curso', $planRetencion->contexto['motivo_pendiente']);
        $this->assertSame($tareaPrevia->id, $planRetencion->contexto['tarea_previa_id']);
        $this->assertSame(0, $planRetencion->tareas()->count());
    }

    public function test_recalculo_reemplaza_un_destino_de_retencion_que_dejo_de_estar_libre(): void
    {
        $contexto = $this->crearContextoDirigido();
        $objetivo = $this->crearFolio(
            $contexto['temporada'],
            'PAL-256-REPLANIFICAR',
            EstadoOperacionalFolio::PendienteUbicacion,
        );
        app(ServicioHabilitacionAlmacenamiento::class)->retener(
            $objetivo,
            CondicionTermicaFolio::Retenido,
            'Segregación con frontera móvil.',
            $contexto['supervisor'],
        );

        $retencion = RetencionOperacionalFolio::query()->sole();
        $plan = $this->planDe($retencion);
        $maniobraAnterior = $plan->maniobras()->sole();
        $destinoAnterior = $maniobraAnterior->pasos()->sole()->posicion_destino_id;
        $ocupante = $this->crearFolio($contexto['temporada'], 'PAL-256-OCUPANTE');
        $this->ubicarSinEventos(
            $ocupante,
            $contexto['camara'],
            Posicion::query()->findOrFail($destinoAnterior),
        );

        app(ServicioPlanSegregacionRetenidos::class)->sincronizar(
            $retencion,
            $contexto['supervisor'],
        );

        $this->assertSame(
            EstadoManiobraOperacional::Cancelada,
            $maniobraAnterior->refresh()->estado,
        );
        $tareaVigente = $plan->tareas()
            ->where('estado', EstadoTareaMovimiento::Pendiente->value)
            ->sole();
        $this->assertNotSame($destinoAnterior, $tareaVigente->posicion_destino_id);
        $this->assertSame(2, $tareaVigente->posicionDestino->banda);
    }

    public function test_banda_con_retenido_no_puede_perder_su_uso_operacional(): void
    {
        $contexto = $this->crearContextoDirigido();
        $folio = $this->crearFolio($contexto['temporada'], 'PAL-256-YA-SEGREGADO');
        $this->ubicarSinEventos($folio, $contexto['camara'], $contexto['destino']);
        app(ServicioHabilitacionAlmacenamiento::class)->retener(
            $folio,
            CondicionTermicaFolio::Retenido,
            'Retención ya segregada.',
            $contexto['supervisor'],
        );

        $plan = $this->planDe(RetencionOperacionalFolio::query()->sole());
        $this->assertSame(EstadoPlanOperacional::Completado, $plan->estado);
        $this->assertSame(100, $plan->contexto['porcentaje_actual']);
        $banda = BandaOperacional::query()
            ->where('camara_id', $contexto['camara']->id)
            ->where('numero', 2)
            ->sole();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('pallets o maniobras activas');
        app(ServicioBandasOperacionales::class)->configurar(
            $contexto['camara'],
            $banda,
            [
                'version' => $banda->version,
                'usos_permitidos' => [UsoBandaOperacional::TransitoProductoTerminado->value],
                'modo' => 'operativa',
            ],
            $contexto['supervisor'],
        );
    }

    /** @return array<string, mixed> */
    private function crearContextoDirigido(): array
    {
        config([
            'planificador.mode' => 'guided',
            'planificador.generacion_automatica' => true,
            'planificador.compute' => 'tablet',
            'planificador.horizon' => 'rolling',
            'planificador.frontier_max' => 4,
        ]);

        return $this->crearContexto();
    }

    /** @return array<string, mixed> */
    private function crearContexto(): array
    {
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-256-'.Str::upper(Str::random(5)),
            'nombre' => 'Temporada segregación retenidos',
            'fecha_inicio' => '2026-09-01',
            'activa' => true,
        ]);
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $camara = Camara::create([
            'codigo' => 'CAM-256-'.Str::upper(Str::random(5)),
            'nombre' => 'Cámara segregación retenidos',
            'contenido' => ContenidoCamara::Productos,
            'estado' => 'activa',
            'cantidad_bandas' => 2,
            'posiciones_por_banda' => 2,
            'cantidad_niveles' => 1,
            'creado_por_user_id' => $supervisor->id,
            'actualizado_por_user_id' => $supervisor->id,
        ]);
        app(ServicioBandasOperacionales::class)->sincronizar($camara, $supervisor);
        BandaOperacional::query()
            ->where('camara_id', $camara->id)
            ->where('numero', 1)
            ->update(['usos_permitidos' => [UsoBandaOperacional::TransitoProductoTerminado->value]]);
        BandaOperacional::query()
            ->where('camara_id', $camara->id)
            ->where('numero', 2)
            ->update(['usos_permitidos' => [UsoBandaOperacional::Retenidos->value]]);
        $origen = $this->crearPosicion($camara, 1, 1);
        $blocker = $this->crearPosicion($camara, 1, 2);
        $destino = $this->crearPosicion($camara, 2, 1);
        $this->crearPosicion($camara, 2, 2);

        return compact('temporada', 'supervisor', 'camara', 'origen', 'blocker', 'destino');
    }

    private function crearFolio(
        Temporada $temporada,
        string $numero,
        EstadoOperacionalFolio $estado = EstadoOperacionalFolio::Disponible,
    ): Folio {
        return Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => $numero,
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => $estado,
            'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
            'fuente_habilitacion_almacenamiento' => FuenteHabilitacionAlmacenamiento::PrefrioAprobado,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
    }

    private function crearPosicion(Camara $camara, int $banda, int $posicion): Posicion
    {
        return Posicion::create([
            'camara_id' => $camara->id,
            'banda' => $banda,
            'posicion' => $posicion,
            'nivel' => 1,
            'etiqueta' => sprintf('B%02d-P%02d-N1', $banda, $posicion),
        ]);
    }

    private function ubicarSinEventos(Folio $folio, Camara $camara, Posicion $posicion): void
    {
        UbicacionActual::withoutEvents(fn (): UbicacionActual => UbicacionActual::create([
            'folio_id' => $folio->id,
            'camara_id' => $camara->id,
            'posicion_id' => $posicion->id,
            'ubicado_at' => now(),
        ]));
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
                'codigo' => 'TABLET-256-'.Str::upper(Str::random(5)),
                'nombre' => 'Tablet segregación retenidos',
                'activo' => true,
            ]),
        ];
    }

    private function planDe(RetencionOperacionalFolio $retencion): PlanOperacional
    {
        return PlanOperacional::query()
            ->where('referencia_tipo', 'retencion_operacional')
            ->where('referencia_id', $retencion->id)
            ->sole();
    }
}
