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

        $validacion = $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertCreated()
            ->assertJsonPath('data.numero_folio', 'PAL-0001')
            ->assertJsonPath('data.resultado', 'aprobado')
            ->assertJsonPath('data.estado', 'aceptada')
            ->assertJsonPath('data.catalogo.categoria.nombre', 'Exportación')
            ->assertJsonPath('data.folio.estado_operacional', 'pendiente_prefrio')
            ->json('data.id');

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $validacion);

        $this->assertSame(1, Folio::query()->where('numero_folio', 'PAL-0001')->count());
        $this->assertDatabaseHas('folios', [
            'numero_folio' => 'PAL-0001',
            'temporada_id' => $catalogo['temporada_id'],
        ]);
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

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $observado)
            ->assertCreated()
            ->assertJsonPath('data.numero_intento', 1)
            ->assertJsonPath('data.folio', null);

        $this->assertDatabaseMissing('folios', ['numero_folio' => 'PAL-0002']);

        $aprobado = $this->payload($catalogo, 'PAL-0002');
        $aprobado['operacion_id'] = (string) Str::uuid();

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $aprobado)
            ->assertCreated()
            ->assertJsonPath('data.numero_intento', 2)
            ->assertJsonPath('data.folio.numero_folio', 'PAL-0002');
    }

    public function test_aprobacion_es_terminal_y_un_intento_posterior_queda_en_conflicto(): void
    {
        [$catalogo, $tokenA] = $this->contexto(RolUsuario::Validador, 'VAL-03');
        [, $tokenB] = $this->acceso(RolUsuario::Validador, 'VAL-04');

        $primeraId = $this->conToken($tokenA)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-0003'))
            ->assertCreated()
            ->json('data.id');

        $segundo = [
            ...$this->payload($catalogo, 'PAL-0003'),
            'resultado' => 'observado',
            'motivo' => 'etiqueta_no_coincide',
            'observacion' => 'Segundo dispositivo informa otra etiqueta.',
        ];

        $this->conToken($tokenB)
            ->postJson('/api/validacion/pallets', $segundo)
            ->assertStatus(409)
            ->assertJsonPath('data.estado', 'conflicto')
            ->assertJsonPath('data.numero_intento', 2)
            ->assertJsonPath('data.conflicto_con.id', $primeraId);

        $this->assertSame(1, Folio::query()->where('numero_folio', 'PAL-0003')->count());
        $this->assertDatabaseCount('validaciones_pallet', 2);
    }

    public function test_supervisor_puede_rechazar_y_el_rechazo_es_terminal(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::SupervisorFrio, 'SUP-01');
        $rechazo = [
            ...$this->payload($catalogo, 'PAL-0004'),
            'resultado' => 'rechazado',
            'motivo' => 'condicion_fruta',
            'observacion' => 'Condición no aceptable.',
        ];

        $rechazoId = $this->conToken($token)
            ->postJson('/api/validacion/pallets', $rechazo)
            ->assertCreated()
            ->assertJsonPath('data.estado', 'aceptada')
            ->assertJsonPath('data.folio', null)
            ->json('data.id');

        $aprobacion = $this->payload($catalogo, 'PAL-0004');

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $aprobacion)
            ->assertStatus(409)
            ->assertJsonPath('data.estado', 'conflicto')
            ->assertJsonPath('data.conflicto_con.id', $rechazoId);

        $this->assertDatabaseMissing('folios', ['numero_folio' => 'PAL-0004']);
    }

    public function test_validador_no_puede_confirmar_rechazo_definitivo(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-05');
        $payload = [
            ...$this->payload($catalogo, 'PAL-0005'),
            'resultado' => 'rechazado',
            'motivo' => 'condicion_fruta',
            'observacion' => 'Condición no aceptable.',
        ];

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('validaciones_pallet', ['numero_folio' => 'PAL-0005']);
    }

    public function test_reutilizar_uuid_con_payload_distinto_genera_conflicto(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-06');
        $payload = [
            ...$this->payload($catalogo, 'PAL-0006'),
            'resultado' => 'observado',
            'motivo' => 'cantidad_cajas_incorrecta',
            'observacion' => 'Cantidad pendiente de confirmación.',
        ];

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertCreated();

        $payload['cantidad_cajas'] = 121;

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->assertDatabaseCount('validaciones_pallet', 1);
    }

    public function test_acepta_catalogo_desactualizado_y_lo_informa_en_la_respuesta(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-07');
        $payload = $this->payload($catalogo, 'PAL-0007');
        $payload['catalogo_version'] = 2;

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertCreated()
            ->assertJsonPath('catalogo_desactualizado', true)
            ->assertJsonPath('data.catalogo.desactualizado', true)
            ->assertJsonPath('data.catalogo.version_dispositivo', 2)
            ->assertJsonPath('data.catalogo.version_servidor', 1);
    }

    public function test_rechaza_una_temporada_inactiva(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-08');
        DB::table('temporadas')
            ->where('id', $catalogo['temporada_id'])
            ->update(['activa' => false]);

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-0008'))
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');

        $this->assertDatabaseMissing('folios', ['numero_folio' => 'PAL-0008']);
    }

    public function test_indice_valida_paginacion_filtra_y_no_expone_el_hash_interno(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-09');

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-0009'))
            ->assertCreated();

        $this->conToken($token)
            ->getJson('/api/validacion/pallets?per_page=1000')
            ->assertUnprocessable();

        $this->conToken($token)
            ->getJson('/api/validacion/pallets?folio=pal-0009&resultado=aprobado&estado=aceptada&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('data.0.numero_folio', 'PAL-0009')
            ->assertJsonMissingPath('data.0.payload_hash');
    }

    public function test_validador_no_puede_consultar_cargas_materiales_ni_camaras(): void
    {
        [, $token] = $this->acceso(RolUsuario::Validador, 'VAL-10');

        $this->conToken($token)
            ->getJson('/api/camaras')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->conToken($token)
            ->getJson('/api/cargas')
            ->assertForbidden();

        $this->conToken($token)
            ->getJson('/api/materiales/inventario')
            ->assertForbidden();
    }

    /**
     * @return array{array<string, string|int>, string}
     */
    private function contexto(RolUsuario $rol, string $codigo): array
    {
        $temporada = (string) Str::uuid();
        $articulo = (string) Str::uuid();
        $origen = (string) Str::uuid();
        $categoria = (string) Str::uuid();

        DB::table('temporadas')->insert([
            'id' => $temporada,
            'codigo' => '2026-2027',
            'nombre' => 'Temporada 2026-2027',
            'activa' => true,
            'version_catalogo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('articulos_validacion')->insert([
            'id' => $articulo,
            'temporada_id' => $temporada,
            'especie' => 'Cereza',
            'variedad' => 'Santina',
            'calibre' => '2J',
            'envase' => '5 kg',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('categorias_validacion')->insert([
            'id' => $categoria,
            'temporada_id' => $temporada,
            'nombre' => 'Exportación',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('origenes_validacion')->insert([
            'id' => $origen,
            'temporada_id' => $temporada,
            'cliente' => 'DIS',
            'marca' => 'ATLAS',
            'csg' => '105410',
            'predio' => 'OLM',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('combinaciones_validacion')->insert([
            'id' => (string) Str::uuid(),
            'temporada_id' => $temporada,
            'articulo_validacion_id' => $articulo,
            'origen_validacion_id' => $origen,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        [, $token] = $this->acceso($rol, $codigo);

        return [[
            'temporada_id' => $temporada,
            'articulo_validacion_id' => $articulo,
            'origen_validacion_id' => $origen,
            'categoria_validacion_id' => $categoria,
            'catalogo_version' => 1,
        ], $token];
    }

    /**
     * @return array{User, string}
     */
    private function acceso(RolUsuario $rol, string $codigo): array
    {
        $usuario = User::factory()->create(['rol' => $rol]);
        $dispositivo = Dispositivo::create([
            'codigo' => $codigo,
            'nombre' => "PDA {$codigo}",
            'plataforma' => 'android',
            'activo' => true,
        ]);
        $token = $usuario->crearTokenParaDispositivo($dispositivo, "test-{$codigo}")->plainTextToken;

        return [$usuario, $token];
    }

    /**
     * @param  array<string, string|int>  $catalogo
     * @return array<string, mixed>
     */
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

    private function conToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
