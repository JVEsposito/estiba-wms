<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ValidacionPalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_aprueba_y_crea_folio_pendiente_de_prefrio_de_forma_idempotente(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-01');
        $payload = $this->payload($catalogo, 'PAL-0001');

        $validacion = $this->withToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertCreated()
            ->assertJsonPath('data.numero_folio', 'PAL-0001')
            ->assertJsonPath('data.resultado', 'aprobado')
            ->assertJsonPath('data.estado', 'aceptada')
            ->assertJsonPath('data.folio.estado_operacional', 'pendiente_prefrio')
            ->json('data.id');

        $this->withToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $validacion);

        $this->assertSame(1, Folio::query()->where('numero_folio', 'PAL-0001')->count());
        $this->assertDatabaseCount('validaciones_pallet', 1);
    }

    public function test_observa_sin_crear_folio_y_la_aprobacion_posterior_es_otro_intento(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-02');
        $observado = [
            ...$this->payload($catalogo, 'PAL-0002'),
            'resultado' => 'observado',
            'motivo' => 'csg_no_coincide',
            'observacion' => 'La etiqueta física informa otro CSG.',
        ];

        $this->withToken($token)
            ->postJson('/api/validacion/pallets', $observado)
            ->assertCreated()
            ->assertJsonPath('data.numero_intento', 1)
            ->assertJsonPath('data.folio', null);

        $this->assertDatabaseMissing('folios', ['numero_folio' => 'PAL-0002']);

        $aprobado = $this->payload($catalogo, 'PAL-0002');
        $aprobado['operacion_id'] = (string) Str::uuid();
        $this->withToken($token)
            ->postJson('/api/validacion/pallets', $aprobado)
            ->assertCreated()
            ->assertJsonPath('data.numero_intento', 2)
            ->assertJsonPath('data.folio.numero_folio', 'PAL-0002');
    }

    public function test_segunda_aprobacion_del_mismo_folio_queda_como_conflicto(): void
    {
        [$catalogo, $tokenA] = $this->contexto(RolUsuario::Validador, 'VAL-03');
        [, $tokenB] = $this->acceso(RolUsuario::Validador, 'VAL-04');

        $this->withToken($tokenA)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-0003'))
            ->assertCreated();

        $segundo = $this->payload($catalogo, 'PAL-0003');
        $segundo['operacion_id'] = (string) Str::uuid();
        $this->withToken($tokenB)
            ->postJson('/api/validacion/pallets', $segundo)
            ->assertStatus(409)
            ->assertJsonPath('data.estado', 'conflicto')
            ->assertJsonPath('data.numero_intento', 2);

        $this->assertSame(1, Folio::query()->where('numero_folio', 'PAL-0003')->count());
        $this->assertDatabaseCount('validaciones_pallet', 2);
    }

    public function test_validador_no_puede_confirmar_rechazo_definitivo(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-05');
        $payload = [
            ...$this->payload($catalogo, 'PAL-0004'),
            'resultado' => 'rechazado',
            'motivo' => 'condicion_fruta',
            'observacion' => 'Condición no aceptable.',
        ];

        $this->withToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');
    }

    /** @return array{array<string, string|int>, string} */
    private function contexto(RolUsuario $rol, string $codigo): array
    {
        $temporada = (string) Str::uuid();
        $articulo = (string) Str::uuid();
        $origen = (string) Str::uuid();
        DB::table('temporadas')->insert([
            'id' => $temporada, 'codigo' => '2026-2027', 'nombre' => 'Temporada 2026-2027',
            'activa' => true, 'version_catalogo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('articulos_validacion')->insert([
            'id' => $articulo, 'temporada_id' => $temporada, 'especie' => 'Cereza',
            'variedad' => 'Santina', 'calibre' => '2J', 'envase' => '5 kg',
            'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('origenes_validacion')->insert([
            'id' => $origen, 'temporada_id' => $temporada, 'cliente' => 'DIS',
            'marca' => 'ATLAS', 'csg' => '105410', 'predio' => 'OLM',
            'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        [, $token] = $this->acceso($rol, $codigo);

        return [[
            'temporada_id' => $temporada,
            'articulo_validacion_id' => $articulo,
            'origen_validacion_id' => $origen,
            'catalogo_version' => 1,
        ], $token];
    }

    /** @return array{User, string} */
    private function acceso(RolUsuario $rol, string $codigo): array
    {
        $usuario = User::factory()->create(['rol' => $rol]);
        $dispositivo = Dispositivo::create([
            'codigo' => $codigo, 'nombre' => "PDA {$codigo}", 'plataforma' => 'android', 'activo' => true,
        ]);
        $token = $usuario->crearTokenParaDispositivo($dispositivo, "test-{$codigo}")->plainTextToken;

        return [$usuario, $token];
    }

    /** @param array<string, string|int> $catalogo @return array<string, mixed> */
    private function payload(array $catalogo, string $folio): array
    {
        return [
            'operacion_id' => (string) Str::uuid(),
            'numero_folio' => $folio,
            'tipo_bulto' => 'pallet',
            'cantidad_cajas' => 120,
            ...$catalogo,
            'resultado' => 'aprobado',
            'motivo' => null,
            'observacion' => null,
            'generado_dispositivo_at' => now()->toAtomString(),
        ];
    }
}
