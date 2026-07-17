<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidenciaCargaFolioResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'carga_folio_id' => $this->carga_folio_id,
            'carga' => $this->whenLoaded('asignacion', fn () => $this->asignacion?->relationLoaded('carga') ? [
                'id' => $this->asignacion->carga?->id,
                'codigo' => $this->asignacion->carga?->codigo,
                'numero_orden_externa' => $this->asignacion->carga?->numero_orden_externa,
                'prioridad' => $this->asignacion->carga?->prioridad?->value,
                'estado' => $this->asignacion->carga?->estado?->value,
            ] : null),
            'folio' => $this->whenLoaded('asignacion', fn () => $this->asignacion?->relationLoaded('folio') ? [
                'id' => $this->asignacion->folio?->id,
                'numero_folio' => $this->asignacion->folio?->numero_folio,
                'tipo_bulto' => $this->asignacion->folio?->tipo_bulto?->value,
                'variedad' => $this->asignacion->folio?->variedad,
                'calibre' => $this->asignacion->folio?->calibre,
                'marca' => $this->asignacion->folio?->marca,
                'exportadora' => $this->asignacion->folio?->exportadora,
            ] : null),
            'tipo' => $this->tipo->value,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado->value,
            'ubicacion_reportada' => [
                'camara' => $this->whenLoaded('camara', fn () => [
                    'id' => $this->camara->id,
                    'codigo' => $this->camara->codigo,
                    'nombre' => $this->camara->nombre,
                ]),
                'posicion' => $this->whenLoaded('posicion', fn () => [
                    'id' => $this->posicion->id,
                    'etiqueta' => $this->posicion->etiqueta,
                    'banda' => $this->posicion->banda,
                    'posicion' => $this->posicion->posicion,
                    'nivel' => $this->posicion->nivel,
                ]),
            ],
            'reportado_por' => $this->whenLoaded('reportadoPor', fn () => [
                'id' => $this->reportadoPor->id,
                'nombre' => $this->reportadoPor->name,
            ]),
            'dispositivo' => $this->whenLoaded('dispositivo', fn () => [
                'id' => $this->dispositivo->id,
                'codigo' => $this->dispositivo->codigo,
                'nombre' => $this->dispositivo->nombre,
            ]),
            'reportada_at' => $this->reportada_at?->toAtomString(),
            'tipo_resolucion' => $this->tipo_resolucion?->value,
            'observacion_resolucion' => $this->observacion_resolucion,
            'resuelta_por' => $this->whenLoaded('resueltaPor', fn () => $this->resueltaPor ? [
                'id' => $this->resueltaPor->id,
                'nombre' => $this->resueltaPor->name,
            ] : null),
            'resuelta_at' => $this->resuelta_at?->toAtomString(),
            'folio_reemplazo' => $this->whenLoaded('asignacionReemplazo', fn () => $this->asignacionReemplazo?->relationLoaded('folio') ? [
                'id' => $this->asignacionReemplazo->folio?->id,
                'numero_folio' => $this->asignacionReemplazo->folio?->numero_folio,
            ] : null),
        ];
    }
}
