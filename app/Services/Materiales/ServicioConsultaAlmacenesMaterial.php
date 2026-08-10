<?php

namespace App\Services\Materiales;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoPosicion;
use App\Enums\TipoAlmacenMaterial;
use App\Models\AlmacenMaterial;
use App\Models\MovimientoAlmacenMaterial;
use App\Models\SaldoMaterialAlmacen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ServicioConsultaAlmacenesMaterial
{
    public function existencias(): array
    {
        $saldos = SaldoMaterialAlmacen::query()
            ->with([
                'almacen',
                'camara',
                'posicion',
                'folioMaterial.folio',
                'folioMaterial.item.cliente.temporada',
                'folioMaterial.item.cliente.cliente',
            ])
            ->where('cantidad_actual', '>', 0)
            ->whereHas('almacen', fn (Builder $almacenes) => $almacenes
                ->where('activo', true))
            ->whereHas('folioMaterial.folio', fn (Builder $folios) => $folios
                ->where('activo', true)
                ->whereHas('temporada', fn (Builder $temporadas) => $temporadas
                    ->where('activa', true)))
            ->orderBy('almacen_material_id')
            ->orderBy('folio_id')
            ->get();

        $bodega = $saldos
            ->filter(fn (SaldoMaterialAlmacen $saldo): bool => $saldo->almacen->tipo === TipoAlmacenMaterial::Fisica)
            ->map(fn (SaldoMaterialAlmacen $saldo): array => $this->filaSaldo($saldo))
            ->values();
        $centros = $saldos
            ->filter(fn (SaldoMaterialAlmacen $saldo): bool => $saldo->almacen->tipo === TipoAlmacenMaterial::Virtual)
            ->map(fn (SaldoMaterialAlmacen $saldo): array => $this->filaSaldo($saldo))
            ->values();
        $totalEmpresa = $saldos
            ->groupBy(fn (SaldoMaterialAlmacen $saldo): string => $saldo->folioMaterial->item_material_id)
            ->map(fn (Collection $grupo): array => $this->totalItem($grupo))
            ->sortBy(fn (array $fila): string => sprintf(
                '%s-%s',
                $fila['cliente']['codigo'],
                $fila['item']['codigo'],
            ))
            ->values();

        return [
            'almacenes' => AlmacenMaterial::query()
                ->where('activo', true)
                ->orderBy('tipo')
                ->orderBy('nombre')
                ->get()
                ->map(fn (AlmacenMaterial $almacen): array => [
                    'id' => $almacen->id,
                    'codigo' => $almacen->codigo,
                    'nombre' => $almacen->nombre,
                    'tipo' => $almacen->tipo->value,
                    'centro_costo' => $almacen->centro_costo,
                    'requiere_ubicacion_fisica' => $almacen->requiere_ubicacion_fisica,
                ])
                ->values(),
            'perspectivas' => [
                'bodega' => $bodega,
                'centros_costo' => $centros,
                'total_empresa' => $totalEmpresa,
            ],
            'resumen' => [
                'folios' => $saldos->pluck('folio_id')->unique()->count(),
                'almacenes' => $saldos->pluck('almacen_material_id')->unique()->count(),
                'items' => $totalEmpresa->count(),
            ],
        ];
    }

    /**
     * @param  array{q?:string|null,cliente_id?:string|null,item_id?:string|null,almacen_id?:string|null,camara_id?:string|null}  $filtros
     * @return array{titulo:string,archivo:string,columnas:array<int,array{clave:string,titulo:string,ancho:int,tipo?:string}>,filas:Collection<int,array<string,mixed>>}
     */
    public function exportacion(string $perspectiva, array $filtros): array
    {
        $existencias = $this->existencias();
        $filas = collect($existencias['perspectivas'][$perspectiva] ?? [])
            ->filter(fn (array $fila): bool => $this->coincideConFiltros($fila, $perspectiva, $filtros))
            ->values();

        if ($perspectiva === 'total_empresa') {
            return [
                'titulo' => 'Inventario CC · Existencia total empresa',
                'archivo' => 'Inventario_CC_Total_Empresa',
                'columnas' => [
                    ['clave' => 'cliente', 'titulo' => 'Cliente', 'ancho' => 28],
                    ['clave' => 'item', 'titulo' => 'Ítem', 'ancho' => 34],
                    ['clave' => 'unidad_medida', 'titulo' => 'Unidad', 'ancho' => 14],
                    ['clave' => 'en_bodega', 'titulo' => 'En Bodega Central', 'ancho' => 19, 'tipo' => 'numero'],
                    ['clave' => 'en_centros_costo', 'titulo' => 'En centros de costo', 'ancho' => 21, 'tipo' => 'numero'],
                    ['clave' => 'total_empresa', 'titulo' => 'Total empresa', 'ancho' => 18, 'tipo' => 'numero'],
                    ['clave' => 'folios', 'titulo' => 'Folios', 'ancho' => 12, 'tipo' => 'numero'],
                ],
                'filas' => $filas->map(fn (array $fila): array => [
                    'cliente' => $this->etiqueta($fila['cliente']),
                    'item' => $this->etiqueta($fila['item']),
                    'unidad_medida' => $fila['unidad_medida'],
                    'en_bodega' => $fila['en_bodega'],
                    'en_centros_costo' => $fila['en_centros_costo'],
                    'total_empresa' => $fila['total_empresa'],
                    'folios' => $fila['folios'],
                ]),
            ];
        }

        $esCentroCosto = $perspectiva === 'centros_costo';
        $columnas = [
            ['clave' => 'almacen', 'titulo' => $esCentroCosto ? 'Centro de costo / almacén' : 'Almacén', 'ancho' => 32],
            ['clave' => 'cliente', 'titulo' => 'Cliente', 'ancho' => 28],
            ['clave' => 'item', 'titulo' => 'Ítem', 'ancho' => 34],
            ['clave' => 'folio', 'titulo' => 'Folio', 'ancho' => 18],
            ['clave' => 'lote', 'titulo' => 'Lote', 'ancho' => 18],
            ['clave' => 'cantidad_actual', 'titulo' => 'Cantidad', 'ancho' => 15, 'tipo' => 'numero'],
            ['clave' => 'unidad_medida', 'titulo' => 'Unidad', 'ancho' => 14],
            ['clave' => 'cantidad_reservada', 'titulo' => 'Reservada', 'ancho' => 15, 'tipo' => 'numero'],
            ['clave' => 'cantidad_disponible', 'titulo' => 'Disponible', 'ancho' => 15, 'tipo' => 'numero'],
        ];
        if (! $esCentroCosto) {
            $columnas[] = ['clave' => 'camara', 'titulo' => 'Cámara', 'ancho' => 18];
            $columnas[] = ['clave' => 'posicion', 'titulo' => 'Posición', 'ancho' => 18];
        }
        $columnas[] = ['clave' => 'estado', 'titulo' => 'Estado', 'ancho' => 16];

        return [
            'titulo' => $esCentroCosto
                ? 'Inventario CC · Existencia en centros de costo'
                : 'Inventario CC · Existencia en Bodega Central',
            'archivo' => $esCentroCosto
                ? 'Inventario_CC_Centros_Costo'
                : 'Inventario_CC_Bodega_Central',
            'columnas' => $columnas,
            'filas' => $filas->map(function (array $fila) use ($esCentroCosto): array {
                $exportada = [
                    'almacen' => collect([
                        $fila['almacen']['codigo'] ?? null,
                        $fila['almacen']['nombre'] ?? null,
                        $fila['almacen']['centro_costo'] ?? null,
                    ])->filter()->implode(' · '),
                    'cliente' => $this->etiqueta($fila['cliente']),
                    'item' => $this->etiqueta($fila['item']),
                    'folio' => $fila['numero_folio'],
                    'lote' => $fila['lote'] ?: 'Sin lote',
                    'cantidad_actual' => $fila['cantidad_actual'],
                    'unidad_medida' => $fila['unidad_medida'],
                    'cantidad_reservada' => $fila['cantidad_reservada'],
                    'cantidad_disponible' => $fila['cantidad_disponible'],
                    'estado' => $fila['bloqueado'] ? 'Bloqueado' : 'Disponible',
                ];
                if (! $esCentroCosto) {
                    $exportada['camara'] = $this->etiqueta($fila['camara'] ?? []);
                    $exportada['posicion'] = $fila['posicion']['etiqueta'] ?? 'Sin posición';
                }

                return $exportada;
            }),
        ];
    }

    /** @param array<string, mixed> $fila */
    private function coincideConFiltros(array $fila, string $perspectiva, array $filtros): bool
    {
        foreach (['cliente_id' => 'cliente', 'item_id' => 'item', 'almacen_id' => 'almacen', 'camara_id' => 'camara'] as $filtro => $relacion) {
            $valor = $filtros[$filtro] ?? null;
            if ($valor && ($fila[$relacion]['id'] ?? null) !== $valor) {
                return false;
            }
        }

        $busqueda = $this->normalizar((string) ($filtros['q'] ?? ''));
        if ($busqueda === '') {
            return true;
        }

        $valores = $perspectiva === 'total_empresa'
            ? [
                ...array_values($fila['cliente'] ?? []),
                ...array_values($fila['item'] ?? []),
                $fila['unidad_medida'] ?? null,
                $fila['en_bodega'] ?? null,
                $this->cantidadBuscable($fila['en_bodega'] ?? 0),
                $fila['en_centros_costo'] ?? null,
                $this->cantidadBuscable($fila['en_centros_costo'] ?? 0),
                $fila['total_empresa'] ?? null,
                $this->cantidadBuscable($fila['total_empresa'] ?? 0),
                $fila['folios'] ?? null,
            ]
            : [
                ...array_values($fila['almacen'] ?? []),
                ...array_values($fila['cliente'] ?? []),
                ...array_values($fila['item'] ?? []),
                $fila['numero_folio'] ?? null,
                $fila['lote'] ?? null,
                $fila['cantidad_actual'] ?? null,
                $this->cantidadBuscable($fila['cantidad_actual'] ?? 0),
                $fila['cantidad_reservada'] ?? null,
                $this->cantidadBuscable($fila['cantidad_reservada'] ?? 0),
                $fila['cantidad_disponible'] ?? null,
                $this->cantidadBuscable($fila['cantidad_disponible'] ?? 0),
                $fila['unidad_medida'] ?? null,
                ...array_values($fila['camara'] ?? []),
                ...array_values($fila['posicion'] ?? []),
                ($fila['bloqueado'] ?? false) ? 'bloqueado' : 'disponible',
            ];

        return str_contains($this->normalizar(implode(' ', array_filter($valores, fn ($valor) => $valor !== null))), $busqueda);
    }

    /** @param array<string, mixed> $registro */
    private function etiqueta(array $registro): string
    {
        return collect([
            $registro['codigo'] ?? null,
            $registro['nombre'] ?? null,
        ])->filter()->implode(' · ');
    }

    private function normalizar(string $valor): string
    {
        return Str::lower(Str::ascii(trim($valor)));
    }

    private function cantidadBuscable(mixed $valor): string
    {
        return number_format((float) $valor, 3, ',', '.');
    }

    public function kardex(int $limite = 250): Collection
    {
        return MovimientoAlmacenMaterial::query()
            ->with([
                'folioMaterial.folio:id,numero_folio',
                'item.cliente.temporada',
                'almacenOrigen:id,codigo,nombre,tipo,centro_costo',
                'almacenDestino:id,codigo,nombre,tipo,centro_costo',
                'usuario:id,name',
                'dispositivo:id,codigo,nombre',
            ])
            ->whereHas('folioMaterial.folio', fn (Builder $folios) => $folios
                ->whereHas('temporada', fn (Builder $temporadas) => $temporadas
                    ->where('activa', true)))
            ->latest('ocurrido_at')
            ->limit($limite)
            ->get()
            ->map(fn (MovimientoAlmacenMaterial $movimiento): array => [
                'id' => $movimiento->id,
                'operacion_id' => $movimiento->operacion_id,
                'tipo' => $movimiento->tipo->value,
                'folio' => [
                    'id' => $movimiento->folio_id,
                    'numero_folio' => $movimiento->folioMaterial->folio->numero_folio,
                ],
                'item' => [
                    'id' => $movimiento->item->id,
                    'codigo' => $movimiento->item->codigo,
                    'nombre' => $movimiento->item->nombre,
                ],
                'almacen_origen' => $this->almacenMovimiento($movimiento->almacenOrigen),
                'almacen_destino' => $this->almacenMovimiento($movimiento->almacenDestino),
                'cantidad' => $movimiento->cantidad,
                'saldo_origen_anterior' => $movimiento->saldo_origen_anterior,
                'saldo_origen_resultante' => $movimiento->saldo_origen_resultante,
                'saldo_destino_anterior' => $movimiento->saldo_destino_anterior,
                'saldo_destino_resultante' => $movimiento->saldo_destino_resultante,
                'centro_costo' => $movimiento->centro_costo,
                'motivo' => $movimiento->motivo,
                'documento_relacionado' => $movimiento->documento_relacionado,
                'usuario' => $movimiento->usuario?->name,
                'dispositivo' => $movimiento->dispositivo?->codigo,
                'ocurrido_at' => $movimiento->ocurrido_at?->toAtomString(),
            ]);
    }

    private function filaSaldo(SaldoMaterialAlmacen $saldo): array
    {
        $material = $saldo->folioMaterial;
        $folio = $material->folio;
        $item = $material->item;
        $disponible = $this->disponible($saldo);

        return [
            'saldo_id' => $saldo->id,
            'folio_id' => $folio->id,
            'numero_folio' => $folio->numero_folio,
            'lote' => $material->lote,
            'fecha_ingreso' => $folio->fecha_ingreso?->toAtomString(),
            'cliente' => [
                'id' => $item->cliente->id,
                'codigo' => $item->cliente->codigo,
                'nombre' => $item->cliente->nombre,
            ],
            'item' => [
                'id' => $item->id,
                'codigo' => $item->codigo,
                'nombre' => $item->nombre,
            ],
            'almacen' => [
                'id' => $saldo->almacen->id,
                'codigo' => $saldo->almacen->codigo,
                'nombre' => $saldo->almacen->nombre,
                'tipo' => $saldo->almacen->tipo->value,
                'centro_costo' => $saldo->almacen->centro_costo,
            ],
            'cantidad_actual' => $this->cantidad($saldo->cantidad_actual),
            'cantidad_reservada' => $this->cantidad($saldo->cantidad_reservada),
            'cantidad_disponible' => $this->cantidad($disponible),
            'unidad_medida' => $material->unidad_medida,
            'camara' => $saldo->camara ? [
                'id' => $saldo->camara->id,
                'codigo' => $saldo->camara->codigo,
                'nombre' => $saldo->camara->nombre,
            ] : null,
            'posicion' => $saldo->posicion ? [
                'id' => $saldo->posicion->id,
                'etiqueta' => $saldo->posicion->etiqueta,
            ] : null,
            'bloqueado' => $material->motivo_bloqueo !== null,
        ];
    }

    /**
     * @param  Collection<int, SaldoMaterialAlmacen>  $grupo
     */
    private function totalItem(Collection $grupo): array
    {
        $primero = $grupo->first();
        $item = $primero->folioMaterial->item;
        $bodega = $grupo
            ->filter(fn (SaldoMaterialAlmacen $saldo): bool => $saldo->almacen->tipo === TipoAlmacenMaterial::Fisica)
            ->sum('cantidad_actual');
        $centros = $grupo
            ->filter(fn (SaldoMaterialAlmacen $saldo): bool => $saldo->almacen->tipo === TipoAlmacenMaterial::Virtual)
            ->sum('cantidad_actual');

        return [
            'cliente' => [
                'id' => $item->cliente->id,
                'codigo' => $item->cliente->codigo,
                'nombre' => $item->cliente->nombre,
            ],
            'item' => [
                'id' => $item->id,
                'codigo' => $item->codigo,
                'nombre' => $item->nombre,
            ],
            'unidad_medida' => $primero->folioMaterial->unidad_medida,
            'en_bodega' => $this->cantidad($bodega),
            'en_centros_costo' => $this->cantidad($centros),
            'total_empresa' => $this->cantidad($bodega + $centros),
            'folios' => $grupo->pluck('folio_id')->unique()->count(),
        ];
    }

    private function disponible(SaldoMaterialAlmacen $saldo): float
    {
        $material = $saldo->folioMaterial;

        if ($material->motivo_bloqueo !== null
            || $material->folio->estado_operacional !== EstadoOperacionalFolio::Disponible
            || ! $saldo->almacen->activo) {
            return 0;
        }

        if ($saldo->almacen->tipo === TipoAlmacenMaterial::Fisica
            && ($saldo->camara?->contenido !== ContenidoCamara::Materiales
                || $saldo->camara?->estado !== EstadoCamara::Activa
                || ($saldo->posicion
                    && $saldo->posicion->estado !== EstadoPosicion::Activa))) {
            return 0;
        }

        return max(
            0,
            round(
                (float) $saldo->cantidad_actual - (float) $saldo->cantidad_reservada,
                3,
            ),
        );
    }

    private function almacenMovimiento(?AlmacenMaterial $almacen): ?array
    {
        return $almacen ? [
            'id' => $almacen->id,
            'codigo' => $almacen->codigo,
            'nombre' => $almacen->nombre,
            'tipo' => $almacen->tipo->value,
            'centro_costo' => $almacen->centro_costo,
        ] : null;
    }

    private function cantidad(mixed $valor): string
    {
        return number_format((float) $valor, 3, '.', '');
    }
}
