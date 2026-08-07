<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\RolUsuario;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnulacionValidacionPalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_anula_pallet_pendiente_prefrio_y_conserva_auditoria(): void
    {
        [$catalogo, $tokenValidador] = $this->contexto(RolUsuario::Validador, 'VAL-ANU-01');
        [, $tokenSupervisor] = $this->acceso(RolUsuario::SupervisorFrio, 'SUP-ANU-01');

        $validacionId = $this->conToken($tokenValidador)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-ANU-0001'))
            ->assertCreated()
            ->json('data.id');

        $operacionId = (string) Str::uuid();
        $respuesta = $this->conToken($tokenSupervisor)
            ->postJson("/api/validacion/pallets/{$validacionId}/anular", [
                'operacion_id' => $operacionId,
                'motivo_categoria' => 'folio_incorrecto',
                'motivo' => 'El número físico del pallet fue digitado incorrectamente.',
            ])
            ->assertOk()
            ->assertJsonPath('data.numero_folio', 'PAL-ANU-0001')
            ->assertJsonPath('data.motivo_categoria', 'folio_incorrecto')
            ->assertJsonPath('data.folio.estado_operacional', 'anulado')
            ->assertJsonPath('data.folio.activo', false)
            ->assertJsonPath('message', 'Pallet anulado. El folio quedó inactivo, bloqueado para toda operación y conservado para auditoría.');

        $anulacionId = $respuesta->json('data.id');

        $this->assertDatabaseHas('validaciones_pallet', [
            'id' => $validacionId,
            'estado' => 'anulada',
        ]);
        $this->assertDatabaseHas('folios', [
            'numero_folio' => 'PAL-ANU-0001',
            'estado_operacional' => 'anulado',
            'activo' => false,
        ]);
        $this->assertDatabaseHas('anulaciones_validacion_pallet', [
            'id' => $anulacionId,
            'operacion_id' => $operacionId,
            'validacion_pallet_id' => $validacionId,
            'numero_folio' => 'PAL-ANU-0001',
            'motivo_categoria' => 'folio_incorrecto',
        ]);

        $folio = Folio::query()->where('numero_folio', 'PAL-ANU-0001')->firstOrFail();
        $this->assertSame($anulacionId, $folio->datos_externos['anulacion_validacion_id']);
    }

    public function test_validador_no_puede_anular_su_propio_pallet(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-ANU-02');

        $validacionId = $this->conToken($token)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-ANU-0002'))
            ->assertCreated()
            ->json('data.id');

        $this->conToken($token)
            ->postJson("/api/validacion/pallets/{$validacionId}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo_categoria' => 'otro',
                'motivo' => 'Intento no autorizado del propio validador.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('anulaciones_validacion_pallet', [
            'validacion_pallet_id' => $validacionId,
        ]);
        $this->assertDatabaseHas('folios', [
            'numero_folio' => 'PAL-ANU-0002',
            'estado_operacional' => 'pendiente_prefrio',
            'activo' => true,
        ]);
    }

    public function test_anulacion_es_idempotente_y_no_duplica_registro(): void
    {
        [$catalogo, $tokenValidador] = $this->contexto(RolUsuario::Validador, 'VAL-ANU-03');
        [, $tokenSupervisor] = $this->acceso(RolUsuario::SupervisorFrio, 'SUP-ANU-03');

        $validacionId = $this->conToken($tokenValidador)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-ANU-0003'))
            ->assertCreated()
            ->json('data.id');
        $payload = [
            'operacion_id' => (string) Str::uuid(),
            'motivo_categoria' => 'error_etiqueta',
            'motivo' => 'La etiqueta física corresponde a otro pallet.',
        ];

        $primera = $this->conToken($tokenSupervisor)
            ->postJson("/api/validacion/pallets/{$validacionId}/anular", $payload)
            ->assertOk()
            ->json('data.id');

        $this->conToken($tokenSupervisor)
            ->postJson("/api/validacion/pallets/{$validacionId}/anular", $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $primera);

        $this->assertDatabaseCount('anulaciones_validacion_pallet', 1);
    }

    public function test_no_permite_anular_si_el_pallet_ya_avanzó_desde_pendiente_prefrio(): void
    {
        [$catalogo, $tokenValidador] = $this->contexto(RolUsuario::Validador, 'VAL-ANU-04');
        [, $tokenSupervisor] = $this->acceso(RolUsuario::SupervisorFrio, 'SUP-ANU-04');

        $validacionId = $this->conToken($tokenValidador)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-ANU-0004'))
            ->assertCreated()
            ->json('data.id');

        Folio::query()->where('numero_folio', 'PAL-ANU-0004')->update([
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
        ]);

        $this->conToken($tokenSupervisor)
            ->postJson("/api/validacion/pallets/{$validacionId}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo_categoria' => 'otro',
                'motivo' => 'Se intentó anular después de que el pallet avanzó.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->assertDatabaseMissing('anulaciones_validacion_pallet', [
            'validacion_pallet_id' => $validacionId,
        ]);
    }

    public function test_folio_anulado_es_inmutable_y_no_puede_reactivarse(): void
    {
        [$catalogo, $tokenValidador] = $this->contexto(RolUsuario::Validador, 'VAL-ANU-05');
        [, $tokenSupervisor] = $this->acceso(RolUsuario::SupervisorFrio, 'SUP-ANU-05');

        $validacionId = $this->conToken($tokenValidador)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-ANU-0005'))
            ->assertCreated()
            ->json('data.id');

        $this->conToken($tokenSupervisor)
            ->postJson("/api/validacion/pallets/{$validacionId}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo_categoria' => 'pallet_duplicado',
                'motivo' => 'La etiqueta fue duplicada físicamente por error.',
            ])
            ->assertOk();

        $folio = Folio::query()->where('numero_folio', 'PAL-ANU-0005')->firstOrFail();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('fue anulado en Validación y es inmutable');
        $folio->update(['activo' => true]);
    }

    public function test_oficina_lista_candidatos_y_auditoria_de_anulaciones(): void
    {
        [$catalogo, $tokenValidador] = $this->contexto(RolUsuario::Validador, 'VAL-ANU-06');
        [, $tokenSupervisor] = $this->acceso(RolUsuario::SupervisorFrio, 'SUP-ANU-06');

        $validacionA = $this->conToken($tokenValidador)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-ANU-0006'))
            ->assertCreated()
            ->json('data.id');
        $payloadB = $this->payload($catalogo, 'PAL-ANU-0007');
        $payloadB['operacion_id'] = (string) Str::uuid();
        $this->conToken($tokenValidador)
            ->postJson('/api/validacion/pallets', $payloadB)
            ->assertCreated();

        $this->conToken($tokenSupervisor)
            ->postJson("/api/validacion/pallets/{$validacionA}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo_categoria' => 'cantidad_cajas_incorrecta',
                'motivo' => 'La cantidad física de cajas no correspondía al registro.',
            ])
            ->assertOk();

        $this->conToken($tokenSupervisor)
            ->getJson('/api/validacion/anulaciones')
            ->assertOk()
            ->assertJsonPath('resumen.total', 1)
            ->assertJsonPath('resumen.hoy', 1)
            ->assertJsonPath('resumen.por_categoria.cantidad_cajas_incorrecta', 1)
            ->assertJsonCount(1, 'candidatas')
            ->assertJsonPath('candidatas.0.numero_folio', 'PAL-ANU-0007')
            ->assertJsonCount(1, 'anulaciones')
            ->assertJsonPath('anulaciones.0.numero_folio', 'PAL-ANU-0006')
            ->assertJsonPath('anulaciones.0.folio.estado_operacional', 'anulado');
    }

    /** @return array{array<string, string|int>, string} */
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

    /** @return array{User, string} */
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
     * @param array<string, string|int> $catalogo
     * @return array<string, mixed>
     */
    private function payload(array $catalogo, string $folio): array
    {
        return [
            'operacion_id' => (string) Str::uuid(),
            'numero_folio' => $folio,
            'tipo_bulto' => 'pallet',
            'cantidad_cajas' => 120,
            'linea_proceso' => 1,
            'turno' => 'A',
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
