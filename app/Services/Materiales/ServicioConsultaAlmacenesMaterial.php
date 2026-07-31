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
