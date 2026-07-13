<?php

namespace Tests\Feature\Api;

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
        $token = $user->createToken('tablet-prueba')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }
}
