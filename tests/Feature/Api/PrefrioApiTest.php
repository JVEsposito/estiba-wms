<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\FuenteHabilitacionAlmacenamiento;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\PosicionTunelPrefrio;
use App\Models\ProcesoPrefrio;
use App\Models\RegistroHabilitacionAlmacenamiento;
use App\Models\TunelPrefrio;
use App\Models\User;
use App\Services\Folios\ServicioHabilitacionAlmacenamiento;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrefrioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_crea_tunel_configurable_y_operador_no_administra(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        [, $tokenOperador] = $this->acceso(RolUsuario::OperadorPrefrio, 'PF-OP-CONF');

        $tunelId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/prefrio/tuneles', [
                'nombre' => 'Túnel California grande',
                'capacidad_posiciones' => 40,
                'setpoint_habitual' => -1.5,
                'estado_tecnico' => 'operativo',
            ])
            ->assertCreated()
            ->assertJsonPath('data.codigo', 'TUN-01')
            ->assertJsonPath('data.capacidad_posiciones', 40)
            ->assertJsonCount(40, 'data.posiciones')
            ->assertJsonPath('data.posiciones.39.etiqueta', 'TUN-01-P40')
            ->json('data.id');

        $this->assertDatabaseHas('tuneles_prefrio', [
            'id' => $tunelId,
            'capacidad_posiciones' => 40,
            'estado_administrativo' => 'activo',
            'estado_tecnico' => 'operativo',
        ]);

        $this->conToken($tokenOperador)
            ->postJson('/api/administracion/prefrio/tuneles', [
                'nombre' => 'Túnel no autorizado',
                'capacidad_posiciones' => 22,
            ])
            ->assertForbidden();
    }

    public function test_tunel_admite_un_solo_proceso_activo_y_creacion_es_idempotente(): void
    {
        [$tunel, , $token] = $this->contexto();
        $operacionId = (string) Str::uuid();
        $payload = $this->payloadProceso($tunel->id, $operacionId);

        $procesoId = $this->conToken($token)
            ->postJson('/api/prefrio/procesos', $payload)
            ->assertCreated()
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonPath('data.version', 0)
            ->json('data.id');

        $this->conToken($token)
            ->postJson('/api/prefrio/procesos', $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', $procesoId);

        $payloadDistinto = $payload;
        $payloadDistinto['setpoint'] = -2;

        $this->conToken($token)
            ->postJson('/api/prefrio/procesos', $payloadDistinto)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->conToken($token)
            ->postJson('/api/prefrio/procesos', $this->payloadProceso(
                $tunel->id,
                (string) Str::uuid(),
            ))
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->assertSame(1, ProcesoPrefrio::query()->count());
    }

    public function test_aprobacion_habilita_almacenamiento_sin_dejar_folio_disponible_antes_de_camara(): void
    {
        [$tunel, $posicion, $tokenOperador] = $this->contexto();
        [, $tokenSupervisor] = $this->acceso(RolUsuario::SupervisorFrio, 'PF-SUP-01');
        $folio = $this->folioPendiente('PAL-PF-001');
        $proceso = $this->llevarAVerificacion(
            $tokenOperador,
            $tunel,
            $posicion,
            $folio,
        );

        $resultado = $this->accion(
            $tokenSupervisor,
            "/api/prefrio/procesos/{$proceso['id']}/aprobar",
            [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 4,
                'resultados' => [[
                    'folio_id' => $folio->id,
                    'temperatura_final' => -0.5,
                    'observacion' => 'Pulpa conforme.',
                ]],
                'ocurrido_at' => now()->toAtomString(),
            ],
        );

        $this->assertSame('aprobado', $resultado['estado']);
        $folio->refresh();
        $this->assertSame(CondicionTermicaFolio::PrefrioAprobado, $folio->condicion_termica);
        $this->assertSame(
            HabilitacionAlmacenamientoFolio::Habilitado,
            $folio->habilitacion_almacenamiento,
        );
        $this->assertSame(EstadoOperacionalFolio::PendientePrefrio, $folio->estado_operacional);

        $registro = RegistroHabilitacionAlmacenamiento::query()
            ->where('folio_id', $folio->id)
            ->where('estado_resultante', HabilitacionAlmacenamientoFolio::Habilitado)
            ->firstOrFail();
        $this->assertSame(HabilitacionAlmacenamientoFolio::Habilitado, $registro->estado_resultante);
        $this->assertSame(FuenteHabilitacionAlmacenamiento::PrefrioAprobado, $registro->fuente);
        $this->assertSame('prefrio', $registro->proceso_origen);
        $this->assertSame($proceso['id'], $registro->referencia_origen);
        $this->assertNotNull($registro->user_id);
        $this->assertNotNull($registro->dispositivo_id);

        app(ServicioHabilitacionAlmacenamiento::class)->validarIngresoCamara($folio);
    }

    public function test_reproceso_retiene_folio_y_conserva_historial_para_segundo_proceso(): void
    {
        [$tunel, $posicion, $tokenOperador] = $this->contexto();
        [, $tokenSupervisor] = $this->acceso(RolUsuario::SupervisorFrio, 'PF-SUP-02');
        $folio = $this->folioPendiente('PAL-PF-002');
        $proceso = $this->llevarAVerificacion(
            $tokenOperador,
            $tunel,
            $posicion,
            $folio,
        );

        $resultado = $this->accion(
            $tokenSupervisor,
            "/api/prefrio/procesos/{$proceso['id']}/reprocesar",
            [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 4,
                'motivo' => 'temperatura_fuera_rango',
                'resultados' => [[
                    'folio_id' => $folio->id,
                    'temperatura_final' => 1.4,
                ]],
                'ocurrido_at' => now()->toAtomString(),
            ],
        );

        $this->assertSame('requiere_reproceso', $resultado['estado']);
        $folio->refresh();
        $this->assertSame(CondicionTermicaFolio::RequiereReproceso, $folio->condicion_termica);
        $this->assertSame(HabilitacionAlmacenamientoFolio::Retenido, $folio->habilitacion_almacenamiento);
        $this->assertSame(EstadoOperacionalFolio::Bloqueado, $folio->estado_operacional);
        $this->assertDatabaseHas('historial_habilitaciones_almacenamiento', [
            'folio_id' => $folio->id,
            'estado_resultante' => HabilitacionAlmacenamientoFolio::Retenido->value,
            'condicion_termica' => CondicionTermicaFolio::RequiereReproceso->value,
            'proceso_origen' => 'prefrio',
            'referencia_origen' => $proceso['id'],
            'motivo' => 'temperatura_fuera_rango',
        ]);

        $nuevo = $this->crearProceso($tokenOperador, $tunel);
        $this->accion($tokenOperador, "/api/prefrio/procesos/{$nuevo['id']}/folios", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 0,
            'folio_id' => $folio->id,
            'posicion_tunel_prefrio_id' => $posicion->id,
            'ocurrido_at' => now()->toAtomString(),
        ]);

        $this->assertSame(2, $folio->procesosPrefrio()->count());
    }

    public function test_camara_usa_habilitacion_generica_y_no_exige_prefrio_para_saldos(): void
    {
        $servicio = app(ServicioHabilitacionAlmacenamiento::class);
        $pendiente = $this->folioPendiente('PAL-PF-003');

        try {
            $servicio->validarIngresoCamara($pendiente);
            $this->fail('El pallet pendiente de prefrío no debe ingresar a cámara.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('habilitado', $exception->getMessage());
        }

        $saldo = Folio::create([
            'numero_folio' => 'SALDO-REP-001',
            'tipo_bulto' => TipoBulto::Saldo,
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'fecha_ingreso' => now(),
            'activo' => true,
            'origen_sistema' => 'repaletizaje',
        ]);

        $servicio->prepararFolioManual($saldo);
        $saldo->refresh();
        $servicio->validarIngresoCamara($saldo);

        $this->assertSame(CondicionTermicaFolio::CondicionHeredada, $saldo->condicion_termica);
        $this->assertSame(
            HabilitacionAlmacenamientoFolio::Habilitado,
            $saldo->habilitacion_almacenamiento,
        );
        $this->assertSame(
            FuenteHabilitacionAlmacenamiento::CondicionHeredadaRepaletizaje,
            $saldo->fuente_habilitacion_almacenamiento,
        );
        $this->assertDatabaseHas('historial_habilitaciones_almacenamiento', [
            'folio_id' => $saldo->id,
            'fuente' => FuenteHabilitacionAlmacenamiento::CondicionHeredadaRepaletizaje->value,
            'proceso_origen' => 'repaletizaje',
        ]);
    }

    public function test_folio_terminal_no_puede_reingresar_a_camara_aunque_figure_habilitado(): void
    {
        $folio = Folio::create([
            'numero_folio' => 'PAL-PF-TERMINAL',
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::Despachado,
            'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('estado operacional');

        app(ServicioHabilitacionAlmacenamiento::class)->validarIngresoCamara($folio);
    }

    public function test_tunel_puede_reducirse_y_ampliarse_sin_duplicar_posiciones_inactivas(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $tunelId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/prefrio/tuneles', [
                'nombre' => 'Túnel redimensionable',
                'capacidad_posiciones' => 22,
                'estado_tecnico' => 'operativo',
            ])
            ->assertCreated()
            ->json('data.id');

        $payload = [
            'nombre' => 'Túnel redimensionable',
            'capacidad_posiciones' => 20,
            'estado_administrativo' => 'activo',
            'estado_tecnico' => 'operativo',
        ];

        $this->actingAs($administrador, 'sanctum')
            ->putJson("/api/administracion/prefrio/tuneles/{$tunelId}", $payload)
            ->assertOk()
            ->assertJsonPath('data.capacidad_posiciones', 20);

        $payload['capacidad_posiciones'] = 22;
        $this->actingAs($administrador, 'sanctum')
            ->putJson("/api/administracion/prefrio/tuneles/{$tunelId}", $payload)
            ->assertOk()
            ->assertJsonPath('data.capacidad_posiciones', 22)
            ->assertJsonCount(22, 'data.posiciones');

        $this->assertSame(22, PosicionTunelPrefrio::query()
            ->where('tunel_prefrio_id', $tunelId)
            ->count());
        $this->assertSame(22, PosicionTunelPrefrio::query()
            ->where('tunel_prefrio_id', $tunelId)
            ->where('activa', true)
            ->count());
    }

    public function test_tunel_rechaza_capacidad_impar_para_conservar_dos_lados_por_profundidad(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/prefrio/tuneles', [
                'nombre' => 'Túnel ambiguo',
                'capacidad_posiciones' => 21,
                'estado_tecnico' => 'operativo',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capacidad_posiciones');
    }

    public function test_eventos_son_idempotentes_y_operador_prefrio_permanece_aislado(): void
    {
        [$tunel, $posicion, $token] = $this->contexto();
        $folio = $this->folioPendiente('PAL-PF-004');
        $proceso = $this->crearProceso($token, $tunel);
        $proceso = $this->accion($token, "/api/prefrio/procesos/{$proceso['id']}/folios", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 0,
            'folio_id' => $folio->id,
            'posicion_tunel_prefrio_id' => $posicion->id,
            'ocurrido_at' => now()->toAtomString(),
        ]);
        $operacionId = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacionId,
            'version_conocida' => 1,
            'observacion' => 'Armado verificado.',
            'ocurrido_at' => now()->toAtomString(),
        ];

        $primera = $this->accion(
            $token,
            "/api/prefrio/procesos/{$proceso['id']}/confirmar-armado",
            $payload,
        );
        $segunda = $this->accion(
            $token,
            "/api/prefrio/procesos/{$proceso['id']}/confirmar-armado",
            $payload,
        );

        $this->assertSame($primera['version'], $segunda['version']);

        $payload['observacion'] = 'Payload diferente.';
        $this->conToken($token)
            ->postJson(
                "/api/prefrio/procesos/{$proceso['id']}/confirmar-armado",
                $payload,
            )
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->conToken($token)
            ->getJson('/api/camaras')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->conToken($token)->getJson('/api/cargas')->assertForbidden();
        $this->conToken($token)->getJson('/api/materiales/inventario')->assertForbidden();
        $this->conToken($token)->getJson('/api/validacion/pallets')->assertForbidden();
    }

    /**
     * @return array{TunelPrefrio, PosicionTunelPrefrio, string}
     */
    private function contexto(): array
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        [, $token] = $this->acceso(RolUsuario::OperadorPrefrio, 'PF-OP-'.Str::random(6));
        $tunelId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/prefrio/tuneles', [
                'nombre' => 'Túnel de prueba',
                'capacidad_posiciones' => 22,
                'setpoint_habitual' => -1.5,
                'estado_tecnico' => 'operativo',
            ])
            ->assertCreated()
            ->json('data.id');
        $tunel = TunelPrefrio::query()->findOrFail($tunelId);
        $posicion = $tunel->posiciones()->orderBy('numero')->firstOrFail();

        return [$tunel, $posicion, $token];
    }

    private function folioPendiente(string $numero): Folio
    {
        return Folio::create([
            'numero_folio' => $numero,
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::PendientePrefrio,
            'condicion_termica' => CondicionTermicaFolio::PendientePrefrio,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::NoHabilitado,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function crearProceso(string $token, TunelPrefrio $tunel): array
    {
        return $this->conToken($token)
            ->postJson('/api/prefrio/procesos', $this->payloadProceso(
                $tunel->id,
                (string) Str::uuid(),
            ))
            ->assertCreated()
            ->json('data');
    }

    /**
     * @return array<string, mixed>
     */
    private function llevarAVerificacion(
        string $token,
        TunelPrefrio $tunel,
        PosicionTunelPrefrio $posicion,
        Folio $folio,
    ): array {
        $proceso = $this->crearProceso($token, $tunel);
        $proceso = $this->accion($token, "/api/prefrio/procesos/{$proceso['id']}/folios", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 0,
            'folio_id' => $folio->id,
            'posicion_tunel_prefrio_id' => $posicion->id,
            'temperatura_inicial' => 9.2,
            'ocurrido_at' => now()->toAtomString(),
        ]);
        $proceso = $this->accion(
            $token,
            "/api/prefrio/procesos/{$proceso['id']}/confirmar-armado",
            $this->payloadAccion(1),
        );
        $proceso = $this->accion(
            $token,
            "/api/prefrio/procesos/{$proceso['id']}/iniciar",
            $this->payloadAccion(2),
        );

        return $this->accion(
            $token,
            "/api/prefrio/procesos/{$proceso['id']}/verificar",
            $this->payloadAccion(3),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadProceso(string $tunelId, string $operacionId): array
    {
        return [
            'operacion_id' => $operacionId,
            'tunel_prefrio_id' => $tunelId,
            'setpoint' => -1.5,
            'duracion_objetivo_minutos' => 720,
            'formato_referencia' => 'Granel 5 kg',
            'ocurrido_at' => now()->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadAccion(int $version): array
    {
        return [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => $version,
            'ocurrido_at' => now()->toAtomString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function accion(string $token, string $ruta, array $payload): array
    {
        return $this->conToken($token)
            ->postJson($ruta, $payload)
            ->assertOk()
            ->json('data');
    }

    /**
     * @return array{User, string}
     */
    private function acceso(RolUsuario $rol, string $codigo): array
    {
        $usuario = User::factory()->create(['rol' => $rol]);
        $dispositivo = Dispositivo::create([
            'codigo' => mb_strtoupper($codigo),
            'nombre' => "PDA {$codigo}",
            'plataforma' => 'android',
            'activo' => true,
        ]);
        $token = $usuario->crearTokenParaDispositivo(
            $dispositivo,
            "test-{$codigo}",
        )->plainTextToken;

        return [$usuario, $token];
    }

    private function conToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
