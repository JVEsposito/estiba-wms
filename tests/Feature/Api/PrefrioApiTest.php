<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoOperacionSincronizacion;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoSesionEstiba;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Movimiento;
use App\Models\OperacionSincronizacion;
use App\Models\Posicion;
use App\Models\ProcesoPrefrio;
use App\Models\SesionEstiba;
use App\Models\UbicacionActual;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrefrioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_crea_tunel_con_posiciones_y_operador_no_puede_configurarlo(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        [, $tokenOperador] = $this->accesoTablet(RolUsuario::OperadorPrefrio, 'PF-CONF-01');

        $tunelId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/prefrio/tuneles', [
                'nombre' => 'Túnel California 01',
                'capacidad_posiciones' => 22,
                'setpoint_habitual' => -1.5,
                'estado_tecnico' => 'operativo',
                'codigo_externo' => 'T-EXT-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.codigo', 'TUN-01')
            ->assertJsonPath('data.capacidad_posiciones', 22)
            ->assertJsonCount(22, 'data.posiciones')
            ->assertJsonPath('data.posiciones.0.etiqueta', 'TUN-01-P01')
            ->json('data.id');

        $this->assertDatabaseHas('tuneles_prefrio', [
            'id' => $tunelId,
            'capacidad_posiciones' => 22,
            'estado_administrativo' => 'activo',
            'estado_tecnico' => 'operativo',
        ]);

        $this->conToken($tokenOperador)
            ->postJson('/api/administracion/prefrio/tuneles', [
                'nombre' => 'Túnel no autorizado',
                'capacidad_posiciones' => 10,
                'setpoint_habitual' => -1.5,
            ])
            ->assertForbidden();
    }

    public function test_un_tunel_no_admite_dos_procesos_activos_y_la_creacion_es_idempotente(): void
    {
        [$tunel, $posicion, $operador, $token] = $this->contextoPrefrio();
        $operacionId = (string) Str::uuid();
        $payload = $this->payloadCrearProceso($tunel->id, $operacionId);

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
        $payloadDistinto['setpoint'] = -2.0;

        $this->conToken($token)
            ->postJson('/api/prefrio/procesos', $payloadDistinto)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->conToken($token)
            ->postJson('/api/prefrio/procesos', $this->payloadCrearProceso(
                $tunel->id,
                (string) Str::uuid(),
            ))
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->assertSame(1, ProcesoPrefrio::query()->count());
        $this->assertNotNull($posicion->id);
        $this->assertSame(RolUsuario::OperadorPrefrio, $operador->rol);
    }

    public function test_flujo_aprobado_habilita_folio_y_solo_se_vuelve_disponible_al_ingresar_a_camara(): void
    {
        [$tunel, $posicion, , $tokenOperador] = $this->contextoPrefrio();
        [, $tokenSupervisor] = $this->accesoTablet(RolUsuario::SupervisorFrio, 'PF-SUP-01');
        $folio = $this->crearFolioPendiente('PAL-PF-001');
        $proceso = $this->crearProceso($tokenOperador, $tunel->id);

        $proceso = $this->accion($tokenOperador, "/api/prefrio/procesos/{$proceso['id']}/folios", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 0,
            'folio_id' => $folio->id,
            'posicion_tunel_prefrio_id' => $posicion->id,
            'temperatura_inicial' => 9.2,
            'ocurrido_at' => now()->toAtomString(),
        ]);
        $this->assertSame(1, $proceso['version']);

        $proceso = $this->accion($tokenOperador, "/api/prefrio/procesos/{$proceso['id']}/confirmar-armado", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 1,
            'ocurrido_at' => now()->toAtomString(),
        ]);
        $this->assertSame('listo_para_iniciar', $proceso['estado']);

        $proceso = $this->accion($tokenOperador, "/api/prefrio/procesos/{$proceso['id']}/iniciar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 2,
            'ocurrido_at' => now()->toAtomString(),
        ]);
        $this->assertSame('en_proceso', $proceso['estado']);
        $this->assertSame(CondicionTermicaFolio::EnProceso, $folio->refresh()->condicion_termica);
        $this->assertSame(
            HabilitacionAlmacenamientoFolio::NoHabilitado,
            $folio->habilitacion_almacenamiento,
        );

        $proceso = $this->accion($tokenOperador, "/api/prefrio/procesos/{$proceso['id']}/verificar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 3,
            'ocurrido_at' => now()->toAtomString(),
        ]);
        $this->assertSame('pendiente_verificacion', $proceso['estado']);

        $proceso = $this->accion($tokenSupervisor, "/api/prefrio/procesos/{$proceso['id']}/aprobar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 4,
            'resultados' => [[
                'folio_id' => $folio->id,
                'temperatura_final' => -0.5,
                'observacion' => 'Pulpa conforme.',
            ]],
            'ocurrido_at' => now()->toAtomString(),
        ]);
        $this->assertSame('aprobado', $proceso['estado']);

        $folio->refresh();
        $this->assertSame(CondicionTermicaFolio::PrefrioAprobado, $folio->condicion_termica);
        $this->assertSame(
            HabilitacionAlmacenamientoFolio::Habilitado,
            $folio->habilitacion_almacenamiento,
        );
        $this->assertSame(EstadoOperacionalFolio::PendientePrefrio, $folio->estado_operacional);

        $this->ubicarEnCamara($folio, 'CAM-PF-01', 1);

        $this->assertSame(EstadoOperacionalFolio::Disponible, $folio->refresh()->estado_operacional);
    }

    public function test_reproceso_retiene_folio_y_permite_un_nuevo_proceso_historico(): void
    {
        [$tunel, $posicion, , $tokenOperador] = $this->contextoPrefrio();
        [, $tokenSupervisor] = $this->accesoTablet(RolUsuario::SupervisorFrio, 'PF-SUP-02');
        $folio = $this->crearFolioPendiente('PAL-PF-002');
        $proceso = $this->llevarAVerificacion($tokenOperador, $tunel->id, $posicion->id, $folio);

        $proceso = $this->accion($tokenSupervisor, "/api/prefrio/procesos/{$proceso['id']}/reprocesar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 4,
            'motivo' => 'temperatura_fuera_rango',
            'resultados' => [[
                'folio_id' => $folio->id,
                'temperatura_final' => 1.4,
            ]],
            'ocurrido_at' => now()->toAtomString(),
        ]);
        $this->assertSame('requiere_reproceso', $proceso['estado']);

        $folio->refresh();
        $this->assertSame(CondicionTermicaFolio::RequiereReproceso, $folio->condicion_termica);
        $this->assertSame(HabilitacionAlmacenamientoFolio::Retenido, $folio->habilitacion_almacenamiento);
        $this->assertSame(EstadoOperacionalFolio::Bloqueado, $folio->estado_operacional);

        $nuevo = $this->crearProceso($tokenOperador, $tunel->id);
        $this->accion($tokenOperador, "/api/prefrio/procesos/{$nuevo['id']}/folios", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 0,
            'folio_id' => $folio->id,
            'posicion_tunel_prefrio_id' => $posicion->id,
            'ocurrido_at' => now()->toAtomString(),
        ]);

        $this->assertSame(2, $folio->procesosPrefrio()->count());
    }

    public function test_evento_repetido_es_idempotente_y_uuid_con_payload_distinto_conflicta(): void
    {
        [$tunel, $posicion, , $token] = $this->contextoPrefrio();
        $folio = $this->crearFolioPendiente('PAL-PF-003');
        $proceso = $this->crearProceso($token, $tunel->id);
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

        $primera = $this->accion($token, "/api/prefrio/procesos/{$proceso['id']}/confirmar-armado", $payload);
        $segunda = $this->accion($token, "/api/prefrio/procesos/{$proceso['id']}/confirmar-armado", $payload);

        $this->assertSame($primera['version'], $segunda['version']);
        $this->assertSame(1, $primera['eventos'][0]['tipo'] === 'armado_confirmado' ? 1 : 0);

        $payload['observacion'] = 'Contenido diferente.';
        $this->conToken($token)
            ->postJson("/api/prefrio/procesos/{$proceso['id']}/confirmar-armado", $payload)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_operacional');
    }

    public function test_camara_rechaza_pendiente_y_acepta_folio_de_condicion_heredada_sin_exigir_prefrio(): void
    {
        $pendiente = $this->crearFolioPendiente('PAL-PF-004');
        [$camara, $posicion, $sesion, $usuario, $dispositivo] = $this->contextoCamara('CAM-PF-02', 1);
        $movimiento = $this->crearMovimiento($pendiente, $posicion, $sesion, $usuario, $dispositivo);

        try {
            UbicacionActual::create([
                'folio_id' => $pendiente->id,
                'posicion_id' => $posicion->id,
                'movimiento_id' => $movimiento->id,
                'ubicado_at' => now(),
            ]);
            $this->fail('Un folio pendiente de prefrío no debe ingresar a cámara.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('habilitado', $exception->getMessage());
        }

        $manual = Folio::create([
            'numero_folio' => 'PAL-SALDO-001',
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'origen_sistema' => 'repaletizaje',
        ]);
        $posicionManual = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 2,
            'nivel' => 1,
            'etiqueta' => 'B01-P02-N1',
        ]);
        $movimientoManual = $this->crearMovimiento(
            $manual,
            $posicionManual,
            $sesion,
            $usuario,
            $dispositivo,
        );

        UbicacionActual::create([
            'folio_id' => $manual->id,
            'posicion_id' => $posicionManual->id,
            'movimiento_id' => $movimientoManual->id,
            'ubicado_at' => now(),
        ]);

        $manual->refresh();
        $this->assertSame(CondicionTermicaFolio::CondicionHeredada, $manual->condicion_termica);
        $this->assertSame(HabilitacionAlmacenamientoFolio::Habilitado, $manual->habilitacion_almacenamiento);
        $this->assertSame(EstadoOperacionalFolio::Disponible, $manual->estado_operacional);
    }

    public function test_operador_prefrio_no_ve_camaras_cargas_materiales_ni_validacion(): void
    {
        [, $token] = $this->accesoTablet(RolUsuario::OperadorPrefrio, 'PF-SEG-01');

        $this->conToken($token)
            ->getJson('/api/camaras')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->conToken($token)->getJson('/api/cargas')->assertForbidden();
        $this->conToken($token)->getJson('/api/materiales/inventario')->assertForbidden();
        $this->conToken($token)->getJson('/api/validacion/pallets')->assertForbidden();
    }

    /**
     * @return array{\App\Models\TunelPrefrio, \App\Models\PosicionTunelPrefrio, User, string}
     */
    private function contextoPrefrio(): array
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        [, $token] = $this->accesoTablet(RolUsuario::OperadorPrefrio, 'PF-OP-'.Str::random(5));
        $tunelId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/prefrio/tuneles', [
                'nombre' => 'Túnel de prueba',
                'capacidad_posiciones' => 22,
                'setpoint_habitual' => -1.5,
                'estado_tecnico' => 'operativo',
            ])
            ->assertCreated()
            ->json('data.id');
        $tunel = \App\Models\TunelPrefrio::query()->findOrFail($tunelId);
        $posicion = $tunel->posiciones()->orderBy('numero')->firstOrFail();
        $operador = User::query()->where('rol', RolUsuario::OperadorPrefrio->value)->latest('id')->firstOrFail();

        return [$tunel, $posicion, $operador, $token];
    }

    /**
     * @return array<string, mixed>
     */
    private function crearProceso(string $token, string $tunelId): array
    {
        return $this->conToken($token)
            ->postJson('/api/prefrio/procesos', $this->payloadCrearProceso(
                $tunelId,
                (string) Str::uuid(),
            ))
            ->assertCreated()
            ->json('data');
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadCrearProceso(string $tunelId, string $operacionId): array
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
     * @return array<string, mixed>
     */
    private function llevarAVerificacion(
        string $token,
        string $tunelId,
        string $posicionId,
        Folio $folio,
    ): array {
        $proceso = $this->crearProceso($token, $tunelId);
        $proceso = $this->accion($token, "/api/prefrio/procesos/{$proceso['id']}/folios", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 0,
            'folio_id' => $folio->id,
            'posicion_tunel_prefrio_id' => $posicionId,
            'ocurrido_at' => now()->toAtomString(),
        ]);
        $proceso = $this->accion($token, "/api/prefrio/procesos/{$proceso['id']}/confirmar-armado", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 1,
            'ocurrido_at' => now()->toAtomString(),
        ]);
        $proceso = $this->accion($token, "/api/prefrio/procesos/{$proceso['id']}/iniciar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 2,
            'ocurrido_at' => now()->toAtomString(),
        ]);

        return $this->accion($token, "/api/prefrio/procesos/{$proceso['id']}/verificar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => 3,
            'ocurrido_at' => now()->toAtomString(),
        ]);
    }

    private function crearFolioPendiente(string $numero): Folio
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

    private function ubicarEnCamara(Folio $folio, string $codigo, int $numero): void
    {
        [$camara, $posicion, $sesion, $usuario, $dispositivo] = $this->contextoCamara($codigo, $numero);
        $movimiento = $this->crearMovimiento($folio, $posicion, $sesion, $usuario, $dispositivo);

        UbicacionActual::create([
            'folio_id' => $folio->id,
            'posicion_id' => $posicion->id,
            'movimiento_id' => $movimiento->id,
            'ubicado_at' => now(),
        ]);
    }

    /**
     * @return array{Camara, Posicion, SesionEstiba, User, Dispositivo}
     */
    private function contextoCamara(string $codigo, int $posicionNumero): array
    {
        $usuario = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'CAM-'.Str::random(8),
            'nombre' => 'Tablet cámara',
            'activo' => true,
        ]);
        $camara = Camara::create([
            'codigo' => $codigo,
            'nombre' => "Cámara {$codigo}",
            'tipo' => 'almacenaje',
            'contenido' => 'productos',
        ]);
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => $posicionNumero,
            'nivel' => 1,
            'etiqueta' => sprintf('B01-P%02d-N1', $posicionNumero),
        ]);
        $sesion = SesionEstiba::create([
            'camara_id' => $camara->id,
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'estado' => EstadoSesionEstiba::Abierta,
            'version_inicial' => 0,
            'iniciada_at' => now(),
        ]);

        return [$camara, $posicion, $sesion, $usuario, $dispositivo];
    }

    private function crearMovimiento(
        Folio $folio,
        Posicion $posicion,
        SesionEstiba $sesion,
        User $usuario,
        Dispositivo $dispositivo,
    ): Movimiento {
        $operacion = OperacionSincronizacion::create([
            'id' => (string) Str::uuid(),
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'tipo' => TipoMovimiento::UbicacionInicial,
            'estado' => EstadoOperacionSincronizacion::Aceptada,
            'payload_hash' => hash('sha256', (string) Str::uuid()),
            'payload' => ['folio_id' => $folio->id],
            'generada_dispositivo_at' => now(),
            'recibida_servidor_at' => now(),
            'procesada_at' => now(),
        ]);

        return Movimiento::create([
            'operacion_id' => $operacion->id,
            'folio_id' => $folio->id,
            'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
            'camara_destino_id' => $posicion->camara_id,
            'posicion_destino_id' => $posicion->id,
            'sesion_destino_id' => $sesion->id,
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'version_destino_anterior' => 0,
            'version_destino_resultante' => 1,
            'generado_dispositivo_at' => now(),
            'recibido_servidor_at' => now(),
        ]);
    }

    /**
     * @return array{User, string}
     */
    private function accesoTablet(RolUsuario $rol, string $codigo): array
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
