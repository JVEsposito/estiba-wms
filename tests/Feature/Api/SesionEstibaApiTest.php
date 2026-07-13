<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\SesionEstiba;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SesionEstibaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_abre_y_cierra_una_sesion_con_la_identidad_del_token(): void
    {
        [$usuario, $dispositivo, $token] = $this->crearIdentidad('TABLET-01');
        $camara = $this->crearCamara();

        $sesionId = $this->withToken($token)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertCreated()
            ->assertJsonPath('data.camara_id', $camara->id)
            ->assertJsonPath('data.estado', 'abierta')
            ->assertJsonPath('data.usuario.id', $usuario->id)
            ->assertJsonPath('data.dispositivo.id', $dispositivo->id)
            ->json('data.id');

        $this->assertDatabaseHas('bloqueos_camara', [
            'camara_id' => $camara->id,
            'sesion_estiba_id' => $sesionId,
        ]);

        $this->withToken($token)
            ->postJson("/api/sesiones/{$sesionId}/cerrar", [
                'motivo' => 'Fin del turno',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cerrada')
            ->assertJsonPath('data.motivo_cierre', 'Fin del turno');

        $this->assertDatabaseMissing('bloqueos_camara', ['camara_id' => $camara->id]);
        $this->assertSame('cerrada', SesionEstiba::query()->findOrFail($sesionId)->estado->value);
    }

    public function test_un_segundo_operador_recibe_conflicto_si_la_camara_esta_en_uso(): void
    {
        [, , $primerToken] = $this->crearIdentidad('TABLET-01');
        [, , $segundoToken] = $this->crearIdentidad('TABLET-02');
        $camara = $this->crearCamara();

        $this->withToken($primerToken)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertCreated();

        $this->withToken($segundoToken)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');
    }

    public function test_un_usuario_de_consulta_no_puede_abrir_una_sesion(): void
    {
        [, , $token] = $this->crearIdentidad('TABLET-01', RolUsuario::Consulta);
        $camara = $this->crearCamara();

        $this->withToken($token)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertForbidden()
            ->assertJsonPath('codigo', 'operacion_no_autorizada');
    }

    /**
     * @return array{User, Dispositivo, string}
     */
    private function crearIdentidad(
        string $codigo,
        RolUsuario $rol = RolUsuario::Operador,
    ): array {
        $usuario = User::factory()->create(['rol' => $rol]);
        $dispositivo = Dispositivo::create([
            'codigo' => $codigo,
            'nombre' => "Tablet {$codigo}",
        ]);
        $token = $usuario
            ->crearTokenParaDispositivo($dispositivo, strtolower($codigo))
            ->plainTextToken;

        return [$usuario, $dispositivo, $token];
    }

    private function crearCamara(): Camara
    {
        return Camara::create([
            'codigo' => 'CAM-01',
            'nombre' => 'Cámara 01',
        ]);
    }
}
