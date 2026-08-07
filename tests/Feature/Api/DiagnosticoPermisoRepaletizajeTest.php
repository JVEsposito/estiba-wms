<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoIntegracionFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Repaletizaje;
use App\Models\Temporada;
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
        $temporada = Temporada::create([
            'codigo' => '2026-DIAG',
            'nombre' => 'Temporada diagnóstico',
            'activa' => true,
            'version_catalogo' => 1,
        ]);
        $folio = Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => 'F-DIAG-001',
            'tipo_bulto' => TipoBulto::Saldo,
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
            'fecha_ingreso' => now(),
            'activo' => true,
            'origen_sistema' => 'diagnostico',
            'identificador_externo' => (string) Str::uuid(),
            'estado_integracion' => EstadoIntegracionFolio::NoVinculado,
            'datos_externos' => ['cantidad_cajas' => 10],
        ]);
        $operador = User::factory()->create(['rol' => RolUsuario::Validador]);
        $repa = Repaletizaje::create([
            'operacion_id' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', 'diag-repa'),
            'codigo' => 'REPA-DIAG-001',
            'tipo_resultado' => 'saldo',
            'estrategia_folio' => 'conservar',
            'folio_resultante_id' => $folio->id,
            'folio_conservado_id' => $folio->id,
            'cantidad_resultante' => 10,
            'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado->value,
            'campos_mix' => [],
            'snapshot' => [],
            'estado' => 'confirmado',
            'user_id' => $operador->id,
            'confirmado_at' => now(),
        ]);

        $validadorToken = $this->token(RolUsuario::Validador, 'VAL-DIAG-REPA');
        $this->withToken($validadorToken)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('rol', RolUsuario::Validador->value);

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
            ->postJson("/api/validacion/repaletizajes/{$repa->id}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'Diagnóstico de autorización.',
            ])
            ->assertOk();
    }

    private function token(RolUsuario $rol, string $codigo): string
    {
        $usuario = User::factory()->create(['rol' => $rol]);
        $dispositivo = Dispositivo::create([
            'codigo' => $codigo,
            'nombre' => $codigo,
            'activo' => true,
        ]);

        return $usuario->crearTokenParaDispositivo(
            $dispositivo,
            'test-'.$codigo,
        )->plainTextToken;
    }
}
