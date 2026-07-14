<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CamaraConfiguracionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $total = (int) ($this->posiciones_count ?? 0);
        $activas = (int) ($this->posiciones_activas_count ?? 0);

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'estado' => $this->estado->value,
            'version_plano' => $this->version_plano,
            'dimensiones' => [
                'bandas' => (int) ($this->posiciones_max_banda ?? 0),
                'posiciones_por_banda' => (int) ($this->posiciones_max_posicion ?? 0),
                'niveles' => (int) ($this->posiciones_max_nivel ?? 0),
            ],
            'capacidad' => [
                'total' => $total,
                'activas' => $activas,
                'fuera_servicio' => max(0, $total - $activas),
            ],
            'auditoria' => [
                'creado_por' => $this->whenLoaded('creadoPor', fn () => [
                    'id' => $this->creadoPor?->id,
                    'nombre' => $this->creadoPor?->name,
                ]),
                'created_at' => $this->created_at?->toAtomString(),
                'updated_at' => $this->updated_at?->toAtomString(),
            ],
        ];
    }
}
