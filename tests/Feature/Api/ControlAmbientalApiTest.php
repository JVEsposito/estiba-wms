<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\CorreccionControlAmbiental;
use App\Models\Dispositivo;
use App\Models\RegistroControlAmbiental;
use App\Models\User;
use App\Services\Autorizacion\CatalogoModulosAcceso;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ControlAmbientalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_camarero_registra_las_tres_temperaturas_con_usuario_y_dispositivo(): void
    {
        Carbon::setTestNow('2026-09-07 16:00:00');
        $camara = $this->crearCamara('CAM-01');
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $token = $this->tokenTablet($camarero);

        $this->withToken($token)
            ->postJson(
                "/api/control-ambiental/camaras/{$camara->id}/registros",
                $this->payloadRegistro('2026-09-07T15:45:00Z'),
            )
            ->assertCreated()
            ->assertJsonPath('data.camara.id', $camara->id)
            ->assertJsonPath('data.temperaturas.inicio_c', -0.8)
            ->assertJsonPath('data.temperaturas.medio_c', -0.6)
            ->assertJsonPath('data.temperaturas.fondo_c', -0.7)
            ->assertJsonPath('data.frecuencia_minutos', 60)
            ->assertJsonPath('data.estado_vigencia', 'vigente')
            ->assertJsonPath('data.registrado_por.id', $camarero->id);

        $registro = RegistroControlAmbiental::query()->firstOrFail();
        $this->assertSame($camara->id, $registro->camara_id);
        $this->assertSame($camarero->id, $registro->registrado_por_user_id);
        $this->assertNotNull($registro->dispositivo_id);
        $this->assertSame('2026-09-07T15:45:00+00:00', $registro->capturado_at->toAtomString());
    }

    public function test_reintento_es_idempotente_y_un_uuid_reutilizado_con_otro_payload_conflicta(): void
    {
        Carbon::setTestNow('2026-09-07 16:00:00');
        $camara = $this->crearCamara('CAM-02');
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $token = $this->tokenTablet($camarero);
        $payload = $this->payloadRegistro('2026-09-07T15:45:00Z');
        $ruta = "/api/control-ambiental/camaras/{$camara->id}/registros";

        $this->withToken($token)->postJson($ruta, $payload)->assertCreated();
        $this->withToken($token)->postJson($ruta, $payload)->assertOk();

        $this->withToken($token)
            ->postJson($ruta, [
                ...$payload,
                'temperatura_medio_c' => '-0.10',
            ])
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->assertSame(1, RegistroControlAmbiental::query()->count());
    }

    public function test_impide_duplicados_en_menos_de_sesenta_minutos_y_admite_el_limite_exacto(): void
    {
        Carbon::setTestNow('2026-09-07 17:00:00');
        $camara = $this->crearCamara('CAM-03');
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $token = $this->tokenTablet($camarero);
        $ruta = "/api/control-ambiental/camaras/{$camara->id}/registros";

        $this->withToken($token)
            ->postJson($ruta, $this->payloadRegistro('2026-09-07T15:00:00Z'))
            ->assertCreated();
        $this->withToken($token)
            ->postJson($ruta, $this->payloadRegistro(
                '2026-09-07T15:59:59Z',
                (string) Str::uuid(),
            ))
            ->assertConflict();
        $this->withToken($token)
            ->postJson($ruta, $this->payloadRegistro(
                '2026-09-07T16:00:00Z',
                (string) Str::uuid(),
            ))
            ->assertCreated();

        $this->assertSame(2, RegistroControlAmbiental::query()->count());
    }

    public function test_estado_distingue_camaras_pendientes_vigentes_y_vencidas(): void
    {
        Carbon::setTestNow('2026-09-07 16:30:00');
        $pendiente = $this->crearCamara('CAM-10');
        $vigente = $this->crearCamara('CAM-11');
        $vencida = $this->crearCamara('CAM-12');
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $token = $this->tokenTablet($camarero);

        $this->registrar($token, $vigente, '2026-09-07T15:50:00Z');
        $this->registrar($token, $vencida, '2026-09-07T14:00:00Z');

        $this->withToken($token)
            ->getJson('/api/control-ambiental/estado')
            ->assertOk()
            ->assertJsonPath('frecuencia_minutos', 60)
            ->assertJsonPath('camaras.0.camara.id', $pendiente->id)
            ->assertJsonPath('camaras.0.estado', 'pendiente')
            ->assertJsonPath('camaras.0.requiere_control', true)
            ->assertJsonPath('camaras.1.camara.id', $vigente->id)
            ->assertJsonPath('camaras.1.estado', 'vigente')
            ->assertJsonPath('camaras.1.requiere_control', false)
            ->assertJsonPath('camaras.2.camara.id', $vencida->id)
            ->assertJsonPath('camaras.2.estado', 'vencido')
            ->assertJsonPath('camaras.2.requiere_control', true)
            ->assertJsonPath('camaras.2.vencido_desde', '2026-09-07T15:00:00+00:00');
    }

    public function test_supervisor_corrige_temperaturas_con_version_e_historial_inmutable(): void
    {
        Carbon::setTestNow('2026-09-07 16:00:00');
        $camara = $this->crearCamara('CAM-20');
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);
        $registroId = $this->registrar(
            $this->tokenTablet($camarero),
            $camara,
            '2026-09-07T15:45:00Z',
        );
        $operacionId = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacionId,
            'version' => 0,
            'temperatura_inicio_c' => '-0.90',
            'temperatura_medio_c' => '-0.60',
            'temperatura_fondo_c' => '-0.70',
            'motivo' => 'La lectura inicial se transcribió incorrectamente.',
        ];
        $tokenSupervisor = $supervisor->createToken('oficina', ['oficina'])->plainTextToken;
        $ruta = "/api/control-ambiental/registros/{$registroId}/corregir";

        $this->withToken($tokenSupervisor)
            ->putJson($ruta, $payload)
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.temperaturas.inicio_c', -0.9)
            ->assertJsonPath('data.correcciones.0.motivo', $payload['motivo'])
            ->assertJsonPath('data.correcciones.0.datos_anteriores.version', 0)
            ->assertJsonPath('data.correcciones.0.datos_nuevos.version', 1);

        $this->withToken($tokenSupervisor)->putJson($ruta, $payload)->assertOk();
        $this->assertSame(1, CorreccionControlAmbiental::query()->count());

        $this->withToken($tokenSupervisor)
            ->putJson($ruta, [
                ...$payload,
                'operacion_id' => (string) Str::uuid(),
            ])
            ->assertConflict();

        $this->withToken($tokenSupervisor)
            ->getJson("/api/control-ambiental/registros?camara_id={$camara->id}")
            ->assertOk()
            ->assertJsonPath('data.0.version', 1)
            ->assertJsonCount(1, 'data.0.correcciones');
    }

    public function test_camarero_no_corrige_y_registro_exige_tablet_activa(): void
    {
        Carbon::setTestNow('2026-09-07 16:00:00');
        $camara = $this->crearCamara('CAM-21');
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $registroId = $this->registrar(
            $this->tokenTablet($camarero),
            $camara,
            '2026-09-07T15:45:00Z',
        );
        $tokenOficina = $camarero->createToken('oficina', ['oficina'])->plainTextToken;

        $this->withToken($tokenOficina)
            ->putJson("/api/control-ambiental/registros/{$registroId}/corregir", [
                'operacion_id' => (string) Str::uuid(),
                'version' => 0,
                'temperatura_inicio_c' => '-0.90',
                'temperatura_medio_c' => '-0.60',
                'temperatura_fondo_c' => '-0.70',
                'motivo' => 'Corrección no autorizada.',
            ])
            ->assertForbidden();

        $this->withToken($tokenOficina)
            ->postJson(
                "/api/control-ambiental/camaras/{$camara->id}/registros",
                $this->payloadRegistro('2026-09-07T16:00:00Z', (string) Str::uuid()),
            )
            ->assertUnauthorized();
    }

    public function test_restringe_el_control_a_producto_terminado_y_al_modulo_frio(): void
    {
        Carbon::setTestNow('2026-09-07 16:00:00');
        $camaraMateriales = $this->crearCamara('CAM-MAT', ContenidoCamara::Materiales);
        $camareroFrio = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $camareroMateriales = User::factory()->create(['rol' => RolUsuario::CamareroMateriales]);
        $payload = $this->payloadRegistro('2026-09-07T15:45:00Z');

        $this->withToken($this->tokenTablet($camareroFrio))
            ->postJson(
                "/api/control-ambiental/camaras/{$camaraMateriales->id}/registros",
                $payload,
            )
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');

        $camaraFrio = $this->crearCamara('CAM-FRIO');
        $this->withToken($this->tokenTablet($camareroMateriales))
            ->postJson(
                "/api/control-ambiental/camaras/{$camaraFrio->id}/registros",
                [
                    ...$payload,
                    'operacion_id' => (string) Str::uuid(),
                ],
            )
            ->assertForbidden();
    }

    private function crearCamara(
        string $codigo,
        ContenidoCamara $contenido = ContenidoCamara::Productos,
    ): Camara {
        return Camara::create([
            'codigo' => $codigo,
            'nombre' => "Cámara {$codigo}",
            'contenido' => $contenido,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);
    }

    private function tokenTablet(User $usuario): string
    {
        $dispositivo = Dispositivo::create([
            'codigo' => 'TAB-'.Str::upper(Str::random(10)),
            'nombre' => 'Tablet control ambiental',
        ]);

        return $usuario->crearTokenParaDispositivo(
            $dispositivo,
            'control-ambiental',
            [CatalogoModulosAcceso::habilidadTablet(
                CatalogoModulosAcceso::TABLET_OPERACION_FRIGORIFICO,
            )],
        )->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    private function payloadRegistro(string $capturadoAt, ?string $operacionId = null): array
    {
        return [
            'operacion_id' => $operacionId ?? (string) Str::uuid(),
            'temperatura_inicio_c' => '-0.80',
            'temperatura_medio_c' => '-0.60',
            'temperatura_fondo_c' => '-0.70',
            'capturado_at' => $capturadoAt,
        ];
    }

    private function registrar(
        string $token,
        Camara $camara,
        string $capturadoAt,
    ): string {
        return (string) $this->withToken($token)
            ->postJson(
                "/api/control-ambiental/camaras/{$camara->id}/registros",
                $this->payloadRegistro($capturadoAt),
            )
            ->assertCreated()
            ->json('data.id');
    }
}
