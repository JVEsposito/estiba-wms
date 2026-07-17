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
            'tipo' => $this->tipo->value,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado->value,
            'camara_id' => $this->camara_id,
            'posicion_id' => $this->posicion_id,
            'reportado_por_user_id' => $this->reportado_por_user_id,
            'dispositivo_id' => $this->dispositivo_id,
            'reportada_at' => $this->reportada_at?->toAtomString(),
            'tipo_resolucion' => $this->tipo_resolucion?->value,
            'observacion_resolucion' => $this->observacion_resolucion,
            'resuelta_por_user_id' => $this->resuelta_por_user_id,
            'resuelta_at' => $this->resuelta_at?->toAtomString(),
            'carga_folio_reemplazo_id' => $this->carga_folio_reemplazo_id,
        ];
    }
}
