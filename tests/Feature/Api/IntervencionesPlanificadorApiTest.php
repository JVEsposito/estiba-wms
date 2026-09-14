<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoManiobraOperacional;
use App\Enums\EstadoReservaTareaMovimiento;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\EstadoTransicionOperacional;
use App\Enums\PrioridadOperacional;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\ManiobraOperacional;
use App\Models\PlanOperacional;
use App\Models\ReservaTareaMovimiento;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Models\TransicionOperacional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntervencionesPlanificadorApiTest extends TestCase
{
    use RefreshDatabase;

    private int $secuencia = 0;

    public function test_solo_supervision_puede_intervenir_maniobras(): void
    {
        $supervisor = $this->usuario(RolUsuario::SupervisorFrio);
        $camarero = $this->usuario(RolUsuario::CamareroFrio);
        [$maniobra] = $this->crearManiobra($supervisor);
        $payload = [
            'operacion_id' => (string) Str::uuid(),
            'version_maniobra' => $maniobra->version,
            'motivo' => 'Pausa preventiva antes de iniciar.',
        ];

        $this->postJson(
            "/api/intervenciones-planificador/maniobras/{$maniobra->id}/pausar",
            $payload,
        )->assertUnauthorized();

        $this->actingAs($camarero, 'sanctum')
            ->postJson(
                "/api/intervenciones-planificador/maniobras/{$maniobra->id}/pausar",
                $payload,
            )
            ->assertForbidden();
    }

    public function test_pausa_y_reanuda_antes_del_inicio_con_auditoria_e_idempotencia(): void
    {
        $supervisor = $this->usuario(RolUsuario::SupervisorFrio);
        [$maniobra, $tarea] = $this->crearManiobra($supervisor);
        $operacionPausa = (string) Str::uuid();
        $payloadPausa = [
            'operacion_id' => $operacionPausa,
            'version_maniobra' => $maniobra->version,
            'motivo' => 'Esperar confirmación del andén antes de publicar.',
        ];
        $urlPausa = "/api/intervenciones-planificador/maniobras/{$maniobra->id}/pausar";

        $this->actingAs($supervisor, 'sanctum')
            ->postJson($urlPausa, $payloadPausa)
            ->assertOk()
            ->assertJsonPath('data.estado', 'pausada_supervision')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.acciones_autorizadas.version_requerida', 2)
            ->assertJsonPath('data.acciones_autorizadas.restricciones.reanudar', null);

        $this->assertSame(
            EstadoManiobraOperacional::PausadaSupervision,
            $maniobra->refresh()->estado,
        );
        $this->assertSame(EstadoTareaMovimiento::Bloqueada, $tarea->refresh()->estado);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson($urlPausa, $payloadPausa)
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $transicionPausa = TransicionOperacional::query()
            ->where('operacion_id', $operacionPausa)
            ->sole();
        $this->assertSame(EstadoTransicionOperacional::Aplicada, $transicionPausa->estado);
        $this->assertSame(2, $transicionPausa->cantidad_cambios);
        $this->assertCount(2, $transicionPausa->cambios);

        $operacionReanudacion = (string) Str::uuid();
        $this->actingAs($supervisor, 'sanctum')
            ->postJson(
                "/api/intervenciones-planificador/maniobras/{$maniobra->id}/reanudar",
                [
                    'operacion_id' => $operacionReanudacion,
                    'version_maniobra' => 2,
                    'motivo' => 'Andén confirmado; la maniobra vuelve a la frontera.',
                ],
            )
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.acciones_autorizadas.restricciones.pausar', null);

        $this->assertSame(EstadoManiobraOperacional::Pendiente, $maniobra->refresh()->estado);
        $this->assertSame(EstadoTareaMovimiento::Pendiente, $tarea->refresh()->estado);
        $this->assertDatabaseCount('transiciones_operacionales', 2);
    }

    public function test_reprioriza_pendientes_y_rechaza_una_version_obsoleta(): void
    {
        $supervisor = $this->usuario(RolUsuario::SupervisorFrio);
        [$maniobra, $tarea] = $this->crearManiobra($supervisor);
        $url = "/api/intervenciones-planificador/maniobras/{$maniobra->id}/prioridad";

        $this->actingAs($supervisor, 'sanctum')
            ->patchJson($url, [
                'operacion_id' => (string) Str::uuid(),
                'version_maniobra' => 99,
                'prioridad' => 'critica',
                'motivo' => 'El camión adelanta su ventana de salida.',
            ])
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->assertSame(PrioridadOperacional::Normal, $maniobra->refresh()->prioridad);
        $this->assertSame(PrioridadOperacional::Normal, $tarea->refresh()->prioridad);
        $this->assertSame(
            EstadoTransicionOperacional::Rechazada,
            TransicionOperacional::query()->latest('created_at')->firstOrFail()->estado,
        );

        $this->actingAs($supervisor, 'sanctum')
            ->patchJson($url, [
                'operacion_id' => (string) Str::uuid(),
                'version_maniobra' => 1,
                'prioridad' => 'critica',
                'motivo' => 'El camión adelanta su ventana de salida.',
            ])
            ->assertOk()
            ->assertJsonPath('data.prioridad', 'critica')
            ->assertJsonPath('data.version', 2);

        $this->assertSame(PrioridadOperacional::Critica, $maniobra->refresh()->prioridad);
        $this->assertSame(PrioridadOperacional::Critica, $tarea->refresh()->prioridad);
    }

    public function test_no_pausa_ni_reprioriza_el_prefijo_fisico_en_proceso(): void
    {
        $supervisor = $this->usuario(RolUsuario::SupervisorFrio);
        [$maniobra, $tarea] = $this->crearManiobra(
            $supervisor,
            EstadoManiobraOperacional::EnEjecucion,
            EstadoTareaMovimiento::EnProceso,
        );

        $this->actingAs($supervisor, 'sanctum')
            ->postJson(
                "/api/intervenciones-planificador/maniobras/{$maniobra->id}/pausar",
                [
                    'operacion_id' => (string) Str::uuid(),
                    'version_maniobra' => $maniobra->version,
                    'motivo' => 'Intento de pausa durante el movimiento.',
                ],
            )
            ->assertConflict();

        $this->actingAs($supervisor, 'sanctum')
            ->patchJson(
                "/api/intervenciones-planificador/maniobras/{$maniobra->id}/prioridad",
                [
                    'operacion_id' => (string) Str::uuid(),
                    'version_maniobra' => $maniobra->version,
                    'prioridad' => 'urgente',
                    'motivo' => 'Intento de cambiar el prefijo.',
                ],
            )
            ->assertConflict();

        $this->assertSame(EstadoManiobraOperacional::EnEjecucion, $maniobra->refresh()->estado);
        $this->assertSame(EstadoTareaMovimiento::EnProceso, $tarea->refresh()->estado);
        $this->assertSame(PrioridadOperacional::Normal, $maniobra->prioridad);
    }

    public function test_libera_solo_leases_vencidos_de_la_temporada_activa_y_es_idempotente(): void
    {
        $supervisor = $this->usuario(RolUsuario::SupervisorFrio);
        $camarero = $this->usuario(RolUsuario::CamareroFrio);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TAB-INT-01',
            'nombre' => 'Tablet intervención',
            'activo' => true,
        ]);
        [$maniobra, $tarea] = $this->crearManiobra(
            $supervisor,
            EstadoManiobraOperacional::EnEjecucion,
            EstadoTareaMovimiento::Asumida,
        );
        $maniobra->update([
            'responsable_user_id' => $camarero->id,
            'dispositivo_id' => $dispositivo->id,
            'asumida_at' => now()->subMinutes(12),
        ]);
        $tarea->update([
            'responsable_user_id' => $camarero->id,
            'dispositivo_id' => $dispositivo->id,
            'asumida_at' => now()->subMinutes(12),
        ]);
        $reserva = $this->crearReservaVencida($tarea, $camarero, $dispositivo);

        $historica = Temporada::create([
            'codigo' => 'TEMP-INT-HIST',
            'nombre' => 'Temporada histórica intervención',
            'activa' => false,
        ]);
        [, $tareaHistorica] = $this->crearManiobra(
            $supervisor,
            EstadoManiobraOperacional::EnEjecucion,
            EstadoTareaMovimiento::Asumida,
            $historica,
        );
        $reservaHistorica = $this->crearReservaVencida(
            $tareaHistorica,
            $camarero,
            $dispositivo,
        );

        $operacionId = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacionId,
            'motivo' => 'Liberar claims vencidos que impiden publicar la frontera.',
            'limite' => 25,
        ];
        $url = '/api/intervenciones-planificador/reservas/expirar-vencidas';

        $this->actingAs($supervisor, 'sanctum')
            ->postJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('data.reservas_expiradas', 1);

        $this->assertSame(EstadoReservaTareaMovimiento::Expirada, $reserva->refresh()->estado);
        $this->assertSame(EstadoTareaMovimiento::Pendiente, $tarea->refresh()->estado);
        $this->assertSame(EstadoManiobraOperacional::Pendiente, $maniobra->refresh()->estado);
        $this->assertSame(
            EstadoReservaTareaMovimiento::Activa,
            $reservaHistorica->refresh()->estado,
        );

        $this->actingAs($supervisor, 'sanctum')
            ->postJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('data.reservas_expiradas', 1);

        $this->assertSame(
            1,
            TransicionOperacional::query()->where('operacion_id', $operacionId)->count(),
        );
        $this->assertSame(
            EstadoReservaTareaMovimiento::Activa,
            $reservaHistorica->refresh()->estado,
        );
    }

    private function usuario(RolUsuario $rol): User
    {
        return User::factory()->create([
            'rol' => $rol,
            'activo' => true,
        ]);
    }

    /**
     * @return array{ManiobraOperacional, TareaMovimiento}
     */
    private function crearManiobra(
        User $creador,
        EstadoManiobraOperacional $estadoManiobra = EstadoManiobraOperacional::Pendiente,
        EstadoTareaMovimiento $estadoTarea = EstadoTareaMovimiento::Pendiente,
        ?Temporada $temporada = null,
    ): array {
        $this->secuencia++;
        $temporada ??= Temporada::query()->where('activa', true)->firstOrFail();
        $indice = str_pad((string) $this->secuencia, 2, '0', STR_PAD_LEFT);
        $folio = Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => "PAL-INT-{$indice}",
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
        $plan = PlanOperacional::create([
            'temporada_id' => $temporada->id,
            'tipo' => 'reordenamiento_camara',
            'estado' => 'en_ejecucion',
            'prioridad' => 'normal',
            'titulo' => "Plan intervención {$indice}",
            'referencia_tipo' => 'prueba_intervencion',
            'referencia_id' => "INT-{$indice}",
            'creado_por_user_id' => $creador->id,
            'programado_at' => now(),
        ]);
        $maniobra = ManiobraOperacional::create([
            'plan_operacional_id' => $plan->id,
            'creado_por_user_id' => $creador->id,
            'estado' => $estadoManiobra,
            'prioridad' => PrioridadOperacional::Normal,
            'candidate_key' => "intervencion-{$indice}",
            'titulo' => "Maniobra intervención {$indice}",
            'secuencia_actual' => 1,
            'costo_movimientos' => 1,
            'version' => 1,
        ]);
        $tarea = TareaMovimiento::create([
            'plan_operacional_id' => $plan->id,
            'maniobra_operacional_id' => $maniobra->id,
            'secuencia' => 1,
            'secuencia_maniobra' => 1,
            'tipo_movimiento' => 'reubicacion',
            'tipo_paso_maniobra' => 'movimiento_permanente',
            'estado' => $estadoTarea,
            'prioridad' => PrioridadOperacional::Normal,
            'folio_id' => $folio->id,
            'instruccion' => 'Mover pallet de prueba.',
            'version' => 1,
        ]);

        return [$maniobra, $tarea];
    }

    private function crearReservaVencida(
        TareaMovimiento $tarea,
        User $usuario,
        Dispositivo $dispositivo,
    ): ReservaTareaMovimiento {
        return ReservaTareaMovimiento::create([
            'tarea_movimiento_id' => $tarea->id,
            'bloqueo_tarea_id' => $tarea->id,
            'estado' => EstadoReservaTareaMovimiento::Activa,
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'reservada_at' => now()->subMinutes(12),
            'renovada_at' => now()->subMinutes(12),
            'vence_at' => now()->subMinutes(2),
            'version' => 1,
        ]);
    }
}
