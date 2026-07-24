<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreacionUsuarioEstadoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creacion_respeta_estado_inactivo_solicitado(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/usuarios', [
                'nombre' => 'Usuario pendiente de habilitación',
                'email' => 'pendiente@empresa.cl',
                'rol' => RolUsuario::Consulta->value,
                'activo' => false,
                'password' => 'ClavePendiente2026',
                'password_confirmation' => 'ClavePendiente2026',
            ])
            ->assertCreated()
            ->assertJsonPath('usuario.activo', false);

        $this->assertDatabaseHas('users', [
            'email' => 'pendiente@empresa.cl',
            'activo' => false,
        ]);
    }
}
