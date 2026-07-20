<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportacionCatalogoMaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_archivo' => $this->nombre_archivo,
            'tipo_archivo' => $this->tipo_archivo,
            'estado' => $this->estado,
            'resumen' => $this->resumen,
            'filas' => $this->filas,
            'errores' => $this->errores ?? [],
            'creado_por' => $this->whenLoaded('creadoPor', fn (): array => [
                'id' => $this->creadoPor->id,
                'nombre' => $this->creadoPor->name,
            ]),
            'confirmado_por' => $this->whenLoaded('confirmadoPor', fn (): ?array => $this->confirmadoPor ? [
                'id' => $this->confirmadoPor->id,
                'nombre' => $this->confirmadoPor->name,
            ] : null),
            'confirmado_at' => $this->confirmado_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
