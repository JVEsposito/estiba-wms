<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecetaMaterialResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'activa' => $this->activa,
            'temporada' => $this->whenLoaded('temporada', fn (): array => [
                'id' => $this->temporada->id,
                'codigo' => $this->temporada->codigo,
                'nombre' => $this->temporada->nombre,
                'activa' => $this->temporada->activa,
            ]),
            'cliente' => $this->whenLoaded('cliente', fn (): array => [
                'id' => $this->cliente->id,
                'codigo' => $this->cliente->codigo,
                'nombre' => $this->cliente->nombre,
            ]),
            'item_salida' => $this->whenLoaded('itemSalida', fn (): array => [
                'id' => $this->itemSalida->id,
                'codigo' => $this->itemSalida->codigo,
                'nombre' => $this->itemSalida->nombre,
                'categoria_operacional' => $this->itemSalida->categoria_operacional?->value,
                'unidad_medida' => $this->itemSalida->unidad_medida,
            ]),
            'versiones' => $this->whenLoaded('versiones', fn () => $this->versiones->map(
                fn ($version): array => [
                    'id' => $version->id,
                    'numero_version' => $version->numero_version,
                    'estado' => $version->estado->value,
                    'cantidad_base_salida' => $version->cantidad_base_salida,
                    'unidad_medida_salida' => $version->unidad_medida_salida,
                    'activado_at' => $version->activado_at?->toAtomString(),
                    'retirado_at' => $version->retirado_at?->toAtomString(),
                    'componentes' => $version->relationLoaded('detalles')
                        ? $version->detalles->map(fn ($detalle): array => [
                            'id' => $detalle->id,
                            'item' => [
                                'id' => $detalle->itemEntrada->id,
                                'codigo' => $detalle->itemEntrada->codigo,
                                'nombre' => $detalle->itemEntrada->nombre,
                                'categoria_operacional' => $detalle->itemEntrada->categoria_operacional?->value,
                            ],
                            'cantidad_estandar' => $detalle->cantidad_estandar,
                            'unidad_medida' => $detalle->unidad_medida,
                            'es_componente_principal' => $detalle->es_componente_principal,
                            'factor_conversion' => $detalle->factor_conversion,
                            'merma_estandar_porcentaje' => $detalle->merma_estandar_porcentaje,
                            'tolerancia_porcentaje' => $detalle->tolerancia_porcentaje,
                        ])->values()
                        : [],
                ],
            )->values()),
            'creado_por' => $this->whenLoaded('creadoPor', fn (): array => [
                'id' => $this->creadoPor->id,
                'nombre' => $this->creadoPor->name,
            ]),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
