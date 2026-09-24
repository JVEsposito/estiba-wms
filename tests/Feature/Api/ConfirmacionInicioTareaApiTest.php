<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPlanOperacional;
use App\Models\Camara;
use App\Models\ConfirmacionInicioTarea;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Posicion;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Autenticacion\ServicioPinOperacional;
use App\Services\Camaras\ServicioBandasOperacionales;
use App\Services\Estiba\ServicioConfirmacionInicioTarea;
use App\Services\Estiba\ServicioPlanesOperacionales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmacionInicioTareaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_digitos_esperados_son_los_ultimos_cuatro_del_folio(): void
    {
        $this->assertSame('5871', ServicioConfirmacionInicioTarea::digitosEsperados('PT-2026-0045871'));
        $this->assertSame('001', ServicioConfirmacionInicioTarea::digitosEsperados('ROLL-001'));
        $this->assertSame('ABCD', ServicioConfirmacionInicioTarea::digitosEsperados('xyzabcd'));
        $this->assertTrue(ServicioConfirmacionInicioTarea::coincide('PT-2026-0045871', '5871'));
        $this->assertTrue(ServicioConfirmacionInicioTarea::coincide('PT-2026-0045871', ' pt-2026-0045871 '));
        $this->assertFalse(ServicioConfirmacionInicioTarea::coincide('PT-2026-0045871', '5872'));
        $this->assertFalse(ServicioConfirmacionInicioTarea::coincide('PT-2026-0045871', ''));
    }

    public function test_iniciar_exige_digitos_del_folio_y_pin(): void
    {
        $contexto = $this->prepararTareaAsumida();

        $this->iniciar($contexto, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmacion_folio', 'pin']);

        $this->assertSame('asumida', $contexto['tarea']->refresh()->estado->value);
        $this->assertDatabaseCount('confirmaciones_inicio_tarea', 0);
    }

    public function test_digitos_equivocados_rechazan_y_quedan_registrados(): void
    {
        $contexto = $this->prepararTareaAsumida();

        $this->iniciar($contexto, ['confirmacion_folio' => '5872', 'pin' => '2580'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmacion_folio'])
            ->assertJsonMissingValidationErrors(['pin']);

        $this->assertSame('asumida', $contexto['tarea']->refresh()->estado->value);
        $this->assertDatabaseHas('confirmaciones_inicio_tarea', [
            'tarea_movimiento_id' => $contexto['tarea']->id,
            'user_id' => $contexto['camarero']->id,
            'dispositivo_id' => $contexto['dispositivo']->id,
            'numero_folio' => 'PT-2026-0045871',
            'resultado' => ConfirmacionInicioTarea::RECHAZADA_FOLIO,
            'digitos_ingresados' => '5872',
        ]);
    }

    public function test_pin_incorrecto_rechaza_y_descuenta_intentos(): void
    {
        $contexto = $this->prepararTareaAsumida();

        $this->iniciar($contexto, ['confirmacion_folio' => '5871', 'pin' => '1397'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin'])
            ->assertJsonPath('errors.pin.0', 'PIN incorrecto. Te quedan 4 intentos antes del bloqueo.');

        $this->assertSame('asumida', $contexto['tarea']->refresh()->estado->value);
        $this->assertSame(1, $contexto['camarero']->refresh()->pin_operacional_intentos_fallidos);
        $this->assertDatabaseHas('confirmaciones_inicio_tarea', [
            'tarea_movimiento_id' => $contexto['tarea']->id,
            'resultado' => ConfirmacionInicioTarea::RECHAZADA_PIN,
        ]);
    }

    public function test_confirmacion_correcta_inicia_la_tarea_y_registra_quien_confirmo(): void
    {
        $contexto = $this->prepararTareaAsumida();

        $this->iniciar($contexto, ['confirmacion_folio' => '5871', 'pin' => '2580'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_proceso');

        $confirmacion = ConfirmacionInicioTarea::query()->sole();
        $this->assertSame(ConfirmacionInicioTarea::CONFIRMADA, $confirmacion->resultado);
        $this->assertSame($contexto['camarero']->id, $confirmacion->user_id);
        $this->assertSame($contexto['dispositivo']->id, $confirmacion->dispositivo_id);
        $this->assertSame($contexto['folio']->id, $confirmacion->folio_id);
    }

    public function test_el_folio_completo_leido_por_escaner_tambien_confirma(): void
    {
        $contexto = $this->prepararTareaAsumida();

        $this->iniciar($contexto, ['confirmacion_folio' => 'PT-2026-0045871', 'pin' => '2580'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_proceso');
    }

    public function test_cinco_pin_incorrectos_bloquean_hasta_que_expire_o_se_restablezca(): void
    {
        $contexto = $this->prepararTareaAsumida();

        foreach (range(1, ServicioPinOperacional::INTENTOS_MAXIMOS) as $intento) {
            $this->iniciar($contexto, ['confirmacion_folio' => '5871', 'pin' => '1397'])
                ->assertUnprocessable();
        }

        $this->assertNotNull($contexto['camarero']->refresh()->pin_operacional_bloqueado_hasta);

        $this->iniciar($contexto, ['confirmacion_folio' => '5871', 'pin' => '2580'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);
        $this->assertStringContainsString(
            'PIN bloqueado',
            $this->iniciar($contexto, ['confirmacion_folio' => '5871', 'pin' => '2580'])->json('errors.pin.0'),
        );

        $this->travel(ServicioPinOperacional::BLOQUEO_MINUTOS + 1)->minutes();

        $this->iniciar($contexto, ['confirmacion_folio' => '5871', 'pin' => '2580'])
            ->assertOk();
    }

    public function test_la_exigencia_puede_desactivarse_como_rollback(): void
    {
        config(['planificador.confirmacion_inicio_tarea' => false]);
        $contexto = $this->prepararTareaAsumida();

        $this->iniciar($contexto, [])
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_proceso');
        $this->assertDatabaseCount('confirmaciones_inicio_tarea', 0);
    }

    public function test_el_camarero_crea_y_cambia_su_pin(): void
    {
        $contexto = $this->prepararTareaAsumida(conPin: false);

        $this->conToken($contexto['token'])
            ->getJson('/api/usuario/pin')
            ->assertOk()
            ->assertJsonPath('data.configurado', false);

        foreach (['1234', '0000', '9876', 'ab12', '25801'] as $invalido) {
            $this->conToken($contexto['token'])
                ->putJson('/api/usuario/pin', ['pin' => $invalido, 'pin_confirmation' => $invalido])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['pin']);
        }

        // El endpoint limita a 6 intentos por minuto.
        $this->travel(1)->minutes();

        $this->conToken($contexto['token'])
            ->putJson('/api/usuario/pin', ['pin' => '2580', 'pin_confirmation' => '2508'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);

        $this->conToken($contexto['token'])
            ->putJson('/api/usuario/pin', ['pin' => '2580', 'pin_confirmation' => '2580'])
            ->assertOk()
            ->assertJsonPath('data.configurado', true);

        $this->conToken($contexto['token'])
            ->putJson('/api/usuario/pin', ['pin' => '3691', 'pin_confirmation' => '3691'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin_actual']);

        $this->conToken($contexto['token'])
            ->putJson('/api/usuario/pin', ['pin' => '3691', 'pin_confirmation' => '3691', 'pin_actual' => '2580'])
            ->assertOk();

        $this->iniciar($contexto, ['confirmacion_folio' => '5871', 'pin' => '3691'])
            ->assertOk();
        $this->assertArrayNotHasKey('pin_operacional_hash', $contexto['camarero']->refresh()->toArray());
    }

    public function test_administracion_y_supervision_de_frio_restablecen_el_pin_de_operadores(): void
    {
        $contexto = $this->prepararTareaAsumida();
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador, 'activo' => true]);
        $otroSupervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio, 'activo' => true]);

        $this->actingAs($contexto['supervisor'], 'sanctum')
            ->getJson('/api/operacion/pines-operadores')
            ->assertOk()
            ->assertJsonFragment(['id' => $contexto['camarero']->id, 'rol' => 'camarero_frio'])
            ->assertJsonMissing(['id' => $administrador->id]);

        // El supervisor de frío no restablece a administradores, a otros supervisores ni a sí mismo.
        foreach ([$administrador, $otroSupervisor, $contexto['supervisor']] as $objetivo) {
            $this->actingAs($contexto['supervisor'], 'sanctum')
                ->postJson("/api/administracion/usuarios/{$objetivo->id}/restablecer-pin")
                ->assertForbidden();
        }
        $this->actingAs($contexto['camarero'], 'sanctum')
            ->getJson('/api/operacion/pines-operadores')
            ->assertForbidden();

        $this->actingAs($contexto['supervisor'], 'sanctum')
            ->postJson("/api/administracion/usuarios/{$contexto['camarero']->id}/restablecer-pin")
            ->assertOk()
            ->assertJsonPath('data.configurado', false);
        $this->assertSame(
            $contexto['supervisor']->id,
            $contexto['camarero']->refresh()->pin_operacional_restablecido_por_user_id,
        );

        $this->iniciar($contexto, ['confirmacion_folio' => '5871', 'pin' => '2580'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.pin.0', 'Crea tu PIN operacional antes de confirmar.');

        $this->actingAs($administrador, 'sanctum')
            ->postJson("/api/administracion/usuarios/{$otroSupervisor->id}/restablecer-pin")
            ->assertOk();
    }

    /** @return array<string, mixed> */
    private function prepararTareaAsumida(bool $conPin = true): array
    {
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-CONFIRMA-2026',
            'nombre' => 'Temporada confirmación 2026',
            'fecha_inicio' => '2026-01-01',
            'activa' => true,
        ]);
        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio, 'activo' => true]);
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio, 'activo' => true]);
        $dispositivo = Dispositivo::create(['codigo' => 'TABLET-CONFIRMA-01', 'nombre' => 'Tablet confirmación']);
        $token = $camarero->crearTokenParaDispositivo($dispositivo, 'tablet-confirmacion')->plainTextToken;
        $camara = Camara::create([
            'codigo' => 'CAM-CONF',
            'nombre' => 'Cámara confirmación',
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 2,
            'cantidad_niveles' => 1,
        ]);
        app(ServicioBandasOperacionales::class)->sincronizar($camara->refresh(), $supervisor);
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => 'B01-P01-N1',
        ]);
        $folio = Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => 'PT-2026-0045871',
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
        ]);
        $servicio = app(ServicioPlanesOperacionales::class);
        $plan = $servicio->crear(
            temporada: $temporada,
            tipo: TipoPlanOperacional::AlmacenamientoPallet,
            titulo: 'Confirmación antes de retirar',
            creadoPor: $supervisor,
            tareas: [[
                'folio_id' => $folio->id,
                'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
                'instruccion' => 'Ubicar el pallet validado.',
            ]],
            contexto: ['planner_horizon' => 'rolling'],
        );
        /** @var TareaMovimiento $tarea */
        $tarea = $plan->tareas->firstOrFail();
        $servicio->asumir($tarea, $camarero, $dispositivo);
        $servicio->materializarDestino($tarea->refresh(), $posicion, $camarero, $dispositivo);

        if ($conPin) {
            app(ServicioPinOperacional::class)->configurar($camarero, '2580', null);
        }

        return [
            'supervisor' => $supervisor,
            'camarero' => $camarero->refresh(),
            'dispositivo' => $dispositivo,
            'token' => $token,
            'folio' => $folio,
            'tarea' => $tarea->refresh(),
        ];
    }

    /** @param array<string, string> $datos */
    private function iniciar(array $contexto, array $datos)
    {
        return $this->conToken($contexto['token'])
            ->postJson("/api/tareas-movimiento/{$contexto['tarea']->id}/iniciar", $datos);
    }

    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
