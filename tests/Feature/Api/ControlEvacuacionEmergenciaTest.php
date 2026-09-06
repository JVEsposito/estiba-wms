<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\ContenidoCamara;
use App\Enums\EstadoCustodiaTemporal;
use App\Enums\EstadoManiobraOperacional;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\FuenteHabilitacionAlmacenamiento;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\ModoBandaOperacional;
use App\Enums\PrioridadOperacional;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPlanOperacional;
use App\Enums\UsoBandaOperacional;
use App\Exceptions\ConflictoOperacion;
use App\Models\Camara;
use App\Models\CustodiaTemporalManiobra;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\ManiobraOperacional;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Camaras\ServicioBandasOperacionales;
use App\Services\Camaras\ServicioControlEvacuacionEmergencia;
use App\Services\Estiba\ServicioPlanesOperacionales;
use App\Services\Estiba\ServicioReservasTareasMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ControlEvacuacionEmergenciaTest extends TestCase
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
        ]);
    }

    public function test_declara_bloquea_y_antepone_solo_trabajo_reversible_de_menor_prioridad(): void
    {
        $contexto = $this->crearContexto();
        $planNormal = $this->crearPlan($contexto, PrioridadOperacional::Normal);
        $reversible = $this->crearTarea(
            $contexto,
            $planNormal,
            EstadoTareaMovimiento::Pendiente,
            PrioridadOperacional::Normal,
            $contexto['otra'],
            $contexto['emergencia'],
        );
        $enProceso = $this->crearTarea(
            $contexto,
            $planNormal,
            EstadoTareaMovimiento::EnProceso,
            PrioridadOperacional::Normal,
            $contexto['otra'],
            $contexto['emergencia'],
        );
        $critica = $this->crearTarea(
            $contexto,
            $planNormal,
            EstadoTareaMovimiento::Pendiente,
            PrioridadOperacional::Critica,
            $contexto['emergencia'],
            $contexto['otra'],
        );
        $maniobra = ManiobraOperacional::create([
            'plan_operacional_id' => $planNormal->id,
            'creado_por_user_id' => $contexto['supervisor']->id,
            'estado' => EstadoManiobraOperacional::PausadaDiscrepancia,
            'prioridad' => PrioridadOperacional::Normal,
            'candidate_key' => 'emergencia:discrepancia',
            'titulo' => 'Maniobra con discrepancia',
            'secuencia_actual' => 1,
            'costo_movimientos' => 1,
        ]);
        $conDiscrepancia = $this->crearTarea(
            $contexto,
            $planNormal,
            EstadoTareaMovimiento::Pendiente,
            PrioridadOperacional::Normal,
            $contexto['emergencia'],
            $contexto['otra'],
            $maniobra,
        );
        $maniobraCustodia = ManiobraOperacional::create([
            'plan_operacional_id' => $planNormal->id,
            'creado_por_user_id' => $contexto['supervisor']->id,
            'estado' => EstadoManiobraOperacional::EnEjecucion,
            'prioridad' => PrioridadOperacional::Normal,
            'candidate_key' => 'emergencia:custodia',
            'titulo' => 'Maniobra con custodia temporal',
            'secuencia_actual' => 1,
            'costo_movimientos' => 1,
        ]);
        $conCustodia = $this->crearTarea(
            $contexto,
            $planNormal,
            EstadoTareaMovimiento::Pendiente,
            PrioridadOperacional::Normal,
            $contexto['emergencia'],
            $contexto['otra'],
            $maniobraCustodia,
        );
        CustodiaTemporalManiobra::create([
            'maniobra_operacional_id' => $maniobraCustodia->id,
            'folio_id' => $conCustodia->folio_id,
            'tarea_extraccion_id' => $conCustodia->id,
            'camara_origen_id' => $contexto['emergencia']->id,
            'posicion_origen_id' => $contexto['emergencia']->posiciones()->firstOrFail()->id,
            'banda_origen' => 1,
            'posicion_origen' => 1,
            'nivel_origen' => 1,
            'estado' => EstadoCustodiaTemporal::Activa,
            'bloqueo_folio_id' => $conCustodia->folio_id,
            'user_id' => $contexto['supervisor']->id,
            'dispositivo_id' => $contexto['dispositivo']->id,
            'extraido_at' => now(),
        ]);

        $respuesta = $this->withToken($contexto['token'])
            ->postJson("/api/evacuaciones-emergencia/{$contexto['emergencia']->id}", [
                'motivo' => 'Fuga de refrigerante detectada en el sector norte.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tipo', TipoPlanOperacional::EvacuacionEmergencia->value)
            ->assertJsonPath('data.estado', EstadoPlanOperacional::EnEjecucion->value)
            ->assertJsonPath('data.prioridad', PrioridadOperacional::Critica->value)
            ->assertJsonPath('data.contexto.ingreso_bloqueado', true)
            ->assertJsonPath('data.contexto.genera_destinos', false)
            ->assertJsonPath('data.contexto.genera_tareas', false)
            ->assertJsonPath('data.contexto.tareas_canceladas', 1)
            ->assertJsonPath('data.contexto.total_impedimentos', 4)
            ->assertJsonPath(
                'data.contexto.declarado_desde_dispositivo_id',
                $contexto['dispositivo']->id,
            );

        $this->assertSame(EstadoTareaMovimiento::Cancelada, $reversible->refresh()->estado);
        $this->assertSame(EstadoTareaMovimiento::EnProceso, $enProceso->refresh()->estado);
        $this->assertSame(EstadoTareaMovimiento::Pendiente, $critica->refresh()->estado);
        $this->assertSame(EstadoTareaMovimiento::Pendiente, $conDiscrepancia->refresh()->estado);
        $this->assertSame(EstadoTareaMovimiento::Pendiente, $conCustodia->refresh()->estado);
        $this->assertEqualsCanonicalizing(
            ['en_proceso', 'prioridad_critica', 'discrepancia', 'custodia_temporal'],
            collect($respuesta->json('data.contexto.impedimentos'))->pluck('motivo')->all(),
        );
        $this->assertNotEmpty($respuesta->json('data.contexto.declarado_at'));
        $this->assertSame(0, PlanOperacional::query()
            ->findOrFail($respuesta->json('data.id'))
            ->tareas()
            ->count());
        $this->assertTrue($contexto['emergencia']->bandasOperacionales()
            ->get()
            ->every(fn ($banda): bool => $banda->modo === ModoBandaOperacional::EnVaciado));

        $this->withToken($contexto['token'])
            ->postJson("/api/evacuaciones-emergencia/{$contexto['emergencia']->id}", [
                'motivo' => 'Segundo aviso del mismo incidente.',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.id', $respuesta->json('data.id'));
        $this->assertSame(1, PlanOperacional::query()
            ->where('tipo', TipoPlanOperacional::EvacuacionEmergencia->value)
            ->count());
    }

    public function test_shadow_registra_impacto_sin_bloquear_ni_cancelar_y_off_no_declara(): void
    {
        $contexto = $this->crearContexto();
        $planNormal = $this->crearPlan($contexto, PrioridadOperacional::Normal);
        $tarea = $this->crearTarea(
            $contexto,
            $planNormal,
            EstadoTareaMovimiento::Pendiente,
            PrioridadOperacional::Normal,
            $contexto['otra'],
            $contexto['emergencia'],
        );
        config(['planificador.mode' => 'shadow']);

        $this->withToken($contexto['token'])
            ->postJson("/api/evacuaciones-emergencia/{$contexto['emergencia']->id}", [
                'motivo' => 'Simular respuesta ante alarma de temperatura.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.estado', EstadoPlanOperacional::Programado->value)
            ->assertJsonPath('data.contexto.estado_emergencia', 'shadow')
            ->assertJsonPath('data.contexto.ingreso_bloqueado', false)
            ->assertJsonPath('data.contexto.tareas_reversibles_detectadas', 1)
            ->assertJsonPath('data.contexto.tareas_canceladas', 0);

        $this->assertSame(EstadoTareaMovimiento::Pendiente, $tarea->refresh()->estado);
        $this->assertTrue($contexto['emergencia']->bandasOperacionales()
            ->get()
            ->every(fn ($banda): bool => $banda->modo === ModoBandaOperacional::Operativa));

        config(['planificador.mode' => 'off']);
        $otraCamara = $this->crearCamara($contexto['supervisor'], 'CAM-259-OFF');
        $this->withToken($contexto['token'])
            ->postJson("/api/evacuaciones-emergencia/{$otraCamara->id}", [
                'motivo' => 'No debe persistirse con el planificador apagado.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');
        $this->assertDatabaseMissing('planes_operacionales', [
            'referencia_tipo' => ServicioControlEvacuacionEmergencia::REFERENCIA,
            'referencia_id' => $otraCamara->id,
        ]);
    }

    public function test_cancelacion_restaura_solo_el_estado_de_banda_que_la_emergencia_conserva(): void
    {
        $contexto = $this->crearContexto();
        $bandas = $contexto['emergencia']->bandasOperacionales()->get()->values();
        $bandas[0]->update([
            'modo' => ModoBandaOperacional::EnVaciado,
            'motivo_estado' => 'Desocupación programada previa.',
        ]);
        $bandas[1]->update([
            'modo' => ModoBandaOperacional::Bloqueada,
            'motivo_estado' => 'Mantención de puerta.',
        ]);
        $plan = app(ServicioControlEvacuacionEmergencia::class)->declarar(
            $contexto['emergencia'],
            $contexto['supervisor'],
            'Alarma confirmada en la cámara.',
        );
        $bandas[1]->refresh()->update([
            'motivo_estado' => 'Intervención posterior de mantenimiento.',
            'version' => $bandas[1]->version + 1,
        ]);

        $this->withToken($contexto['token'])
            ->postJson("/api/evacuaciones-emergencia/{$contexto['emergencia']->id}/cancelar", [
                'motivo' => 'Alarma descartada por el responsable de seguridad.',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonPath('data.estado', EstadoPlanOperacional::Cancelado->value)
            ->assertJsonPath('data.contexto.bandas_restauradas', 1)
            ->assertJsonPath('data.contexto.bandas_no_restauradas', 1)
            ->assertJsonPath(
                'data.contexto.cancelado_desde_dispositivo_id',
                $contexto['dispositivo']->id,
            );

        $this->assertSame(ModoBandaOperacional::EnVaciado, $bandas[0]->refresh()->modo);
        $this->assertSame('Desocupación programada previa.', $bandas[0]->motivo_estado);
        $this->assertSame(ModoBandaOperacional::Bloqueada, $bandas[1]->refresh()->modo);
        $this->assertSame('Intervención posterior de mantenimiento.', $bandas[1]->motivo_estado);
    }

    public function test_emergencia_impide_regenerar_labor_normal_en_la_camara(): void
    {
        $contexto = $this->crearContexto();
        $planNormal = $this->crearPlan($contexto, PrioridadOperacional::Normal);
        app(ServicioControlEvacuacionEmergencia::class)->declarar(
            $contexto['emergencia'],
            $contexto['supervisor'],
            'Controlar el sector antes de generar más trabajo.',
        );
        $folio = $this->crearFolio($contexto['temporada'], 'PAL-259-REGENERAR');

        $this->expectException(ConflictoOperacion::class);
        $this->expectExceptionMessage('suspendió nuevas labores normales');
        app(ServicioPlanesOperacionales::class)->agregarTareaRolling($planNormal, [
            'folio_id' => $folio->id,
            'tipo_movimiento' => TipoMovimiento::Retiro,
            'camara_origen_id' => $contexto['emergencia']->id,
            'posicion_origen_id' => $contexto['emergencia']->posiciones()->firstOrFail()->id,
        ]);
    }

    public function test_bloqueo_impide_un_ingreso_manual_nuevo(): void
    {
        $contexto = $this->crearContexto();
        app(ServicioControlEvacuacionEmergencia::class)->declarar(
            $contexto['emergencia'],
            $contexto['supervisor'],
            'Cerrar inmediatamente los ingresos a la cámara.',
        );
        $folio = $this->crearFolio($contexto['temporada'], 'PAL-259-MANUAL');
        [$operador, $dispositivo] = $this->crearOperador();

        $this->expectException(ConflictoOperacion::class);
        $this->expectExceptionMessage('no admite nuevos ingresos');
        app(ServicioReservasTareasMovimiento::class)->validarParaMovimiento(
            tarea: null,
            folio: $folio,
            tipo: TipoMovimiento::TrasladoEntreCamaras,
            posicionOrigenId: $contexto['otra']->posiciones()->firstOrFail()->id,
            posicionDestinoId: $contexto['emergencia']->posiciones()->firstOrFail()->id,
            usuario: $operador,
            dispositivo: $dispositivo,
        );
    }

    public function test_requiere_permiso_de_supervision_y_motivo_auditable(): void
    {
        $contexto = $this->crearContexto();
        $consulta = User::factory()->create([
            'rol' => RolUsuario::Consulta,
            'activo' => true,
        ]);

        $this->actingAs($consulta, 'sanctum')
            ->postJson("/api/evacuaciones-emergencia/{$contexto['emergencia']->id}", [
                'motivo' => 'Alarma operacional.',
            ])
            ->assertForbidden();
        $this->withToken($contexto['token'])
            ->postJson("/api/evacuaciones-emergencia/{$contexto['emergencia']->id}", [
                'motivo' => 'x',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motivo');
    }

    /** @return array<string, mixed> */
    private function crearContexto(): array
    {
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-259-'.Str::upper(Str::random(5)),
            'nombre' => 'Temporada control de emergencia',
            'fecha_inicio' => '2026-09-05',
            'activa' => true,
        ]);
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-259-'.Str::upper(Str::random(5)),
            'nombre' => 'Tablet control emergencia',
            'activo' => true,
        ]);
        $token = $supervisor
            ->crearTokenParaDispositivo($dispositivo, 'tablet-control-emergencia')
            ->plainTextToken;
        $emergencia = $this->crearCamara($supervisor, 'CAM-259-EMERGENCIA');
        $otra = $this->crearCamara($supervisor, 'CAM-259-APOYO');

        return compact('temporada', 'supervisor', 'dispositivo', 'token', 'emergencia', 'otra');
    }

    private function crearCamara(User $usuario, string $codigo): Camara
    {
        $camara = Camara::create([
            'codigo' => $codigo,
            'nombre' => $codigo,
            'contenido' => ContenidoCamara::Productos,
            'estado' => 'activa',
            'cantidad_bandas' => 2,
            'posiciones_por_banda' => 2,
            'cantidad_niveles' => 1,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
        app(ServicioBandasOperacionales::class)->sincronizar($camara, $usuario);
        $camara->bandasOperacionales()->update([
            'usos_permitidos' => [UsoBandaOperacional::TransitoProductoTerminado->value],
        ]);
        foreach (range(1, 2) as $banda) {
            foreach (range(1, 2) as $profundidad) {
                Posicion::create([
                    'camara_id' => $camara->id,
                    'banda' => $banda,
                    'posicion' => $profundidad,
                    'nivel' => 1,
                    'etiqueta' => sprintf('%s-B%02d-P%02d-N1', $codigo, $banda, $profundidad),
                ]);
            }
        }

        return $camara;
    }

    private function crearPlan(array $contexto, PrioridadOperacional $prioridad): PlanOperacional
    {
        return PlanOperacional::create([
            'temporada_id' => $contexto['temporada']->id,
            'tipo' => TipoPlanOperacional::ReordenamientoCamara,
            'estado' => EstadoPlanOperacional::Programado,
            'prioridad' => $prioridad,
            'titulo' => 'Trabajo normal previo',
            'motivo' => 'Trabajo reversible.',
            'creado_por_user_id' => $contexto['supervisor']->id,
            'programado_at' => now(),
            'contexto' => ['planner_horizon' => 'rolling'],
        ]);
    }

    private function crearTarea(
        array $contexto,
        PlanOperacional $plan,
        EstadoTareaMovimiento $estado,
        PrioridadOperacional $prioridad,
        Camara $origen,
        Camara $destino,
        ?ManiobraOperacional $maniobra = null,
    ): TareaMovimiento {
        $secuencia = ((int) $plan->tareas()->max('secuencia')) + 1;

        return TareaMovimiento::create([
            'plan_operacional_id' => $plan->id,
            'maniobra_operacional_id' => $maniobra?->id,
            'secuencia' => $secuencia,
            'secuencia_maniobra' => $maniobra ? 1 : null,
            'tipo_movimiento' => TipoMovimiento::TrasladoEntreCamaras,
            'estado' => $estado,
            'prioridad' => $prioridad,
            'folio_id' => $this->crearFolio(
                $contexto['temporada'],
                'PAL-259-'.Str::upper(Str::random(8)),
            )->id,
            'camara_origen_id' => $origen->id,
            'posicion_origen_id' => $origen->posiciones()->firstOrFail()->id,
            'camara_destino_id' => $destino->id,
            'posicion_destino_id' => $destino->posiciones()->firstOrFail()->id,
            'iniciada_at' => $estado === EstadoTareaMovimiento::EnProceso ? now() : null,
        ]);
    }

    private function crearFolio(Temporada $temporada, string $numero): Folio
    {
        return Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => $numero,
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
            'fuente_habilitacion_almacenamiento' => FuenteHabilitacionAlmacenamiento::PrefrioAprobado,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
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
                'codigo' => 'TABLET-259-OPERADOR-'.Str::upper(Str::random(4)),
                'nombre' => 'Tablet operador emergencia',
                'activo' => true,
            ]),
        ];
    }
}
