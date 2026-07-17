<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TareaCargaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'carga_id' => $this->carga_id,
            'camara_origen' => $this->whenLoaded('camaraOrigen', fn () => [
                'id' => $this->camaraOrigen->id,
                'codigo' => $this->camaraOrigen->codigo,
                'nombre' => $this->camaraOrigen->nombre,
            ]),
            'responsable' => $this->whenLoaded('responsable', fn () => $this->responsable ? [
                'id' => $this->responsable->id,
                'nombre' => $this->responsable->name,
            ] : null),
            'estado' => $this->estado->value,
            'asumida_at' => $this->asumida_at?->toAtomString(),
            'completada_at' => $this->completada_at?->toAtomString(),
        ];
    }
}
