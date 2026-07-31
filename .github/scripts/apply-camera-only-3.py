from pathlib import Path


def read(path):
    return Path(path).read_text(encoding='utf-8')


def write(path, content):
    target = Path(path)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding='utf-8')


def replace(path, old, new):
    text = read(path)
    if old not in text:
        raise RuntimeError(f'No se encontró patrón en {path}: {old[:100]!r}')
    write(path, text.replace(old, new, 1))


replace(
    'app/Http/Controllers/Api/CamaraController.php',
    "            ->with($this->relacionesBloqueo())\n",
    "            ->withCount('ubicacionesSinPosicion')\n            ->with($this->relacionesBloqueo())\n",
)
replace(
    'app/Http/Controllers/Api/CamaraController.php',
    "        $camara->loadMissing($this->relacionesBloqueo());\n",
    "        $camara->loadCount('ubicacionesSinPosicion');\n        $camara->loadMissing($this->relacionesBloqueo());\n",
)
replace(
    'app/Http/Controllers/Api/CamaraController.php',
    """        $camara->load([
            'posiciones' => fn ($consulta) => $consulta
""",
    """        $camara->load([
            'ubicacionesSinPosicion' => fn ($consulta) => $consulta
                ->with([
                    'folio.condicionSag',
                    'folio.material.item.cliente.temporada',
                    'folio.asignacionCargaActual.carga',
                ])
                ->orderBy('ubicado_at')
                ->orderBy('folio_id'),
            'posiciones' => fn ($consulta) => $consulta
""",
)
replace(
    'app/Http/Resources/CamaraResumenResource.php',
    """            'ocupacion' => [
                'ocupadas' => $ocupadas,
                'total' => $total,
""",
    """            'ocupacion' => [
                'ocupadas' => $ocupadas,
                'sin_posicion' => (int) ($this->ubicaciones_sin_posicion_count ?? 0),
                'total' => $total,
""",
)
replace(
    'app/Http/Resources/CamaraPlanoResource.php',
    """            'posiciones' => PosicionPlanoResource::collection(
                $this->whenLoaded('posiciones'),
            ),
""",
    """            'folios_sin_posicion' => FolioSinPosicionResource::collection(
                $this->whenLoaded('ubicacionesSinPosicion'),
            ),
            'posiciones' => PosicionPlanoResource::collection(
                $this->whenLoaded('posiciones'),
            ),
""",
)
write('app/Http/Resources/FolioSinPosicionResource.php', '''<?php

namespace App\\Http\\Resources;

use App\\Enums\\EstadoCarga;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\JsonResource;

class FolioSinPosicionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $folio = $this->folio;
        $asignacionCarga = $folio?->relationLoaded('asignacionCargaActual')
            ? $folio->asignacionCargaActual
            : null;
        $carga = $asignacionCarga?->relationLoaded('carga')
            ? $asignacionCarga->carga
            : null;

        if ($carga && ! in_array($carga->estado, EstadoCarga::visiblesEnOperacion(), true)) {
            $carga = null;
        }

        return [
            'id' => $folio->id,
            'numero_folio' => $folio->numero_folio,
            'tipo_bulto' => $folio->tipo_bulto->value,
            'estado_operacional' => $folio->estado_operacional->value,
            'condicion_sag' => $folio->condicionSag ? [
                'id' => $folio->condicionSag->id,
                'codigo' => $folio->condicionSag->codigo,
                'nombre' => $folio->condicionSag->nombre,
            ] : null,
            'fecha_ingreso' => $folio->fecha_ingreso?->toAtomString(),
            'variedad' => $folio->variedad,
            'calibre' => $folio->calibre,
            'marca' => $folio->marca,
            'exportadora' => $folio->exportadora,
            'material' => $folio->material ? [
                'item' => [
                    'id' => $folio->material->item->id,
                    'cliente' => [
                        'id' => $folio->material->item->cliente->id,
                        'temporada' => [
                            'id' => $folio->material->item->cliente->temporada->id,
                            'codigo' => $folio->material->item->cliente->temporada->codigo,
                            'nombre' => $folio->material->item->cliente->temporada->nombre,
                            'activa' => $folio->material->item->cliente->temporada->activa,
                        ],
                        'codigo' => $folio->material->item->cliente->codigo,
                        'nombre' => $folio->material->item->cliente->nombre,
                        'activo' => $folio->material->item->cliente->activo,
                    ],
                    'codigo' => $folio->material->item->codigo,
                    'nombre' => $folio->material->item->nombre,
                    'categoria' => $folio->material->item->categoria,
                ],
                'cantidad_inicial' => $folio->material->cantidad_inicial,
                'cantidad_actual' => $folio->material->cantidad_actual,
                'cantidad_reservada' => $folio->material->cantidad_reservada,
                'cantidad_disponible' => number_format(
                    max(0, (float) $folio->material->cantidad_actual - (float) $folio->material->cantidad_reservada),
                    3,
                    '.',
                    '',
                ),
                'unidad_medida' => $folio->material->unidad_medida,
                'lote' => $folio->material->lote,
                'proveedor' => $folio->material->proveedor,
                'observacion' => $folio->material->observacion,
            ] : null,
            'ubicado_at' => $this->ubicado_at?->toAtomString(),
            'carga_actual' => $carga ? [
                'id' => $carga->id,
                'codigo' => $carga->codigo,
                'estado' => $carga->estado->value,
                'prioridad' => $carga->prioridad->value,
                'version' => $carga->version,
            ] : null,
        ];
    }
}
''')

