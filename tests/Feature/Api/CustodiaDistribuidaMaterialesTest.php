<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\RolUsuario;
use App\Models\AlmacenMaterial;
use App\Models\Camara;
use App\Models\ClienteMaterial;
use App\Models\DestinoMaterial;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\MovimientoAlmacenMaterial;
use App\Models\Posicion;
use App\Models\SaldoMaterialAlmacen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustodiaDistribuidaMaterialesTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrega_transfiere_custodia_y_consumo_reduce_total_empresa(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $tokenOficina = $administrador
            ->createToken('oficina-custodia', ['oficina'])
            ->plainTextToken;
        $camarero = User::factory()->create([
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-CUSTODIA',
            'nombre' => 'Tablet custodia',
            'activo' => true,
        ]);
        $tokenTablet = $camarero
            ->crearTokenParaDispositivo($dispositivo, 'tablet-custodia')
            ->plainTextToken;
        $cliente = ClienteMaterial::query()->where('codigo', 'GENERAL')->firstOrFail();
        $item = ItemMaterial::create([
            'cliente_material_id' => $cliente->id,
            'codigo' => 'FILM-CUSTODIA',
            'nombre' => 'Film de prueba',
            'categoria' => 'Embalaje',
            'categoria_operacional' => 'insumo',
            'unidad_medida' => 'kg',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        $destino = DestinoMaterial::create([
            'nombre' => 'Packing Línea 1',
            'centro_costo' => 'PACK-01',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        $camara = Camara::create([
            'codigo' => 'MAT-CUST',
            'nombre' => 'Cámara materiales custodia',
            'contenido' => ContenidoCamara::Materiales,
        ]);
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => 'B01-P01-N1',
        ]);
        $folio = Folio::create([
            'temporada_id' => $cliente->temporada->temporada_id,
            'numero_folio' => 'FCU0000001',
            'tipo_bulto' => 'material',
            'estado_operacional' => EstadoOperacionalFolio::PendienteUbicacion,
            'fecha_ingreso' => now()->subDay(),
            'activo' => true,
            'origen_sistema' => 'recepcion_materiales',
            'estado_integracion' => 'no_vinculado',
        ]);
        FolioMaterial::create([
            'folio_id' => $folio->id,
            'item_material_id' => $item->id,
            'categoria_operacional' => 'insumo',
            'cantidad_inicial' => 10,
            'cantidad_actual' => 10,
            'cantidad_reservada' => 0,
            'unidad_medida' => 'kg',
            'lote' => 'LOTE-01',
            'proveedor' => 'Proveedor prueba',
        ]);

        $sesion = $this->withToken($tokenTablet)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertCreated()
            ->json('data.id');

        $this->withToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', [
                'operacion_id' => (string) Str::uuid(),
                'numero_folio' => $folio->numero_folio,
                'tipo_bulto' => 'material',
                'camara_destino_id' => $camara->id,
                'posicion_destino_id' => $posicion->id,
                'sesion_destino_id' => $sesion,
                'version_destino_conocida' => 0,
                'generado_dispositivo_at' => now()->toAtomString(),
            ])
            ->assertOk();

        $despachoId = $this->withToken($tokenOficina)
            ->postJson('/api/materiales/despachos', [
                'operacion_id' => (string) Str::uuid(),
                'destino_material_id' => $destino->id,
                'items' => [[
                    'item_material_id' => $item->id,
                    'cantidad' => 10,
                ]],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($tokenTablet)
            ->postJson("/api/materiales/despachos/{$despachoId}/retirar", [
                'operacion_id' => (string) Str::uuid(),
                'retiros' => [[
                    'folio_id' => $folio->id,
                    'cantidad' => 10,
                    'sesion_estiba_id' => $sesion,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'completado');

        $bodega = AlmacenMaterial::query()
            ->where('codigo', AlmacenMaterial::CODIGO_BODEGA_CENTRAL)
            ->firstOrFail();
        $this->assertDatabaseHas('saldos_materiales_almacenes', [
            'folio_id' => $folio->id,
            'almacen_material_id' => $bodega->id,
            'cantidad_actual' => 0,
        ]);
        $this->assertDatabaseHas('saldos_materiales_almacenes', [
            'folio_id' => $folio->id,
            'almacen_material_id' => $destino->id,
            'cantidad_actual' => 10,
        ]);
        $this->assertDatabaseHas('folios_materiales', [
            'folio_id' => $folio->id,
            'cantidad_actual' => 10,
        ]);
        $this->assertDatabaseHas('folios', [
            'id' => $folio->id,
            'estado_operacional' => EstadoOperacionalFolio::Disponible->value,
            'activo' => true,
        ]);
        $this->assertDatabaseMissing('ubicaciones_actuales', ['folio_id' => $folio->id]);
        $this->assertDatabaseHas('movimientos_almacenes_materiales', [
            'folio_id' => $folio->id,
            'tipo' => 'entrega',
            'cantidad' => 10,
        ]);

        $this->withToken($tokenOficina)
            ->postJson('/api/materiales/almacenes/movimientos', [
                'operacion_id' => (string) Str::uuid(),
                'tipo' => 'consumo',
                'folio_id' => $folio->id,
                'almacen_origen_id' => $destino->id,
                'cantidad' => 3,
                'motivo' => 'Producción turno noche',
                'documento_relacionado' => 'TURNO-NOCHE',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'consumo')
            ->assertJsonPath('data.saldo_origen_resultante', '7.000');

        $this->assertDatabaseHas('folios_materiales', [
            'folio_id' => $folio->id,
            'cantidad_actual' => 7,
        ]);
        $this->assertDatabaseHas('saldos_materiales_almacenes', [
            'folio_id' => $folio->id,
            'almacen_material_id' => $destino->id,
            'cantidad_actual' => 7,
        ]);

        $this->withToken($tokenOficina)
            ->getJson('/api/materiales/almacenes')
            ->assertOk()
            ->assertJsonPath('perspectivas.bodega', [])
            ->assertJsonPath('perspectivas.centros_costo.0.numero_folio', 'FCU0000001')
            ->assertJsonPath('perspectivas.centros_costo.0.cantidad_actual', '7.000')
            ->assertJsonPath('perspectivas.total_empresa.0.total_empresa', '7.000');

        $this->withToken($tokenOficina)
            ->postJson('/api/materiales/almacenes/movimientos', [
                'operacion_id' => (string) Str::uuid(),
                'tipo' => 'consumo',
                'folio_id' => $folio->id,
                'almacen_origen_id' => $destino->id,
                'cantidad' => 7,
                'motivo' => 'Consumo final de la línea',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('folios_materiales', [
            'folio_id' => $folio->id,
            'cantidad_actual' => 0,
        ]);
        $this->assertDatabaseHas('folios', [
            'id' => $folio->id,
            'estado_operacional' => EstadoOperacionalFolio::Agotado->value,
            'activo' => false,
        ]);
        $this->assertSame(
            3,
            MovimientoAlmacenMaterial::query()->where('folio_id', $folio->id)->count(),
        );
        $this->assertSame(
            0.0,
            (float) SaldoMaterialAlmacen::query()
                ->where('folio_id', $folio->id)
                ->sum('cantidad_actual'),
        );
    }
}
