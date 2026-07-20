<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DespachoMaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'origen' => $this->origen->value,
            'estado' => $this->estado->value,
            'destino' => [
                'id' => $this->destino_material_id,
                'nombre' => $this->destino_nombre,
                'centro_costo' => $this->destino_centro_costo,
            ],
            'observacion' => $this->observacion,
            'creado_por' => $this->whenLoaded('creadoPor', fn () => [
                'id' => $this->creadoPor->id,
                'nombre' => $this->creadoPor->name,
            ]),
            'dispositivo' => $this->whenLoaded('dispositivo', fn () => $this->dispositivo ? [
                'id' => $this->dispositivo->id,
                'codigo' => $this->dispositivo->codigo,
                'nombre' => $this->dispositivo->nombre,
            ] : null),
            'items' => $this->whenLoaded('detalles', fn () => $this->detalles->map(
                function ($detalle): array {
                    $reservado = $detalle->relationLoaded('reservas')
                        ? $detalle->reservas->sum(fn ($reserva) => (float) $reserva->cantidad)
                        : 0;

                    return [
                        'detalle_id' => $detalle->id,
                        'item' => [
                            'id' => $detalle->item->id,
                            'cliente' => [
                                'id' => $detalle->item->cliente->id,
                                'temporada' => [
                                    'id' => $detalle->item->cliente->temporada->id,
                                    'codigo' => $detalle->item->cliente->temporada->codigo,
                                    'nombre' => $detalle->item->cliente->temporada->nombre,
                                    'activa' => $detalle->item->cliente->temporada->activa,
                                ],
                                'codigo' => $detalle->item->cliente->codigo,
                                'nombre' => $detalle->item->cliente->nombre,
                                'activo' => $detalle->item->cliente->activo,
                            ],
                            'codigo' => $detalle->item->codigo,
                            'nombre' => $detalle->item->nombre,
                            'categoria' => $detalle->item->categoria,
                        ],
                        'cantidad_solicitada' => $detalle->cantidad_solicitada,
                        'cantidad_despachada' => $detalle->cantidad_despachada,
                        'cantidad_pendiente' => number_format(max(
                            0,
                            (float) $detalle->cantidad_solicitada
                                - (float) $detalle->cantidad_despachada,
                        ), 3, '.', ''),
                        'cantidad_reservada' => number_format($reservado, 3, '.', ''),
                        'unidad_medida' => $detalle->unidad_medida,
                        'sugerencias_fifo' => $detalle->relationLoaded('reservas')
                            ? $detalle->reservas->map(function ($reserva): array {
                                $folio = $reserva->folioMaterial->folio;
                                $posicion = $folio->ubicacionActual?->posicion;

                                return [
                                    'folio_id' => $folio->id,
                                    'numero_folio' => $folio->numero_folio,
                                    'cantidad' => $reserva->cantidad,
                                    'camara' => $posicion?->camara ? [
                                        'id' => $posicion->camara->id,
                                        'codigo' => $posicion->camara->codigo,
                                        'nombre' => $posicion->camara->nombre,
                                    ] : null,
                                    'posicion' => $posicion ? [
                                        'id' => $posicion->id,
                                        'etiqueta' => $posicion->etiqueta,
                                    ] : null,
                                ];
                            })->values()
                            : [],
                        'retiros' => $detalle->relationLoaded('retiros')
                            ? $detalle->retiros
                                ->sortByDesc('retirado_at')
                                ->map(function ($retiro): array {
                                    $folio = $retiro->folioMaterial->folio;

                                    return [
                                        'id' => $retiro->id,
                                        'folio' => [
                                            'id' => $folio->id,
                                            'numero_folio' => $folio->numero_folio,
                                        ],
                                        'cantidad_anterior' => $retiro->cantidad_anterior,
                                        'cantidad_retirada' => $retiro->cantidad_retirada,
                                        'cantidad_resultante' => $retiro->cantidad_resultante,
                                        'camara' => $retiro->camara ? [
                                            'id' => $retiro->camara->id,
                                            'codigo' => $retiro->camara->codigo,
                                            'nombre' => $retiro->camara->nombre,
                                        ] : null,
                                        'posicion' => $retiro->posicion ? [
                                            'id' => $retiro->posicion->id,
                                            'etiqueta' => $retiro->posicion->etiqueta,
                                        ] : null,
                                        'usuario' => $retiro->usuario ? [
                                            'id' => $retiro->usuario->id,
                                            'nombre' => $retiro->usuario->name,
                                        ] : null,
                                        'dispositivo' => $retiro->dispositivo ? [
                                            'id' => $retiro->dispositivo->id,
                                            'codigo' => $retiro->dispositivo->codigo,
                                            'nombre' => $retiro->dispositivo->nombre,
                                        ] : null,
                                        'siguio_fifo' => $retiro->siguio_fifo,
                                        'retirado_at' => $retiro->retirado_at?->toAtomString(),
                                    ];
                                })->values()
                            : [],
                    ];
                },
            )),
            'completado_at' => $this->completado_at?->toAtomString(),
            'cancelacion' => $this->cancelado_at ? [
                'motivo' => $this->cancelacion_motivo,
                'usuario' => $this->whenLoaded('canceladoPor', fn () => $this->canceladoPor ? [
                    'id' => $this->canceladoPor->id,
                    'nombre' => $this->canceladoPor->name,
                ] : null),
                'dispositivo' => $this->whenLoaded('dispositivoCancelacion', fn () => $this->dispositivoCancelacion ? [
                    'id' => $this->dispositivoCancelacion->id,
                    'codigo' => $this->dispositivoCancelacion->codigo,
                    'nombre' => $this->dispositivoCancelacion->nombre,
                ] : null),
                'cancelado_at' => $this->cancelado_at->toAtomString(),
            ] : null,
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
