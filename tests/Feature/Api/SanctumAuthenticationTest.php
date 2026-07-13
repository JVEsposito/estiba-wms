<?php

namespace Tests\Feature\Api;

use App\Models\Dispositivo;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_api_rechaza_solicitudes_sin_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_un_token_sanctum_autentica_a_un_usuario(): void
    {
        $user = User::factory()->create();
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet de prueba',
        ]);
        $token = $user
            ->crearTokenParaDispositivo($dispositivo, 'tablet-prueba')
            ->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_el_token_queda_asociado_al_dispositivo_autorizado(): void
    {
        $user = User::factory()->create();
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet de prueba',
        ]);

        $nuevoToken = $user->crearTokenParaDispositivo($dispositivo, 'tablet-prueba');

        $this->assertInstanceOf(PersonalAccessToken::class, $nuevoToken->accessToken);
        $this->assertSame($dispositivo->id, $nuevoToken->accessToken->dispositivo_id);
        $this->assertTrue($nuevoToken->accessToken->dispositivo->is($dispositivo));
    }

    public function test_un_token_sin_dispositivo_es_rechazado(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('token-sin-tablet')->plainTextToken;

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_un_usuario_inactivo_no_puede_usar_un_token_existente(): void
    {
        [$user, $token] = $this->crearIdentidadOperacional();

        $user->update(['activo' => false]);

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_un_dispositivo_inactivo_no_puede_usar_un_token_existente(): void
    {
        [$user, $token, $dispositivo] = $this->crearIdentidadOperacional();

        $dispositivo->update(['activo' => false]);

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    private function crearIdentidadOperacional(): array
    {
        $user = User::factory()->create();
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet de prueba',
        ]);
        $token = $user
            ->crearTokenParaDispositivo($dispositivo, 'tablet-prueba')
            ->plainTextToken;

        return [$user, $token, $dispositivo];
    }
}
