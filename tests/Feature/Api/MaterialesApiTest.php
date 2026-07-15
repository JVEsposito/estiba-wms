<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\DestinoMaterial;
use App\Models\Dispositivo;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\Posicion;
use App\Models\RetiroMaterial;
use App\Models\User;
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

        $itemId = $this->conToken($tokenOficina)
            ->postJson('/api/administracion/materiales/items', [
                'codigo' => '  film-01 ',
                'nombre' => ' Film stretch ',
                'categoria' => 'Embalaje',
                'unidad_medida' => 'ROLLOS',
            ])
            ->assertCreated()
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
            ->assertJsonPath('items.0.id', $itemId)
            ->assertJsonPath('destinos.0.id', $destinoId);

        $this->conToken($tokenTablet)
            ->postJson('/api/administracion/materiales/items', [
                'codigo' => 'NO-AUTORIZADO',
                'nombre' => 'No autorizado',
                'unidad_medida' => 'unidad',
            ])
            ->assertForbidden();

        $this->assertSame($administrador->id, ItemMaterial::findOrFail($itemId)->creado_por_user_id);
    }

    public function test_ubica_material_solo_en_su_tipo_de_camara_y_crea_kardex_de_ingreso(): void
    {
        [$administrador] = $this->crearAdministrador();
        [$operador, $dispositivo, $tokenTablet] = $this->crearOperador();
        $item = $this->crearItem($administrador);
        [$camaraMaterial, $posicionMaterial] = $this->crearCamara('CAM-01', ContenidoCamara::Materiales);
        [$camaraProducto, $posicionProducto] = $this->crearCamara('CAM-02', ContenidoCamara::Productos);
        $sesionMaterial = $this->abrirSesion($tokenTablet, $camaraMaterial);
        $sesionProducto = $this->abrirSesion($tokenTablet, $camaraProducto);

        $this->conToken($tokenTablet)
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
        [, , $tokenTablet] = $this->crearOperador();
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
            ->assertJsonPath('data.items.0.cantidad_pendiente', '8.000');

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
            'rol' => RolUsuario::Supervisor,
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
            'rol' => RolUsuario::Operador,
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

    private function crearItem(User $usuario): ItemMaterial
    {
        return ItemMaterial::create([
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
