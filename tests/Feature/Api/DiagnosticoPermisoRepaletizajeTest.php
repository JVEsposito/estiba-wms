<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Dispositivo;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiagnosticoPermisoRepaletizajeTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_frio_con_token_puede_pasar_el_gate_de_anulacion(): void
    {
        $usuario = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'SUP-DIAG-REPA',
            'nombre' => 'Supervisor diagnóstico repa',
            'activo' => true,
        ]);
        $token = $usuario->crearTokenParaDispositivo(
            $dispositivo,
            'diag-repa',
        )->plainTextToken;

        $this->assertTrue(
            app(AlcanceOperacionalUsuario::class)->puedeRechazarPallets($usuario),
        );
        $this->assertTrue(Gate::forUser($usuario)->allows('rechazar-pallets'));

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('rol', RolUsuario::SupervisorFrio->value);

        $this->withToken($token)
            ->postJson('/api/validacion/repaletizajes/'.Str::uuid().'/anular', [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'Diagnóstico de autorización.',
            ])
            ->assertNotFound();
    }
}
