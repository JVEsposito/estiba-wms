<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ProductorCsgResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $clientes = $this->whenLoaded('clientes');

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'rut' => $this->rut,
            'razon_social' => $this->razon_social,
            'predio' => $this->predio,
            'direccion' => $this->direccion,
            'estado_sag' => $this->estado_sag,
            'tipo_codigo' => $this->tipo_codigo,
            'especies' => $this->especies ?? [],
            'estado_asociacion' => $this->estado_asociacion,
            'primera_verificacion_at' => $this->primera_verificacion_at?->toIso8601String(),
            'ultima_verificacion_at' => $this->ultima_verificacion_at?->toIso8601String(),
            'clientes' => $clientes instanceof Collection
                ? $clientes
                    ->filter(fn ($cliente): bool => (bool) $cliente->pivot->activo)
                    ->values()
                    ->map(fn ($cliente): array => [
                        'id' => $cliente->id,
                        'codigo' => $cliente->codigo,
                        'nombre' => $cliente->nombre,
                    ])
                : [],
            'catalogos_temporada' => $this->whenLoaded(
                'catalogosTemporada',
                fn () => $this->catalogosTemporada->map(fn ($csg): array => [
                    'id' => $csg->id,
                    'codigo' => $csg->codigo,
                    'predio' => $csg->predio,
                    'activo' => $csg->activo,
                    'temporada' => $csg->temporada?->only(['id', 'codigo', 'nombre']),
                ]),
            ),
        ];
    }
}
