<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AndenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'codigo_externo' => $this->codigo_externo,
            'activo' => $this->activo,
            'ocupacion' => $this->whenLoaded('presenciaActiva', fn () => $this->presenciaActiva ? [
                'presencia_id' => $this->presenciaActiva->id,
                'patente' => $this->presenciaActiva->patente,
                'carga' => $this->presenciaActiva->relationLoaded('carga') ? [
                    'id' => $this->presenciaActiva->carga->id,
                    'codigo' => $this->presenciaActiva->carga->codigo,
                ] : null,
                'ingresada_at' => $this->presenciaActiva->ingresada_at?->toAtomString(),
            ] : null),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
