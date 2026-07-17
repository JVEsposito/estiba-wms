<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class CargaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $asignaciones = $this->resource->relationLoaded('asignacionesActuales')
            ? $this->asignacionesActuales
            : collect();
        $folios = $this->transformarFolios($asignaciones);

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'numero_orden_externa' => $this->numero_orden_externa,
            'estado' => $this->estado->value,
            'prioridad' => $this->prioridad->value,
            'version' => $this->version,
            'observacion' => $this->observacion,
            'camara_objetivo' => $this->whenLoaded('camaraObjetivo', fn () => $this->camaraObjetivo ? [
                'id' => $this->camaraObjetivo->id,
                'codigo' => $this->camaraObjetivo->codigo,
                'nombre' => $this->camaraObjetivo->nombre,
            ] : null),
            'anden_previsto' => $this->whenLoaded('andenPrevisto', fn () => $this->andenPrevisto ? [
                'id' => $this->andenPrevisto->id,
                'codigo' => $this->andenPrevisto->codigo,
                'nombre' => $this->andenPrevisto->nombre,
            ] : null),
            'total_folios' => $folios->count(),
            'folios' => $folios,
            'distribucion' => $this->distribucion($folios),
            'creada_por' => $this->whenLoaded('creadaPor', fn () => [
                'id' => $this->creadaPor?->id,
                'nombre' => $this->creadaPor?->name,
            ]),
            'actualizada_por' => $this->whenLoaded('actualizadaPor', fn () => [
                'id' => $this->actualizadaPor?->id,
                'nombre' => $this->actualizadaPor?->name,
            ]),
            'publicada_por' => $this->whenLoaded('publicadaPor', fn () => $this->publicadaPor ? [
                'id' => $this->publicadaPor->id,
                'nombre' => $this->publicadaPor->name,
            ] : null),
            'cancelada_por' => $this->whenLoaded('canceladaPor', fn () => $this->canceladaPor ? [
                'id' => $this->canceladaPor->id,
                'nombre' => $this->canceladaPor->name,
            ] : null),
            'publicada_at' => $this->publicada_at?->toAtomString(),
            'cancelada_at' => $this->cancelada_at?->toAtomString(),
            'cierre' => $this->cerrada_at ? [
                'patente' => $this->patente,
                'conductor' => $this->conductor,
                'observacion' => $this->observacion_cierre,
                'cerrada_at' => $this->cerrada_at->toAtomString(),
                'cerrada_por' => $this->whenLoaded('cerradaPor', fn () => [
                    'id' => $this->cerradaPor?->id,
                    'nombre' => $this->cerradaPor?->name,
                ]),
            ] : null,
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $asignaciones
     * @return Collection<int, array<string, mixed>>
     */
    private function transformarFolios(Collection $asignaciones): Collection
    {
        return $asignaciones
            ->map(function ($asignacion): array {
                $folio = $asignacion->folio;
                $ubicacion = $folio?->ubicacionActual;
                $posicion = $ubicacion?->posicion;
                $camara = $posicion?->camara;

                return [
                    'asignacion_id' => $asignacion->id,
                    'id' => $folio?->id,
                    'numero_folio' => $folio?->numero_folio,
                    'tipo_bulto' => $folio?->tipo_bulto?->value,
                    'estado_operacional' => $folio?->estado_operacional?->value,
                    'estado_carga' => $asignacion->estado->value,
                    'anden' => $asignacion->relationLoaded('anden') && $asignacion->anden ? [
                        'id' => $asignacion->anden->id,
                        'codigo' => $asignacion->anden->codigo,
                        'nombre' => $asignacion->anden->nombre,
                    ] : null,
                    'asignado_at' => $asignacion->asignado_at?->toAtomString(),
                    'ubicacion' => $posicion && $camara ? [
                        'camara' => [
                            'id' => $camara->id,
                            'codigo' => $camara->codigo,
                            'nombre' => $camara->nombre,
                        ],
                        'posicion' => [
                            'id' => $posicion->id,
                            'banda' => $posicion->banda,
                            'posicion' => $posicion->posicion,
                            'nivel' => $posicion->nivel,
                            'etiqueta' => $posicion->etiqueta,
                        ],
                    ] : null,
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $folios
     * @return Collection<int, array<string, mixed>>
     */
    private function distribucion(Collection $folios): Collection
    {
        return $folios
            ->filter(fn (array $folio): bool => $folio['ubicacion'] !== null)
            ->groupBy(fn (array $folio): string => $folio['ubicacion']['camara']['id'])
            ->map(function (Collection $grupo): array {
                $primero = $grupo->first();

                return [
                    'camara' => $primero['ubicacion']['camara'],
                    'cantidad' => $grupo->count(),
                ];
            })
            ->values();
    }
}