replace(
    'tests/Feature/Api/MaterialesApiTest.php',
    "            'tipo_bulto' => 'material',\n            'posicion_destino_id' => $posicion->id,\n",
    "            'tipo_bulto' => 'material',\n            'camara_destino_id' => $posicion->camara_id,\n            'posicion_destino_id' => $posicion->id,\n",
)
test = '''    public function test_material_es_reservable_y_despachable_asignando_solo_camara(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();
        $item = $this->crearItem($administrador);
        $destino = $this->crearDestino($administrador);
        [$camara, $posicion] = $this->crearCamara(
            'MAT-SOLO-CAMARA',
            ContenidoCamara::Materiales,
        );
        $sesion = $this->abrirSesion($tokenTablet, $camara);
        $folioId = $this->crearFolioMaterialPendiente(
            $item,
            'MAT-SIN-POSICION',
            10,
            now()->toAtomString(),
        );

        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', [
                'operacion_id' => (string) Str::uuid(),
                'numero_folio' => 'MAT-SIN-POSICION',
                'tipo_bulto' => 'material',
                'camara_destino_id' => $camara->id,
                'posicion_destino_id' => null,
                'sesion_destino_id' => $sesion,
                'version_destino_conocida' => 0,
                'generado_dispositivo_at' => now()->toAtomString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.destino.camara.id', $camara->id)
            ->assertJsonPath('data.destino.posicion', null);

        $this->assertDatabaseHas('ubicaciones_actuales', [
            'folio_id' => $folioId,
            'camara_id' => $camara->id,
            'posicion_id' => null,
        ]);
        $this->assertDatabaseHas('folios', [
            'id' => $folioId,
            'estado_operacional' => 'disponible',
        ]);

        $this->conToken($tokenOficina)
            ->getJson('/api/materiales/inventario?q=MAT-SIN-POSICION')
            ->assertOk()
            ->assertJsonPath('data.0.estado_ubicacion', 'solo_camara')
            ->assertJsonPath('data.0.reservable', true)
            ->assertJsonPath('data.0.cantidad_disponible', '10.000')
            ->assertJsonPath('data.0.camara.id', $camara->id)
            ->assertJsonPath('data.0.posicion', null);

        $this->conToken($tokenTablet)
            ->getJson("/api/camaras/{$camara->id}/plano")
            ->assertOk()
            ->assertJsonPath('data.ocupacion.sin_posicion', 1)
            ->assertJsonPath('data.folios_sin_posicion.0.id', $folioId);

        $despachoId = $this->conToken($tokenOficina)
            ->postJson('/api/materiales/despachos', [
                'operacion_id' => (string) Str::uuid(),
                'destino_material_id' => $destino->id,
                'items' => [[
                    'item_material_id' => $item->id,
                    'cantidad' => 4,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.sugerencias_fifo.0.camara.id', $camara->id)
            ->assertJsonPath('data.items.0.sugerencias_fifo.0.posicion', null)
            ->json('data.id');

        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/despachos/{$despachoId}/retirar", [
                'operacion_id' => (string) Str::uuid(),
                'retiros' => [[
                    'folio_id' => $folioId,
                    'cantidad' => 4,
                    'sesion_estiba_id' => $sesion,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'completado')
            ->assertJsonPath('data.items.0.retiros.0.posicion', null);

        $this->assertDatabaseHas('retiros_materiales', [
            'folio_id' => $folioId,
            'camara_id' => $camara->id,
            'posicion_id' => null,
            'cantidad_retirada' => 4,
        ]);

        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', [
                'operacion_id' => (string) Str::uuid(),
                'numero_folio' => 'MAT-SIN-POSICION',
                'tipo_bulto' => 'material',
                'camara_destino_id' => $camara->id,
                'posicion_destino_id' => $posicion->id,
                'sesion_destino_id' => $sesion,
                'version_destino_conocida' => 1,
                'generado_dispositivo_at' => now()->toAtomString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.tipo_movimiento', 'reubicacion')
            ->assertJsonPath('data.destino.posicion.id', $posicion->id);

        $this->assertDatabaseHas('ubicaciones_actuales', [
            'folio_id' => $folioId,
            'camara_id' => $camara->id,
            'posicion_id' => $posicion->id,
        ]);
    }

'''
replace(
    'tests/Feature/Api/MaterialesApiTest.php',
    '    private function crearAdministrador(): array\n',
    test + '    private function crearAdministrador(): array\n',
)

print('Bloque 3 aplicado')
