<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrdenTransformacionMaterialResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->estado->value,
            'version' => $this->version,
            'cantidad_planificada_salida' => $this->cantidad_planificada_salida,
            'cantidad_real_salida' => $this->cantidad_real_salida,
            'linea' => $this->linea,
            'turno' => $this->turno,
            'fecha_operacional' => $this->fecha_operacional?->toDateString(),
            'observacion' => $this->observacion,
            'motivo_cancelacion' => $this->motivo_cancelacion,
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
            'version_receta' => $this->whenLoaded('versionReceta', fn (): array => [
                'id' => $this->versionReceta->id,
                'numero_version' => $this->versionReceta->numero_version,
                'estado' => $this->versionReceta->estado->value,
                'receta' => [
                    'id' => $this->versionReceta->receta->id,
                    'nombre' => $this->versionReceta->receta->nombre,
                    'item_salida' => [
                        'id' => $this->versionReceta->receta->itemSalida->id,
                        'codigo' => $this->versionReceta->receta->itemSalida->codigo,
                        'nombre' => $this->versionReceta->receta->itemSalida->nombre,
                        'unidad_medida' => $this->versionReceta->receta->itemSalida->unidad_medida,
                    ],
                ],
            ]),
            'reservas' => $this->whenLoaded('reservas', fn () => $this->reservas->map(
                fn ($reserva): array => [
                    'id' => $reserva->id,
                    'estado' => $reserva->estado->value,
                    'cantidad' => $reserva->cantidad,
                    'orden_fifo' => $reserva->orden_fifo,
                    'item_material_id' => $reserva->item_material_id,
                    'folio' => $reserva->relationLoaded('folioMaterial') ? [
                        'id' => $reserva->folio_id,
                        'numero_folio' => $reserva->folioMaterial->folio->numero_folio,
                        'cantidad_actual' => $reserva->folioMaterial->cantidad_actual,
                        'cantidad_reservada' => $reserva->folioMaterial->cantidad_reservada,
                        'ubicacion' => $reserva->folioMaterial->folio->ubicacionActual?->posicion ? [
                            'camara' => $reserva->folioMaterial->folio->ubicacionActual->posicion->camara->codigo,
                            'posicion' => $reserva->folioMaterial->folio->ubicacionActual->posicion->etiqueta,
                        ] : null,
                    ] : null,
                ],
            )->values()),
            'eventos' => $this->whenLoaded('eventos', fn () => $this->eventos->map(
                fn ($evento): array => [
                    'id' => $evento->id,
                    'tipo' => $evento->tipo->value,
                    'datos' => $evento->datos,
                    'observacion' => $evento->observacion,
                    'usuario' => $evento->relationLoaded('usuario') ? [
                        'id' => $evento->usuario->id,
                        'nombre' => $evento->usuario->name,
                    ] : null,
                    'ocurrido_at' => $evento->ocurrido_at?->toAtomString(),
                ],
            )->values()),
            'creado_por' => $this->whenLoaded('creadoPor', fn (): array => [
                'id' => $this->creadoPor->id,
                'nombre' => $this->creadoPor->name,
            ]),
            'iniciado_at' => $this->iniciado_at?->toAtomString(),
            'cerrado_at' => $this->cerrado_at?->toAtomString(),
            'cancelado_at' => $this->cancelado_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
