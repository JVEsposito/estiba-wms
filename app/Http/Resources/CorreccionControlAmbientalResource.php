<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorreccionControlAmbientalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operacion_id' => $this->operacion_id,
            'motivo' => $this->motivo,
            'datos_anteriores' => $this->datos_anteriores,
            'datos_nuevos' => $this->datos_nuevos,
            'corregido_por' => $this->whenLoaded('corregidoPor', fn () => [
                'id' => $this->corregidoPor->id,
                'nombre' => $this->corregidoPor->name,
            ]),
            'corregido_at' => $this->corregido_at?->toAtomString(),
        ];
    }
}
