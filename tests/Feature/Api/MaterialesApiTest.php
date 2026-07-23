<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\ClienteMaterial;
use App\Models\CorreccionItemFolioMaterial;
use App\Models\DestinoMaterial;
use App\Models\Dispositivo;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\MovimientoInventarioMaterial;
use App\Models\Posicion;
use App\Models\RetiroMaterial;
use App\Models\User;
use App\Services\Temporadas\ServicioTemporadaGlobal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MaterialesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_mantiene_catalogos_y_operador_solo_los_consulta(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();

        $temporadaId = $this->conToken($tokenOficina)
            ->postJson('/api/administracion/temporadas', [
                'codigo' => ' 2026-2027 ',
                'nombre' => ' Temporada materiales 2026-2027 ',
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2027-06-30',
                'activa' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.codigo', '2026-2027')
            ->assertJsonPath('data.activa', true)
            ->json('data.configuracion_material_id');

        $clienteGlobalId = $this->conToken($tokenOficina)
            ->postJson('/api/administracion/clientes', [
                'codigo' => 'CLI-001',
                'nombre' => ' Exportadora del Sur ',
                'activo' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.codigo', 'CLI-001')
            ->json('data.id');
        $clienteId = ClienteMaterial::query()
            ->where('temporada_material_id', $temporadaId)
            ->where('cliente_id', $clienteGlobalId)
            ->firstOrFail()
            ->id;

        $itemId = $this->conToken($tokenOficina)
            ->postJson('/api/administracion/materiales/items', [
                'cliente_material_id' => $clienteId,
                'codigo' => '  film-01 ',
                'nombre' => ' Film stretch ',
                'categoria' => 'Embalaje',
                'unidad_medida' => 'ROLLOS',
            ])
            ->assertCreated()
            ->assertJsonPath('data.cliente.codigo', 'CLI-001')
            ->assertJsonPath('data.codigo', 'FILM-01')
            ->assertJsonPath('data.unidad_medida', 'rollos')
            ->json('data.id');

        $destinoId = $this->conToken($tokenOficina)
            ->postJson('/api/administracion/materiales/destinos', [
                'nombre' => ' Packing norte ',
                'centro_costo' => ' cc-100 ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Packing norte')
            ->assertJsonPath('data.centro_costo', 'CC-100')
            ->json('data.id');

        $this->conToken($tokenTablet)
            ->getJson('/api/materiales/catalogo')
            ->assertOk()
            ->assertJsonPath('temporada.id', $temporadaId)
            ->assertJsonPath('clientes.0.id', $clienteId)
            ->assertJsonPath('items.0.id', $itemId)
            ->assertJsonPath('destinos.0.id', $destinoId);

        $this->conToken($tokenTablet)
            ->postJson('/api/administracion/materiales/items', [
                'cliente_material_id' => $clienteId,
                'codigo' => 'NO-AUTORIZADO',
                'nombre' => 'No autorizado',
                'unidad_medida' => 'unidad',
            ])
            ->assertForbidden();

        $this->assertSame($administrador->id, ItemMaterial::findOrFail($itemId)->creado_por_user_id);
    }

    public function test_el_codigo_de_item_es_unico_por_cliente_y_no_global(): void
    {
        [, $tokenOficina] = $this->crearAdministrador();
        $temporadaId = ClienteMaterial::query()->where('codigo', 'GENERAL')->firstOrFail()->temporada_material_id;
        $clientes = collect(['CLI-NORTE', 'CLI-SUR'])->map(function (string $codigo) use ($tokenOficina, $temporadaId): string {
            $clienteGlobalId = $this
                ->conToken($tokenOficina)
                ->postJson('/api/administracion/clientes', [
                    'codigo' => $codigo,
                    'nombre' => 'Cliente '.$codigo,
                    'activo' => true,
                ])
                ->assertCreated()
                ->json('data.id');

            return ClienteMaterial::query()
                ->where('temporada_material_id', $temporadaId)
                ->where('cliente_id', $clienteGlobalId)
                ->firstOrFail()
                ->id;
        });

        foreach ($clientes as $clienteId) {
            $this->conToken($tokenOficina)
                ->postJson('/api/administracion/materiales/items', [
                    'cliente_material_id' => $clienteId,
                    'codigo' => 'CAJA-5KG',
                    'nombre' => 'Caja cartón 5 kg',
                    'unidad_medida' => 'unidades',
                ])
                ->assertCreated();
        }

        $this->conToken($tokenOficina)
            ->postJson('/api/administracion/materiales/items', [
                'cliente_material_id' => $clientes->first(),
                'codigo' => 'CAJA-5KG',
                'nombre' => 'Caja duplicada',
                'unidad_medida' => 'unidades',
            ])
            ->assertUnprocessable();

        $this->assertSame(2, ItemMaterial::query()->where('codigo', 'CAJA-5KG')->count());
    }

    public function test_activar_temporada_material_desactiva_la_anterior_y_filtra_el_catalogo_operacional(): void
    {
        [, $tokenOficina] = $this->crearAdministrador();
        $temporadaAnteriorId = ClienteMaterial::query()->where('codigo', 'GENERAL')->firstOrFail()->temporada_material_id;
        $temporadaNuevaId = $this->conToken($tokenOficina)
            ->postJson('/api/administracion/temporadas', [
                'codigo' => '2027-2028',
                'nombre' => 'Temporada materiales 2027-2028',
                'activa' => true,
            ])
            ->assertCreated()
            ->json('data.configuracion_material_id');
        $clienteGlobalId = $this->conToken($tokenOficina)
            ->postJson('/api/administracion/clientes', [
                'codigo' => 'CLI-001',
                'nombre' => 'Cliente temporada nueva',
                'activo' => true,
            ])
            ->assertCreated()
            ->json('data.id');
        $clienteId = ClienteMaterial::query()
            ->where('temporada_material_id', $temporadaNuevaId)
            ->where('cliente_id', $clienteGlobalId)
            ->firstOrFail()
            ->id;

        $this->conToken($tokenOficina)
            ->postJson('/api/administracion/materiales/items', [
                'cliente_material_id' => $clienteId,
                'codigo' => 'CAJA-5KG',
                'nombre' => 'Caja temporada nueva',
                'unidad_medida' => 'unidades',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('temporadas_materiales', ['id' => $temporadaAnteriorId, 'activa' => false]);
        $this->conToken($tokenOficina)
            ->getJson('/api/materiales/catalogo')
            ->assertOk()
            ->assertJsonPath('temporada.id', $temporadaNuevaId)
            ->assertJsonPath('clientes.0.id', $clienteId)
            ->assertJsonPath('items.0.codigo', 'CAJA-5KG')
            ->assertJsonMissing(['codigo' => 'GENERAL']);
    }

    public function test_no_permite_ingresar_nuevo_material_con_item_de_temporada_inactiva(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();
        $itemAnterior = $this->crearItem($administrador);
        [$camara, $posicion] = $this->crearCamara('MAT-01', ContenidoCamara::Materiales);
        $sesion = $this->abrirSesion($tokenTablet, $camara);

        $this->conToken($tokenOficina)
            ->postJson('/api/administracion/temporadas', [
                'codigo' => '2028-2029',
                'nombre' => 'Temporada materiales 2028-2029',
                'activa' => true,
            ])
            ->assertCreated();

        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicion,
                $sesion,
                $itemAnterior,
                'MAT-TEMPORADA-ANTERIOR',
                0,
                10,
            ))
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');

        $this->assertDatabaseMissing('folios', ['numero_folio' => 'MAT-TEMPORADA-ANTERIOR']);
    }

    public function test_despachos_y_kardex_no_mezclan_temporadas_anteriores(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();
        $item = $this->crearItem($administrador);
        $destino = $this->crearDestino($administrador);
        [$camara, $posicion] = $this->crearCamara('MAT-TEMP-01', ContenidoCamara::Materiales);
        $sesion = $this->abrirSesion($tokenTablet, $camara);

        $this->ubicarMaterial(
            $tokenTablet,
            $posicion,
            $sesion,
            $item,
            'MAT-HISTORICO-01',
            0,
            20,
            now()->toAtomString(),
        );
        $despachoId = $this->crearDespacho($tokenOficina, $item, $destino, 5);

        app(ServicioTemporadaGlobal::class)->guardar([
            'codigo' => 'MAT-NUEVA',
            'nombre' => 'Temporada nueva de materiales',
            'activa' => true,
        ], usuarioId: $administrador->id);

        $this->conToken($tokenOficina)
            ->getJson('/api/materiales/despachos')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonMissing(['id' => $despachoId]);

        $this->conToken($tokenOficina)
            ->getJson('/api/materiales/kardex')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->conToken($tokenOficina)
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_ubica_material_solo_en_su_tipo_de_camara_y_crea_kardex_de_ingreso(): void
    {
        [$administrador] = $this->crearAdministrador();
        [$operador, $dispositivo, $tokenTablet] = $this->crearOperador();
        [, , $tokenFrio] = $this->crearCamareroFrio();
        $item = $this->crearItem($administrador);
        [$camaraMaterial, $posicionMaterial] = $this->crearCamara('CAM-01', ContenidoCamara::Materiales);
        [$camaraProducto, $posicionProducto] = $this->crearCamara('CAM-02', ContenidoCamara::Productos);
        $sesionMaterial = $this->abrirSesion($tokenTablet, $camaraMaterial);
        $sesionProducto = $this->abrirSesion($tokenFrio, $camaraProducto);

        $this->conToken($tokenFrio)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicionProducto,
                $sesionProducto,
                $item,
                'MAT-RECHAZADO',
                0,
                25,
            ))
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');

        $folioId = $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicionMaterial,
                $sesionMaterial,
                $item,
                'MAT-0001',
                0,
                25.5,
            ))
            ->assertOk()
            ->assertJsonPath('data.folio.tipo_bulto', 'material')
            ->json('data.folio.id');

        $this->assertDatabaseHas('folios_materiales', [
            'folio_id' => $folioId,
            'item_material_id' => $item->id,
            'cantidad_inicial' => 25.500,
            'cantidad_actual' => 25.500,
            'unidad_medida' => 'rollos',
            'lote' => 'L-2026-07',
        ]);
        $this->assertDatabaseHas('movimientos_inventario_materiales', [
            'folio_id' => $folioId,
            'tipo' => 'ingreso',
            'cantidad' => 25.500,
            'user_id' => $operador->id,
            'dispositivo_id' => $dispositivo->id,
        ]);
        $this->assertDatabaseMissing('folios', ['numero_folio' => 'MAT-RECHAZADO']);
    }

    public function test_reserva_fifo_y_permite_retiros_parciales_hasta_liberar_el_folio(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [$operadorMateriales, $tabletMateriales, $tokenTablet] = $this->crearOperador();
        [, , $tokenFrio] = $this->crearCamareroFrio();
        $item = $this->crearItem($administrador);
        $destino = $this->crearDestino($administrador);
        [$camara, $posicion1, $posicion2] = $this->crearCamara(
            'CAM-01',
            ContenidoCamara::Materiales,
            2,
        );
        $sesion = $this->abrirSesion($tokenTablet, $camara);
        $folio1 = $this->ubicarMaterial(
            $tokenTablet,
            $posicion1,
            $sesion,
            $item,
            'MAT-ANTIGUO',
            0,
            10,
            now()->subDay()->toAtomString(),
        );
        $folio2 = $this->ubicarMaterial(
            $tokenTablet,
            $posicion2,
            $sesion,
            $item,
            'MAT-NUEVO',
            1,
            6,
            now()->toAtomString(),
        );

        $payloadDespacho = [
            'operacion_id' => (string) Str::uuid(),
            'destino_material_id' => $destino->id,
            'observacion' => 'Reposición de línea',
            'items' => [[
                'item_material_id' => $item->id,
                'cantidad' => 12,
            ]],
        ];
        $despachoId = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/despachos', $payloadDespacho)
            ->assertCreated()
            ->assertJsonPath('data.codigo', 'MAT-DES-000001')
            ->assertJsonPath('data.temporada.activa', true)
            ->assertJsonPath('data.origen', 'oficina')
            ->assertJsonPath('data.destino.centro_costo', 'CC-100')
            ->assertJsonPath('data.items.0.sugerencias_fifo.0.folio_id', $folio1)
            ->assertJsonPath('data.items.0.sugerencias_fifo.0.cantidad', '10.000')
            ->assertJsonPath('data.items.0.sugerencias_fifo.1.folio_id', $folio2)
            ->assertJsonPath('data.items.0.sugerencias_fifo.1.cantidad', '2.000')
            ->json('data.id');

        $this->conToken($tokenOficina)
            ->postJson('/api/materiales/despachos', $payloadDespacho)
            ->assertCreated()
            ->assertJsonPath('data.id', $despachoId);
        $this->assertDatabaseCount('despachos_materiales', 1);
        $this->assertDatabaseCount('notificaciones_operacionales', 1);

        $this->conToken($tokenTablet)
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonPath('resumen.no_leidas', 1)
            ->assertJsonPath('data.0.tipo', 'despacho_material_creado')
            ->assertJsonPath('data.0.despacho_material.id', $despachoId)
            ->assertJsonPath('data.0.despacho_material.codigo', 'MAT-DES-000001');

        $this->conToken($tokenFrio)
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonPath('resumen.no_leidas', 0)
            ->assertJsonCount(0, 'data');

        $this->assertSame('10.000', FolioMaterial::findOrFail($folio1)->cantidad_reservada);
        $this->assertSame('2.000', FolioMaterial::findOrFail($folio2)->cantidad_reservada);

        $operacionRetiroParcial = (string) Str::uuid();
        $retiroParcial = [
            'operacion_id' => $operacionRetiroParcial,
            'retiros' => [[
                'folio_id' => $folio1,
                'cantidad' => 4,
                'sesion_estiba_id' => $sesion,
            ]],
        ];
        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/despachos/{$despachoId}/retirar", $retiroParcial)
            ->assertOk()
            ->assertJsonPath('data.estado', 'parcial')
            ->assertJsonPath('data.items.0.cantidad_despachada', '4.000')
            ->assertJsonPath('data.items.0.cantidad_pendiente', '8.000')
            ->assertJsonPath('data.items.0.retiros.0.folio.id', $folio1)
            ->assertJsonPath('data.items.0.retiros.0.cantidad_retirada', '4.000')
            ->assertJsonPath('data.items.0.retiros.0.usuario.id', $operadorMateriales->id)
            ->assertJsonPath('data.items.0.retiros.0.dispositivo.id', $tabletMateriales->id)
            ->assertJsonPath('data.items.0.retiros.0.siguio_fifo', true);

        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/despachos/{$despachoId}/retirar", $retiroParcial)
            ->assertOk()
            ->assertJsonPath('data.items.0.cantidad_despachada', '4.000');

        $this->assertSame('6.000', FolioMaterial::findOrFail($folio1)->cantidad_actual);
        $this->assertSame(1, RetiroMaterial::query()
            ->where('operacion_retiro_material_id', $operacionRetiroParcial)
            ->count());
        $this->assertDatabaseHas('ubicaciones_actuales', [
            'folio_id' => $folio1,
            'posicion_id' => $posicion1->id,
        ]);

        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/despachos/{$despachoId}/retirar", [
                'operacion_id' => (string) Str::uuid(),
                'retiros' => [
                    [
                        'folio_id' => $folio1,
                        'cantidad' => 6,
                        'sesion_estiba_id' => $sesion,
                    ],
                    [
                        'folio_id' => $folio2,
                        'cantidad' => 2,
                        'sesion_estiba_id' => $sesion,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'completado')
            ->assertJsonPath('data.items.0.cantidad_pendiente', '0.000');

        $this->assertDatabaseMissing('ubicaciones_actuales', ['folio_id' => $folio1]);
        $this->assertDatabaseHas('folios', [
            'id' => $folio1,
            'estado_operacional' => 'despachado',
            'activo' => false,
        ]);
        $this->assertDatabaseHas('ubicaciones_actuales', [
            'folio_id' => $folio2,
            'posicion_id' => $posicion2->id,
        ]);
        $this->assertSame('4.000', FolioMaterial::findOrFail($folio2)->cantidad_actual);
        $this->assertSame(3, $camara->refresh()->version_plano);
    }

    public function test_inventario_refleja_el_stock_disponible_despues_de_reservar(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();
        $item = $this->crearItem($administrador);
        $destino = $this->crearDestino($administrador);
        [$camara, $posicion] = $this->crearCamara('CAM-STOCK', ContenidoCamara::Materiales);
        $sesion = $this->abrirSesion($tokenTablet, $camara);
        $folioId = $this->ubicarMaterial(
            $tokenTablet,
            $posicion,
            $sesion,
            $item,
            'MAT-STOCK',
            0,
            10,
            now()->toAtomString(),
        );

        $this->conToken($tokenOficina)
            ->getJson('/api/materiales/inventario')
            ->assertOk()
            ->assertJsonPath('data.0.folio_id', $folioId)
            ->assertJsonPath('data.0.cantidad_actual', '10.000')
            ->assertJsonPath('data.0.cantidad_reservada', '0.000')
            ->assertJsonPath('data.0.cantidad_disponible', '10.000');

        $this->conToken($tokenOficina)
            ->postJson('/api/materiales/despachos', [
                'operacion_id' => (string) Str::uuid(),
                'destino_material_id' => $destino->id,
                'items' => [[
                    'item_material_id' => $item->id,
                    'cantidad' => 4,
                ]],
            ])
            ->assertCreated();

        $this->conToken($tokenOficina)
            ->getJson('/api/materiales/inventario')
            ->assertOk()
            ->assertJsonPath('data.0.cantidad_actual', '10.000')
            ->assertJsonPath('data.0.cantidad_reservada', '4.000')
            ->assertJsonPath('data.0.cantidad_disponible', '6.000');
    }

    public function test_fifo_es_solo_sugerencia_y_registra_la_excepcion(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();
        $item = $this->crearItem($administrador);
        $destino = $this->crearDestino($administrador);
        [$camara, $posicion1, $posicion2] = $this->crearCamara(
            'CAM-01',
            ContenidoCamara::Materiales,
            2,
        );
        $sesion = $this->abrirSesion($tokenTablet, $camara);
        $this->ubicarMaterial($tokenTablet, $posicion1, $sesion, $item, 'MAT-FIFO', 0, 5, now()->subDay()->toAtomString());
        $folioNoSugerido = $this->ubicarMaterial($tokenTablet, $posicion2, $sesion, $item, 'MAT-OTRO', 1, 5, now()->toAtomString());

        $despachoId = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/despachos', [
                'operacion_id' => (string) Str::uuid(),
                'destino_material_id' => $destino->id,
                'items' => [[
                    'item_material_id' => $item->id,
                    'cantidad' => 2,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.sugerencias_fifo.0.numero_folio', 'MAT-FIFO')
            ->json('data.id');

        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/despachos/{$despachoId}/retirar", [
                'operacion_id' => (string) Str::uuid(),
                'retiros' => [[
                    'folio_id' => $folioNoSugerido,
                    'cantidad' => 2,
                    'sesion_estiba_id' => $sesion,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'completado');

        $this->assertFalse(RetiroMaterial::query()->latest()->firstOrFail()->siguio_fifo);
    }

    public function test_operador_no_puede_cancelar_un_despacho_de_materiales(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenOperador] = $this->crearOperador();
        $item = $this->crearItem($administrador);
        $destino = $this->crearDestino($administrador);
        $despachoId = $this->crearDespacho(
            $tokenOficina,
            $item,
            $destino,
            5,
        );

        $this->conToken($tokenOperador)
            ->postJson('/api/materiales/despachos', [])
            ->assertForbidden();

        $this->conToken($tokenOperador)
            ->postJson("/api/materiales/despachos/{$despachoId}/cancelar", [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'Solicitud anulada por producción.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('despachos_materiales', [
            'id' => $despachoId,
            'estado' => 'pendiente',
            'cancelado_at' => null,
        ]);
    }

    public function test_cancelacion_autorizada_libera_reservas_audita_y_es_idempotente(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();
        $item = $this->crearItem($administrador);
        $destino = $this->crearDestino($administrador);
        [$camara, $posicion] = $this->crearCamara(
            'CAM-MAT-CANCELA',
            ContenidoCamara::Materiales,
        );
        $sesion = $this->abrirSesion($tokenTablet, $camara);
        $folioId = $this->ubicarMaterial(
            $tokenTablet,
            $posicion,
            $sesion,
            $item,
            'MAT-CANCELA',
            0,
            10,
            now()->toAtomString(),
        );
        $despachoId = $this->crearDespacho(
            $tokenOficina,
            $item,
            $destino,
            6,
        );
        $operacionId = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacionId,
            'motivo' => 'Orden interna anulada por producción.',
        ];

        $this->assertSame('6.000', FolioMaterial::findOrFail($folioId)->cantidad_reservada);

        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/despachos/{$despachoId}/cancelar", $payload)
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelado')
            ->assertJsonPath('data.cancelacion.motivo', $payload['motivo'])
            ->assertJsonPath('data.cancelacion.usuario.id', $administrador->id)
            ->assertJsonPath('data.cancelacion.dispositivo', null);

        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/despachos/{$despachoId}/cancelar", $payload)
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelado');

        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/despachos/{$despachoId}/cancelar", [
                'operacion_id' => $operacionId,
                'motivo' => 'Mismo UUID con un motivo diferente.',
            ])
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->assertSame('0.000', FolioMaterial::findOrFail($folioId)->cantidad_reservada);
        $this->assertDatabaseHas('despachos_materiales', [
            'id' => $despachoId,
            'estado' => 'cancelado',
            'cancelacion_operacion_id' => $operacionId,
            'cancelado_por_user_id' => $administrador->id,
            'cancelado_desde_dispositivo_id' => null,
            'cancelacion_motivo' => $payload['motivo'],
        ]);
        $this->assertDatabaseHas('reservas_materiales', [
            'folio_id' => $folioId,
            'estado' => 'liberada',
        ]);
    }

    public function test_cancelacion_desde_tablet_autorizada_registra_dispositivo(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        $item = $this->crearItem($administrador);
        $destino = $this->crearDestino($administrador);
        $despachoId = $this->crearDespacho(
            $tokenOficina,
            $item,
            $destino,
            1,
        );
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorMateriales,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-SUPERVISOR',
            'nombre' => 'Tablet supervisor',
            'activo' => true,
        ]);
        $tokenSupervisor = $supervisor
            ->crearTokenParaDispositivo($dispositivo, 'tablet-supervisor')
            ->plainTextToken;

        $this->conToken($tokenSupervisor)
            ->postJson("/api/materiales/despachos/{$despachoId}/cancelar", [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'Cancelación supervisada desde cámara.',
            ])
            ->assertOk()
            ->assertJsonPath('data.cancelacion.usuario.id', $supervisor->id)
            ->assertJsonPath('data.cancelacion.dispositivo.id', $dispositivo->id);

        $this->assertDatabaseHas('despachos_materiales', [
            'id' => $despachoId,
            'cancelado_por_user_id' => $supervisor->id,
            'cancelado_desde_dispositivo_id' => $dispositivo->id,
        ]);
    }

    public function test_una_posicion_material_admite_varios_items_solo_del_mismo_cliente(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();
        $itemUno = $this->crearItem($administrador);
        $itemDos = ItemMaterial::create([
            'cliente_material_id' => $itemUno->cliente_material_id,
            'codigo' => 'FILM-02',
            'nombre' => 'Film stretch angosto',
            'categoria' => 'Embalaje',
            'unidad_medida' => 'rollos',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        [$camara, $posicion] = $this->crearCamara('MAT-MULTI', ContenidoCamara::Materiales);
        $sesion = $this->abrirSesion($tokenTablet, $camara);

        $folioUno = $this->ubicarMaterial($tokenTablet, $posicion, $sesion, $itemUno, 'BULTO-L1', 0, 10, now()->toAtomString());
        $folioDos = $this->ubicarMaterial($tokenTablet, $posicion, $sesion, $itemDos, 'BULTO-L2', 1, 4, now()->toAtomString());

        $this->assertDatabaseHas('ubicaciones_actuales', ['folio_id' => $folioUno, 'posicion_id' => $posicion->id]);
        $this->assertDatabaseHas('ubicaciones_actuales', ['folio_id' => $folioDos, 'posicion_id' => $posicion->id]);
        $this->conToken($tokenTablet)
            ->getJson("/api/camaras/{$camara->id}/plano")
            ->assertOk()
            ->assertJsonCount(2, 'data.posiciones.0.folios')
            ->assertJsonPath('data.posiciones.0.folios.0.material.item.cliente.nombre', 'Sin clasificar');

        $otroCliente = ClienteMaterial::create([
            'temporada_material_id' => $itemUno->cliente->temporada_material_id,
            'codigo' => 'OTRO',
            'nombre' => 'Otro cliente',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        $itemOtroCliente = ItemMaterial::create([
            'cliente_material_id' => $otroCliente->id,
            'codigo' => 'FILM-01',
            'nombre' => 'Film de otro cliente',
            'unidad_medida' => 'rollos',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);

        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicion,
                $sesion,
                $itemOtroCliente,
                'BULTO-OTRO-CLIENTE',
                2,
                2,
            ))
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->conToken($tokenOficina)
            ->getJson("/api/materiales/inventario?cliente_id={$itemUno->cliente_material_id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('resumen_clientes.0.folios', 2)
            ->assertJsonPath('resumen_clientes.0.items', 2)
            ->assertJsonPath('resumen_clientes.0.posiciones', 1);
    }

    public function test_supervisor_corrige_codigo_estibado_con_auditoria_y_kardex_doble(): void
    {
        [$administrador] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorMateriales,
            'activo' => true,
        ]);
        $tokenSupervisor = $supervisor->createToken('oficina-supervisor', ['oficina'])->plainTextToken;
        $itemAnterior = $this->crearItem($administrador);
        $itemNuevo = ItemMaterial::create([
            'cliente_material_id' => $itemAnterior->cliente_material_id,
            'codigo' => 'FILM-CORRECTO',
            'nombre' => 'Film stretch correcto',
            'categoria' => 'Embalaje',
            'unidad_medida' => 'rollos',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        [$camara, $posicion] = $this->crearCamara('MAT-CORR', ContenidoCamara::Materiales);
        $sesion = $this->abrirSesion($tokenTablet, $camara);
        $folioId = $this->ubicarMaterial($tokenTablet, $posicion, $sesion, $itemAnterior, 'MAT-CORREGIR', 0, 12, now()->toAtomString());
        $operacionId = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacionId,
            'item_material_id' => $itemNuevo->id,
            'motivo' => 'Código seleccionado incorrectamente al estibar.',
        ];

        $correccionId = $this->conToken($tokenSupervisor)
            ->postJson("/api/materiales/inventario/{$folioId}/corregir-item", $payload)
            ->assertOk()
            ->assertJsonPath('data.item_anterior.codigo', 'FILM-01')
            ->assertJsonPath('data.item_nuevo.codigo', 'FILM-CORRECTO')
            ->assertJsonPath('data.usuario.id', $supervisor->id)
            ->json('data.id');

        $this->conToken($tokenSupervisor)
            ->postJson("/api/materiales/inventario/{$folioId}/corregir-item", $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $correccionId);

        $this->assertDatabaseHas('folios_materiales', [
            'folio_id' => $folioId,
            'item_material_id' => $itemNuevo->id,
            'cantidad_actual' => 12,
        ]);
        $this->assertDatabaseHas('correcciones_items_folios_materiales', [
            'id' => $correccionId,
            'operacion_id' => $operacionId,
            'item_anterior_id' => $itemAnterior->id,
            'item_nuevo_id' => $itemNuevo->id,
            'user_id' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('movimientos_inventario_materiales', [
            'folio_id' => $folioId,
            'item_material_id' => $itemAnterior->id,
            'tipo' => 'correccion_item_salida',
            'cantidad' => -12,
        ]);
        $this->assertDatabaseHas('movimientos_inventario_materiales', [
            'folio_id' => $folioId,
            'item_material_id' => $itemNuevo->id,
            'tipo' => 'correccion_item_entrada',
            'cantidad' => 12,
        ]);
        $this->assertSame(1, CorreccionItemFolioMaterial::query()->count());
        $this->assertSame(3, MovimientoInventarioMaterial::query()->where('folio_id', $folioId)->count());

        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/inventario/{$folioId}/corregir-item", [
                'operacion_id' => (string) Str::uuid(),
                'item_material_id' => $itemAnterior->id,
                'motivo' => 'Intento no autorizado.',
            ])
            ->assertForbidden();
    }

    private function crearAdministrador(): array
    {
        $usuario = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $token = $usuario->createToken('oficina-test', ['oficina'])->plainTextToken;

        return [$usuario, $token];
    }

    private function crearOperador(): array
    {
        $usuario = User::factory()->create([
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-'.Str::upper(Str::random(6)),
            'nombre' => 'Tablet materiales',
            'activo' => true,
        ]);
        $token = $usuario
            ->crearTokenParaDispositivo($dispositivo, 'tablet-materiales')
            ->plainTextToken;

        return [$usuario, $dispositivo, $token];
    }

    private function crearCamareroFrio(): array
    {
        $usuario = User::factory()->create([
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-FRIO-'.Str::upper(Str::random(6)),
            'nombre' => 'Tablet frío',
            'activo' => true,
        ]);
        $token = $usuario
            ->crearTokenParaDispositivo($dispositivo, 'tablet-frio')
            ->plainTextToken;

        return [$usuario, $dispositivo, $token];
    }

    private function crearItem(User $usuario): ItemMaterial
    {
        return ItemMaterial::create([
            'cliente_material_id' => ClienteMaterial::query()->where('codigo', 'GENERAL')->firstOrFail()->id,
            'codigo' => 'FILM-01',
            'nombre' => 'Film stretch',
            'categoria' => 'Embalaje',
            'unidad_medida' => 'rollos',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }

    private function crearDestino(User $usuario): DestinoMaterial
    {
        return DestinoMaterial::create([
            'nombre' => 'Packing norte',
            'centro_costo' => 'CC-100',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }

    private function crearDespacho(
        string $token,
        ItemMaterial $item,
        DestinoMaterial $destino,
        float $cantidad,
    ): string {
        return $this->conToken($token)
            ->postJson('/api/materiales/despachos', [
                'operacion_id' => (string) Str::uuid(),
                'destino_material_id' => $destino->id,
                'items' => [[
                    'item_material_id' => $item->id,
                    'cantidad' => $cantidad,
                ]],
            ])
            ->assertCreated()
            ->json('data.id');
    }

    private function crearCamara(
        string $codigo,
        ContenidoCamara $contenido,
        int $posiciones = 1,
    ): array {
        $camara = Camara::create([
            'codigo' => $codigo,
            'nombre' => "Cámara {$codigo}",
            'contenido' => $contenido,
        ]);
        $resultado = [$camara];

        for ($numero = 1; $numero <= $posiciones; $numero++) {
            $resultado[] = Posicion::create([
                'camara_id' => $camara->id,
                'banda' => 1,
                'posicion' => $numero,
                'nivel' => 1,
                'etiqueta' => sprintf('B01-P%02d-N1', $numero),
            ]);
        }

        return $resultado;
    }

    private function abrirSesion(string $token, Camara $camara): string
    {
        return $this->conToken($token)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertCreated()
            ->json('data.id');
    }

    private function ubicarMaterial(
        string $token,
        Posicion $posicion,
        string $sesion,
        ItemMaterial $item,
        string $numeroFolio,
        int $version,
        float $cantidad,
        string $fecha,
    ): string {
        return $this->conToken($token)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicion,
                $sesion,
                $item,
                $numeroFolio,
                $version,
                $cantidad,
                $fecha,
            ))
            ->assertOk()
            ->json('data.folio.id');
    }

    private function payloadUbicacion(
        Posicion $posicion,
        string $sesion,
        ItemMaterial $item,
        string $numeroFolio,
        int $version,
        float $cantidad,
        ?string $fecha = null,
    ): array {
        return [
            'operacion_id' => (string) Str::uuid(),
            'numero_folio' => $numeroFolio,
            'tipo_bulto' => 'material',
            'posicion_destino_id' => $posicion->id,
            'sesion_destino_id' => $sesion,
            'version_destino_conocida' => $version,
            'generado_dispositivo_at' => $fecha ?? now()->toAtomString(),
            'datos_material' => [
                'item_material_id' => $item->id,
                'cantidad' => $cantidad,
                'lote' => 'L-2026-07',
                'proveedor' => 'Proveedor de prueba',
            ],
        ];
    }

    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
