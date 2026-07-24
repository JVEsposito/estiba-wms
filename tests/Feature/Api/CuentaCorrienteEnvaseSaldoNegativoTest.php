<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Cliente;
use App\Models\MovimientoEnvase;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CuentaCorrienteEnvaseSaldoNegativoTest extends TestCase
{
    use RefreshDatabase;

    public function test_informa_un_saldo_negativo_sin_desbordar_el_entero_sin_signo(): void
    {
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $cliente = Cliente::create([
            'codigo' => 'CLI-SALDO',
            'nombre' => 'Cliente con saldo negativo',
            'activo' => true,
        ]);
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);

        MovimientoEnvase::create([
            'operacion_id' => (string) Str::uuid(),
            'temporada_id' => $temporada->id,
            'cliente_id' => $cliente->id,
            'documento_tipo' => 'prueba',
            'numero_documento' => 'ING-20',
            'tipo_movimiento' => 'recepcion_compra',
            'tipo_envase' => 'bins',
            'cantidad' => 20,
            'signo_cuenta' => 1,
            'signo_existencia' => 1,
            'propiedad' => 'cliente',
            'ocurrido_at' => now()->subMinute(),
            'estado_revision' => 'pendiente',
            'creado_por_user_id' => $administrador->id,
        ]);
        MovimientoEnvase::create([
            'operacion_id' => (string) Str::uuid(),
            'temporada_id' => $temporada->id,
            'cliente_id' => $cliente->id,
            'documento_tipo' => 'prueba',
            'numero_documento' => 'SAL-50',
            'tipo_movimiento' => 'despacho_cliente',
            'tipo_envase' => 'bins',
            'cantidad' => 50,
            'signo_cuenta' => -1,
            'signo_existencia' => -1,
            'propiedad' => 'cliente',
            'ocurrido_at' => now(),
            'estado_revision' => 'pendiente',
            'creado_por_user_id' => $administrador->id,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/envases/cuenta-corriente/movimientos?temporada_id='.$temporada->id.'&cliente_id='.$cliente->id)
            ->assertOk()
            ->assertJsonPath('balances.0.cliente.id', $cliente->id)
            ->assertJsonPath('balances.0.tipo_envase', 'bins')
            ->assertJsonPath('balances.0.saldo', -30);
    }
}
