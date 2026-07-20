<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemMaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente' => $this->whenLoaded('cliente', fn () => [
                'id' => $this->cliente->id,
                'temporada' => [
                    'id' => $this->cliente->temporada->id,
                    'codigo' => $this->cliente->temporada->codigo,
                    'nombre' => $this->cliente->temporada->nombre,
                    'activa' => $this->cliente->temporada->activa,
                ],
                'codigo' => $this->cliente->codigo,
                'nombre' => $this->cliente->nombre,
                'activo' => $this->cliente->activo,
            ]),
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'categoria' => $this->categoria,
            'unidad_medida' => $this->unidad_medida,
            'codigo_externo' => $this->codigo_externo,
            'origen_sistema' => $this->origen_sistema,
            'sincronizado_at' => $this->sincronizado_at?->toAtomString(),
            'activo' => $this->activo,
            'folios_activos' => (int) ($this->folios_activos_count ?? 0),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
