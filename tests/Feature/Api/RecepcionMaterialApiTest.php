<?php

namespace Tests\Feature\Api;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\ContenidoCamara;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\Cliente;
use App\Models\ClienteMaterial;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\MovimientoInventarioMaterial;
use App\Models\Posicion;
use App\Models\ProveedorMaterial;
use App\Models\RecepcionMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecepcionMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirma_bultos_y_permite_ubicar_pendientes_y_bloqueados(): void
    {
        [$administrador, $tokenOficina, $cliente, $proveedor, $item] = $this->prepararCatalogo();
        [, , $tokenTablet] = $this->crearOperador();
        $payload = $this->payloadRecepcion($cliente, $proveedor, $item, [
            [
                'cantidad' => 6,
                'lote_proveedor' => 'L-REC-01',
            ],
            [
                'cantidad' => 4,
                'lote_proveedor' => 'L-REC-02',
                'bloqueado' => true,
                'motivo_bloqueo' => 'Pendiente de control de calidad.',
            ],
        ]);
        $recepcion = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/recepciones', $payload)
            ->assertCreated()
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonCount(2, 'data.detalles.0.bultos')
            ->assertJsonPath('data.eventos.0.tipo', 'creada')
            ->json('data');

        $this->conToken($tokenOficina)
            ->postJson('/api/materiales/recepciones', $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', $recepcion['id']);
        $this->assertSame(1, RecepcionMaterial::query()
            ->where('operacion_id', $payload['operacion_id'])
            ->count());

        $this->conToken($tokenTablet)
            ->getJson("/api/materiales/recepciones/{$recepcion['id']}")
            ->assertNotFound();

        $operacionConfirmacion = (string) Str::uuid();
        $confirmada = $this->conToken($tokenOficina)
            ->postJson("/api/materiales/recepciones/{$recepcion['id']}/confirmar", [
                'operacion_id' => $operacionConfirmacion,
                'version_conocida' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.detalles.0.bultos.0.folio.numero_folio', 'FGE0000001')
            ->assertJsonPath('data.detalles.0.bultos.0.folio.estado_operacional', 'pendiente_ubicacion')
            ->assertJsonPath('data.detalles.0.bultos.1.folio.numero_folio', 'FGE0000002')
            ->assertJsonPath('data.detalles.0.bultos.1.folio.estado_operacional', 'bloqueado')
            ->assertJsonPath('data.eventos.1.tipo', 'confirmada')
            ->json('data');

        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/recepciones/{$recepcion['id']}/confirmar", [
                'operacion_id' => $operacionConfirmacion,
                'version_conocida' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $recepcion['id']);
        $this->assertSame(2, Folio::query()
            ->whereIn('numero_folio', ['FGE0000001', 'FGE0000002'])
            ->count());

        $this->conToken($tokenTablet)
            ->getJson("/api/materiales/recepciones/{$recepcion['id']}")
            ->assertOk()
            ->assertJsonPath('data.estado', 'confirmada');
        $this->conToken($tokenTablet)
            ->getJson('/api/materiales/recepciones/folios-pendientes')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        foreach (['FGE0000001', 'FGE0000002'] as $numeroFolio) {
            $this->conToken($tokenTablet)
                ->getJson('/api/movimientos/consultar-folio?numero_folio='.$numeroFolio)
                ->assertOk()
                ->assertJsonPath('data.disponible_ubicacion', true);
        }

        [$camara, $posicionUno, $posicionDos] = $this->crearCamaraMateriales();
        $sesion = $this->conToken($tokenTablet)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertCreated()
            ->json('data.id');

        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                'FGE0000001',
                $posicionUno,
                $sesion,
                $item,
                0,
            ))
            ->assertOk();
        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                'FGE0000002',
                $posicionDos,
                $sesion,
                $item,
                1,
            ))
            ->assertOk();

        $this->assertSame(
            EstadoOperacionalFolio::Disponible,
            Folio::query()->where('numero_folio', 'FGE0000001')->firstOrFail()->estado_operacional,
        );
        $this->assertSame(
            EstadoOperacionalFolio::Bloqueado,
            Folio::query()->where('numero_folio', 'FGE0000002')->firstOrFail()->estado_operacional,
        );
        $this->assertDatabaseHas('folios_materiales', [
            'folio_id' => $confirmada['detalles'][0]['bultos'][0]['folio']['id'],
            'cantidad_actual' => 6,
        ]);

        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/recepciones/{$recepcion['id']}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'Intento posterior a la ubicación.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');

        $this->assertSame($administrador->id, RecepcionMaterial::findOrFail($recepcion['id'])->creado_por_user_id);
    }

    public function test_anulacion_intacta_compensa_saldos_y_es_idempotente(): void
    {
        [, $tokenOficina, $cliente, $proveedor, $item] = $this->prepararCatalogo();
        $payload = $this->payloadRecepcion($cliente, $proveedor, $item, [
            ['cantidad' => 5, 'lote_proveedor' => 'L-ANU-01'],
        ]);
        $recepcion = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/recepciones', $payload)
            ->assertCreated()
            ->json('data');
        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/recepciones/{$recepcion['id']}/confirmar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 1,
            ])
            ->assertOk();

        $folio = Folio::query()->where('numero_folio', 'FGE0000001')->firstOrFail();
        $operacionAnulacion = (string) Str::uuid();
        $anulada = [
            'operacion_id' => $operacionAnulacion,
            'motivo' => 'Guía rechazada por el proveedor.',
        ];
        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/recepciones/{$recepcion['id']}/anular", $anulada)
            ->assertOk()
            ->assertJsonPath('data.estado', 'anulada')
            ->assertJsonPath('data.eventos.2.tipo', 'anulada');
        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/recepciones/{$recepcion['id']}/anular", $anulada)
            ->assertOk()
            ->assertJsonPath('data.estado', 'anulada');

        $material = FolioMaterial::query()->findOrFail($folio->id);
        $this->assertSame('0.000', $material->cantidad_actual);
        $this->assertFalse($folio->refresh()->activo);
        $this->assertSame(2, MovimientoInventarioMaterial::query()
            ->where('folio_id', $folio->id)
            ->count());
        $this->assertDatabaseHas('movimientos_inventario_materiales', [
            'folio_id' => $folio->id,
            'tipo' => 'anulacion_recepcion',
            'cantidad' => -5,
            'cantidad_resultante' => 0,
        ]);
        $this->assertDatabaseCount('eventos_recepciones_materiales', 3);

        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/recepciones/{$recepcion['id']}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => $anulada['motivo'],
            ])
            ->assertConflict();
    }

    public function test_proveedor_solo_puede_recibir_items_de_categorias_habilitadas(): void
    {
        [$administrador, $token, $cliente, $proveedor, $item] = $this->prepararCatalogo();
        $noAutorizado = ItemMaterial::create([
  'cliente_material_id' => $item->cliente_material_id,
  'codigo' => 'QUIM-REC',
  'nombre' => 'Químico no autorizado',
  'categoria' => 'Químicos',
  'categoria_operacional' => CategoriaOperacionalMaterial::Insumo,
  'unidad_medida' => 'litros',
  'origen_sistema' => 'manual',
  'activo' => true,
  'creado_por_user_id' => $administrador->id,
  'actualizado_por_user_id' => $administrador->id,
        ]);

        $this->conToken($token)
  ->getJson('/api/materiales/recepciones/catalogos')
  ->assertOk()
  ->assertJsonPath('proveedores.0.categorias.0.categoria', 'Embalaje');

        $this->conToken($token)
  ->postJson('/api/materiales/recepciones', $this->payloadRecepcion(
      $cliente,
      $proveedor,
      $noAutorizado,
      [['cantidad' => 1]],
  ))
  ->assertUnprocessable()
  ->assertJsonPath('codigo', 'regla_de_negocio');
    }

    private function prepararCatalogo(): array
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $token = $administrador->createToken('oficina-test', ['oficina'])->plainTextToken;
        $catalogo = ClienteMaterial::query()
            ->with(['cliente', 'temporada'])
            ->where('codigo', 'GENERAL')
            ->whereHas('temporada', fn ($consulta) => $consulta->where('activa', true))
            ->firstOrFail();
        $cliente = $catalogo->cliente;
        $cliente->update(['codigo_folio_materiales' => 'GE']);
        $item = ItemMaterial::create([
            'cliente_material_id' => $catalogo->id,
            'codigo' => 'FILM-REC',
            'nombre' => 'Film para recepción',
            'categoria' => 'Embalaje',
            'categoria_operacional' => CategoriaOperacionalMaterial::Insumo,
            'unidad_medida' => 'rollos',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        $proveedor = ProveedorMaterial::create([
            'codigo' => 'PROV-REC',
            'nombre' => 'Proveedor recepción',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        DB::table('clientes_proveedores_materiales')->insert([
            'id' => (string) Str::uuid(),
            'cliente_id' => $cliente->id,
            'proveedor_material_id' => $proveedor->id,
            'activo' => true,
            'categorias' => json_encode(['Embalaje'], JSON_UNESCAPED_UNICODE),
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$administrador, $token, $cliente, $proveedor, $item];
    }

    private function crearOperador(): array
    {
        $usuario = User::factory()->create([
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-REC-'.Str::upper(Str::random(6)),
            'nombre' => 'Tablet recepción',
            'activo' => true,
        ]);
        $token = $usuario
            ->crearTokenParaDispositivo($dispositivo, 'tablet-recepcion')
            ->plainTextToken;

        return [$usuario, $dispositivo, $token];
    }

    private function crearCamaraMateriales(): array
    {
        $camara = Camara::create([
            'codigo' => 'MAT-REC-01',
            'nombre' => 'Cámara recepción',
            'contenido' => ContenidoCamara::Materiales,
        ]);
        $posiciones = collect([1, 2])->map(fn (int $numero): Posicion => Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => $numero,
            'nivel' => 1,
            'etiqueta' => sprintf('B01-P%02d-N1', $numero),
        ]));

        return [$camara, ...$posiciones->all()];
    }

    private function payloadRecepcion(
        Cliente $cliente,
        ProveedorMaterial $proveedor,
        ItemMaterial $item,
        array $bultos,
    ): array {
        return [
            'operacion_id' => (string) Str::uuid(),
            'cliente_id' => $cliente->id,
            'proveedor_material_id' => $proveedor->id,
            'numero_guia_despacho' => 'GD-REC-001',
            'fecha_documento' => '2026-07-24',
            'detalles' => [[
                'item_material_id' => $item->id,
                'cantidad_documental' => collect($bultos)->sum('cantidad'),
                'cantidad_recibida' => collect($bultos)->sum('cantidad'),
                'cantidad_rechazada' => 0,
                'bultos' => $bultos,
            ]],
        ];
    }

    private function payloadUbicacion(
        string $numeroFolio,
        Posicion $posicion,
        string $sesion,
        ItemMaterial $item,
        int $version,
    ): array {
        return [
            'operacion_id' => (string) Str::uuid(),
            'numero_folio' => $numeroFolio,
            'tipo_bulto' => 'material',
            'posicion_destino_id' => $posicion->id,
            'sesion_destino_id' => $sesion,
            'version_destino_conocida' => $version,
            'generado_dispositivo_at' => now()->toAtomString(),
            'datos_material' => [
                'item_material_id' => $item->id,
                'cantidad' => 1,
                'lote' => 'IGNORADO-PARA-FOLIO-EXISTENTE',
                'proveedor' => 'Proveedor recepción',
            ],
        ];
    }

    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
