<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DestinoMaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo instanceof \BackedEnum ? $this->tipo->value : $this->tipo,
            'centro_costo' => $this->centro_costo,
            'requiere_ubicacion_fisica' => $this->requiere_ubicacion_fisica,
            'descripcion' => $this->descripcion,
            'codigo_externo' => $this->codigo_externo,
            'origen_sistema' => $this->origen_sistema,
            'sincronizado_at' => $this->sincronizado_at?->toAtomString(),
            'activo' => $this->activo,
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
