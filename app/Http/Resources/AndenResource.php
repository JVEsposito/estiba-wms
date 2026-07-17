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
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
