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
use App\Models\ReservaMaterial;
use App\Models\SaldoMaterialAlmacen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class CustodiaDistribuidaMaterialesTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrega_parcial_conserva_ubicacion_y_consumo_reduce_total_empresa(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearCamarero();
        $cliente = ClienteMaterial::query()->where('codigo', 'GENERAL')->firstOrFail();
        $item = $this->crearItem($administrador, $cliente);
        $destino = $this->crearDestino($administrador, 'Packing Línea 1', 'PACK-01');
        [$camara, $posicion] = $this->crearCamara();
        $folio = $this->crearFolio($item, 10);
        $sesion = $this->conToken($tokenTablet)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertCreated()
            ->json('data.id');

        $this->ubicar($tokenTablet, $folio, $camara, $posicion, $sesion);
        $despachoId = $this->conToken($tokenOficina)
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
        $bodega = AlmacenMaterial::query()
            ->where('codigo', AlmacenMaterial::CODIGO_BODEGA_CENTRAL)
            ->firstOrFail();
        $saldoBodega = SaldoMaterialAlmacen::query()
            ->where('folio_id', $folio->id)
            ->where('almacen_material_id', $bodega->id)
            ->firstOrFail();
        $reserva = ReservaMaterial::query()->firstOrFail();

        $this->assertSame($saldoBodega->id, $reserva->saldo_material_almacen_id);
        $this->assertSame(10.0, (float) $saldoBodega->cantidad_reservada);
        $this->assertProyeccion($folio->id, 10, 10);

        $this->entregar($tokenTablet, $despachoId, $folio, $sesion, 4)
            ->assertJsonPath('data.estado', 'parcial');

        $this->assertDatabaseHas('saldos_materiales_almacenes', [
            'folio_id' => $folio->id,
            'almacen_material_id' => $bodega->id,
            'cantidad_actual' => 6,
            'cantidad_reservada' => 6,
            'camara_id' => $camara->id,
            'posicion_id' => $posicion->id,
        ]);
        $this->assertDatabaseHas('saldos_materiales_almacenes', [
            'folio_id' => $folio->id,
            'almacen_material_id' => $destino->id,
            'cantidad_actual' => 4,
            'cantidad_reservada' => 0,
            'camara_id' => null,
            'posicion_id' => null,
        ]);
        $this->assertDatabaseHas('ubicaciones_actuales', [
            'folio_id' => $folio->id,
            'camara_id' => $camara->id,
            'posicion_id' => $posicion->id,
        ]);
        $this->assertProyeccion($folio->id, 10, 6);

        $this->entregar($tokenTablet, $despachoId, $folio, $sesion, 6)
            ->assertJsonPath('data.estado', 'completado');

        $this->assertDatabaseHas('saldos_materiales_almacenes', [
            'folio_id' => $folio->id,
            'almacen_material_id' => $bodega->id,
            'cantidad_actual' => 0,
            'cantidad_reservada' => 0,
            'camara_id' => null,
            'posicion_id' => null,
        ]);
        $this->assertDatabaseHas('saldos_materiales_almacenes', [
            'folio_id' => $folio->id,
            'almacen_material_id' => $destino->id,
            'cantidad_actual' => 10,
            'camara_id' => null,
            'posicion_id' => null,
        ]);
        $this->assertDatabaseMissing('ubicaciones_actuales', ['folio_id' => $folio->id]);
        $this->assertDatabaseHas('folios', [
            'id' => $folio->id,
            'estado_operacional' => EstadoOperacionalFolio::Disponible->value,
            'activo' => true,
        ]);
        $this->assertProyeccion($folio->id, 10, 0);

        $operacionConsumo = (string) Str::uuid();
        $payloadConsumo = [
            'operacion_id' => $operacionConsumo,
            'tipo' => 'consumo',
            'folio_id' => $folio->id,
            'almacen_origen_id' => $destino->id,
            'cantidad' => 3,
            'motivo' => 'Producción turno noche',
            'documento_relacionado' => 'TURNO-NOCHE',
        ];

        $this->conToken($tokenOficina)
            ->postJson('/api/materiales/almacenes/movimientos', $payloadConsumo)
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'consumo')
            ->assertJsonPath('data.saldo_origen_resultante', '7.000');
        $this->conToken($tokenOficina)
            ->postJson('/api/materiales/almacenes/movimientos', $payloadConsumo)
            ->assertCreated()
            ->assertJsonPath('data.saldo_origen_resultante', '7.000');

        $this->assertSame(
            1,
            MovimientoAlmacenMaterial::query()
                ->where('operacion_id', $operacionConsumo)
                ->count(),
        );
        $this->assertProyeccion($folio->id, 7, 0);

        $this->conToken($tokenOficina)
            ->getJson('/api/materiales/almacenes')
            ->assertOk()
            ->assertJsonPath('perspectivas.bodega', [])
            ->assertJsonPath('perspectivas.centros_costo.0.numero_folio', 'FCU0000001')
            ->assertJsonPath('perspectivas.centros_costo.0.cantidad_actual', '7.000')
            ->assertJsonPath('perspectivas.total_empresa.0.total_empresa', '7.000');

        $this->conToken($tokenOficina)
            ->postJson('/api/materiales/almacenes/movimientos', [
                'operacion_id' => (string) Str::uuid(),
                'tipo' => 'consumo',
                'folio_id' => $folio->id,
                'almacen_origen_id' => $destino->id,
                'cantidad' => 7,
                'motivo' => 'Consumo final de la línea',
            ])
            ->assertCreated();

        $this->assertProyeccion($folio->id, 0, 0);
        $this->assertDatabaseHas('folios', [
            'id' => $folio->id,
            'estado_operacional' => EstadoOperacionalFolio::Agotado->value,
            'activo' => false,
        ]);
    }

    public function test_almacen_virtual_rechaza_ubicacion_y_movimientos_son_inmutables(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        $cliente = ClienteMaterial::query()->where('codigo', 'GENERAL')->firstOrFail();
        $item = $this->crearItem($administrador, $cliente);
        $origen = $this->crearDestino($administrador, 'Packing Línea 1', 'PACK-01');
        $destino = $this->crearDestino($administrador, 'Frigorífico', 'FRIO-01');
        [$camara, $posicion] = $this->crearCamara();
        $folio = $this->crearFolio($item, 5);
        $bodega = AlmacenMaterial::query()
            ->where('codigo', AlmacenMaterial::CODIGO_BODEGA_CENTRAL)
            ->firstOrFail();
        $saldoBodega = SaldoMaterialAlmacen::query()
            ->where('folio_id', $folio->id)
            ->where('almacen_material_id', $bodega->id)
            ->firstOrFail();
        $saldoBodega->update([
            'cantidad_actual' => 0,
            'camara_id' => null,
            'posicion_id' => null,
        ]);
        SaldoMaterialAlmacen::create([
            'folio_id' => $folio->id,
            'almacen_material_id' => $origen->id,
            'cantidad_actual' => 5,
            'cantidad_reservada' => 0,
        ]);

        $this->conToken($tokenOficina)
            ->postJson('/api/materiales/almacenes/movimientos', [
                'operacion_id' => (string) Str::uuid(),
                'tipo' => 'transferencia',
                'folio_id' => $folio->id,
                'almacen_origen_id' => $origen->id,
                'almacen_destino_id' => $destino->id,
                'cantidad' => 2,
                'camara_destino_id' => $camara->id,
                'posicion_destino_id' => $posicion->id,
            ])
            ->assertUnprocessable();

        $movimientoId = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/almacenes/movimientos', [
                'operacion_id' => (string) Str::uuid(),
                'tipo' => 'transferencia',
                'folio_id' => $folio->id,
                'almacen_origen_id' => $origen->id,
                'almacen_destino_id' => $destino->id,
                'cantidad' => 2,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->expectException(LogicException::class);
        MovimientoAlmacenMaterial::query()->findOrFail($movimientoId)->update([
            'motivo' => 'Intento de modificación histórica',
        ]);
    }

    public function test_esquema_vincula_todas_las_reservas_al_saldo_concreto(): void
    {
        $this->assertTrue(
            Schema::hasColumn('reservas_materiales', 'saldo_material_almacen_id'),
        );
        $this->assertTrue(
            Schema::hasColumn(
                'reservas_transformacion_materiales',
                'saldo_material_almacen_id',
            ),
        );
        $this->assertTrue(
            Schema::hasColumn('saldos_materiales_almacenes', 'version'),
        );
    }

    private function crearAdministrador(): array
    {
        $usuario = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        return [
            $usuario,
            $usuario->createToken('oficina-custodia', ['oficina'])->plainTextToken,
        ];
    }

    private function crearCamarero(): array
    {
        $usuario = User::factory()->create([
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-CUSTODIA',
            'nombre' => 'Tablet custodia',
            'activo' => true,
        ]);

        return [
            $usuario,
            $dispositivo,
            $usuario
                ->crearTokenParaDispositivo($dispositivo, 'tablet-custodia')
                ->plainTextToken,
        ];
    }

    private function crearItem(User $usuario, ClienteMaterial $cliente): ItemMaterial
    {
        return ItemMaterial::create([
            'cliente_material_id' => $cliente->id,
            'codigo' => 'FILM-CUSTODIA',
            'nombre' => 'Film de prueba',
            'categoria' => 'Embalaje',
            'categoria_operacional' => 'insumo',
            'unidad_medida' => 'kg',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }

    private function crearDestino(
        User $usuario,
        string $nombre,
        string $centroCosto,
    ): DestinoMaterial {
        return DestinoMaterial::create([
            'nombre' => $nombre,
            'centro_costo' => $centroCosto,
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }

    private function crearCamara(): array
    {
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

        return [$camara, $posicion];
    }

    private function crearFolio(ItemMaterial $item, float $cantidad): Folio
    {
        $item->loadMissing('cliente.temporada');
        $folio = Folio::create([
            'temporada_id' => $item->cliente->temporada->temporada_id,
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
            'cantidad_inicial' => $cantidad,
            'cantidad_actual' => $cantidad,
            'cantidad_reservada' => 0,
            'unidad_medida' => 'kg',
            'lote' => 'LOTE-01',
            'proveedor' => 'Proveedor prueba',
        ]);

        return $folio;
    }

    private function ubicar(
        string $token,
        Folio $folio,
        Camara $camara,
        Posicion $posicion,
        string $sesion,
    ): void {
        $this->conToken($token)
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
    }

    private function entregar(
        string $token,
        string $despachoId,
        Folio $folio,
        string $sesion,
        float $cantidad,
    ) {
        return $this->conToken($token)
            ->postJson("/api/materiales/despachos/{$despachoId}/retirar", [
                'operacion_id' => (string) Str::uuid(),
                'retiros' => [[
                    'folio_id' => $folio->id,
                    'cantidad' => $cantidad,
                    'sesion_estiba_id' => $sesion,
                ]],
            ])
            ->assertOk();
    }

    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function assertProyeccion(
        string $folioId,
        float $cantidadActual,
        float $cantidadReservada,
    ): void {
        $material = FolioMaterial::query()->findOrFail($folioId);
        $this->assertSame($cantidadActual, (float) $material->cantidad_actual);
        $this->assertSame($cantidadReservada, (float) $material->cantidad_reservada);
        $this->assertSame(
            $cantidadActual,
            (float) SaldoMaterialAlmacen::query()
                ->where('folio_id', $folioId)
                ->sum('cantidad_actual'),
        );
        $this->assertSame(
            $cantidadReservada,
            (float) SaldoMaterialAlmacen::query()
                ->where('folio_id', $folioId)
                ->sum('cantidad_reservada'),
        );
    }
}
