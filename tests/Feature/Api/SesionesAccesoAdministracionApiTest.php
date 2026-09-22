<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\User;
use App\Services\Estiba\ServicioSesionEstiba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SesionesAccesoAdministracionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ve_accesos_vigentes_de_oficina_y_tablet_sin_exponer_tokens(): void
    {
        $admin = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $adminToken = $admin->createToken('oficina-admin', ['oficina'])->plainTextToken;
        $operador = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $dispositivo = Dispositivo::create(['codigo' => 'TABLET-01', 'nombre' => 'Tablet norte']);
        $tokenTablet = $operador->crearTokenParaDispositivo($dispositivo, 'tablet-norte')->plainTextToken;
        $operador->createToken('oficina-expirada', ['oficina'], now()->subMinute());
        $inactivo = User::factory()->create(['activo' => false]);
        $inactivo->createToken('oficina-inactiva', ['oficina']);

        $respuesta = $this->withToken($adminToken)
            ->getJson('/api/administracion/sesiones-acceso')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'data');

        $sesiones = collect($respuesta->json('data'));
        $this->assertTrue($sesiones->contains(fn (array $sesion) => $sesion['es_actual']
            && $sesion['tipo'] === 'oficina' && $sesion['usuario']['id'] === $admin->id));
        $this->assertTrue($sesiones->contains(fn (array $sesion) => $sesion['tipo'] === 'tablet'
            && $sesion['dispositivo']['nombre'] === 'Tablet norte'));
        $this->assertStringNotContainsString($tokenTablet, $respuesta->getContent());
    }

    public function test_revocar_tablet_cierra_sesion_de_camara_libera_bloqueo_y_audita(): void
    {
        $admin = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $adminToken = $admin->createToken('oficina-admin', ['oficina'])->plainTextToken;
        $operador = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $dispositivo = Dispositivo::create(['codigo' => 'TABLET-01', 'nombre' => 'Tablet norte']);
        $acceso = $operador->crearTokenParaDispositivo($dispositivo, 'tablet-norte');
        $camara = Camara::create(['codigo' => 'CAM-01', 'nombre' => 'Cámara norte']);
        $sesion = app(ServicioSesionEstiba::class)->abrir($camara, $operador, $dispositivo);

        $this->withToken($adminToken)
            ->deleteJson("/api/administracion/sesiones-acceso/{$acceso->accessToken->id}")
            ->assertOk()
            ->assertJsonPath('sesion_actual_cerrada', false)
            ->assertJsonPath('sesiones_camara_cerradas', 1);

        $this->assertSame('cierre_forzado', $sesion->refresh()->estado->value);
        $this->assertSame($admin->id, $sesion->cierre_forzado_por_user_id);
        $this->assertDatabaseMissing('bloqueos_camara', ['camara_id' => $camara->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $acceso->accessToken->id]);
        $this->assertDatabaseHas('auditoria_cierres_acceso', [
            'administrador_user_id' => $admin->id,
            'usuario_user_id' => $operador->id,
            'token_id' => $acceso->accessToken->id,
            'dispositivo_codigo' => $dispositivo->codigo,
            'sesiones_camara_cerradas' => 1,
        ]);

        auth()->forgetGuards();
        $this->withToken($acceso->plainTextToken)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_cerrar_propia_sesion_de_oficina_revoca_solo_el_acceso_elegido(): void
    {
        $admin = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $acceso = $admin->createToken('oficina-actual', ['oficina']);
        $otroAcceso = $admin->createToken('oficina-otra', ['oficina']);

        $this->withToken($acceso->plainTextToken)
            ->deleteJson("/api/administracion/sesiones-acceso/{$acceso->accessToken->id}")
            ->assertOk()->assertJsonPath('sesion_actual_cerrada', true);

        auth()->forgetGuards();
        $this->withToken($acceso->plainTextToken)->getJson('/api/user')->assertUnauthorized();
        auth()->forgetGuards();
        $this->withToken($otroAcceso->plainTextToken)->getJson('/api/user')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_usuario_sin_permiso_de_administracion_no_ve_ni_revoca_sesiones(): void
    {
        $operador = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);
        $token = $operador->createToken('oficina-supervisor', ['oficina'])->plainTextToken;
        $victima = User::factory()->create();
        $acceso = $victima->createToken('oficina-victima', ['oficina']);

        $this->withToken($token)->getJson('/api/administracion/sesiones-acceso')->assertForbidden();
        $this->withToken($token)
            ->deleteJson("/api/administracion/sesiones-acceso/{$acceso->accessToken->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $acceso->accessToken->id]);
        $this->assertDatabaseCount('auditoria_cierres_acceso', 0);
    }
}
