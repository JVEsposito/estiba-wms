<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemporadaMaterialResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'temporada_id' => $this->temporada_id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'fecha_inicio' => $this->fecha_inicio?->toDateString(),
            'fecha_fin' => $this->fecha_fin?->toDateString(),
            'activa' => $this->activa,
            'clientes_activos' => (int) ($this->clientes_activos_count ?? 0),
            'items_activos' => (int) ($this->items_activos_count ?? 0),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
