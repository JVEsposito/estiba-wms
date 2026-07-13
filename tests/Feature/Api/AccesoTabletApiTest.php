<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\CondicionSag;
use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccesoTabletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_operador_accede_con_credenciales_y_tablet_autorizada(): void
    {
        [$usuario, $dispositivo] = $this->crearIdentidad();

        $token = $this->postJson('/api/acceso-tablet', [
            'email' => 'operador@example.com',
            'password' => 'clave-segura',
            'codigo_dispositivo' => 'TABLET-01',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('usuario.id', $usuario->id)
            ->assertJsonPath('usuario.rol', 'operador')
            ->assertJsonPath('dispositivo.id', $dispositivo->id)
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $usuario->id);
        $this->assertNotNull($dispositivo->refresh()->ultimo_acceso_at);
    }

    public function test_credenciales_invalidas_no_generan_un_token(): void
    {
        [$usuario] = $this->crearIdentidad();

        $this->postJson('/api/acceso-tablet', [
            'email' => $usuario->email,
            'password' => 'incorrecta',
            'codigo_dispositivo' => 'TABLET-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_una_tablet_inactiva_no_puede_iniciar_turno(): void
    {
        [$usuario, $dispositivo] = $this->crearIdentidad();
        $dispositivo->update(['activo' => false]);

        $this->postJson('/api/acceso-tablet', [
            'email' => $usuario->email,
            'password' => 'clave-segura',
            'codigo_dispositivo' => $dispositivo->codigo,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo_dispositivo');
    }

    public function test_un_nuevo_acceso_en_la_misma_tablet_revoca_el_token_anterior(): void
    {
        [$usuario] = $this->crearIdentidad();
        $payload = [
            'email' => $usuario->email,
            'password' => 'clave-segura',
            'codigo_dispositivo' => 'TABLET-01',
        ];
        $primerToken = $this->postJson('/api/acceso-tablet', $payload)->json('token');
        $segundoToken = $this->postJson('/api/acceso-tablet', $payload)->json('token');

        auth()->forgetGuards();
        $this->withToken($primerToken)->getJson('/api/user')->assertUnauthorized();
        auth()->forgetGuards();
        $this->withToken($segundoToken)->getJson('/api/user')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_cerrar_turno_revoca_el_token_actual(): void
    {
        [$usuario] = $this->crearIdentidad();
        $token = $this->postJson('/api/acceso-tablet', [
            'email' => $usuario->email,
            'password' => 'clave-segura',
            'codigo_dispositivo' => 'TABLET-01',
        ])->json('token');

        $this->withToken($token)
            ->deleteJson('/api/acceso-tablet')
            ->assertNoContent();

        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_el_catalogo_sag_solo_incluye_condiciones_activas(): void
    {
        [$usuario, $dispositivo] = $this->crearIdentidad();
        $activa = CondicionSag::create([
            'codigo' => 'APTA',
            'nombre' => 'Apta para exportación',
        ]);
        CondicionSag::create([
            'codigo' => 'INACTIVA',
            'nombre' => 'Condición inactiva',
            'activo' => false,
        ]);
        $token = $usuario
            ->crearTokenParaDispositivo($dispositivo, 'tablet-prueba')
            ->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/condiciones-sag')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activa->id)
            ->assertJsonPath('data.0.codigo', 'APTA');
    }

    /**
     * @return array{User, Dispositivo}
     */
    private function crearIdentidad(): array
    {
        $usuario = User::factory()->create([
            'email' => 'operador@example.com',
            'password' => Hash::make('clave-segura'),
            'rol' => RolUsuario::Operador,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet de prueba',
        ]);

        return [$usuario, $dispositivo];
    }
}
