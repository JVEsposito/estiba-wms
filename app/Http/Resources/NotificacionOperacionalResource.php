<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificacionOperacionalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $lectura = $this->relationLoaded('lecturas') ? $this->lecturas->first() : null;

        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'severidad' => $this->severidad->value,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'carga' => $this->whenLoaded('carga', fn () => $this->carga ? [
                'id' => $this->carga->id,
                'codigo' => $this->carga->codigo,
                'prioridad' => $this->carga->prioridad->value,
                'estado' => $this->carga->estado->value,
            ] : null),
            'despacho_material' => $this->whenLoaded(
                'despachoMaterial',
                fn () => $this->despachoMaterial ? [
                    'id' => $this->despachoMaterial->id,
                    'codigo' => $this->despachoMaterial->codigo,
                    'estado' => $this->despachoMaterial->estado->value,
                    'destino' => [
                        'nombre' => $this->despachoMaterial->destino_nombre,
                        'centro_costo' => $this->despachoMaterial->destino_centro_costo,
                    ],
                ] : null,
            ),
            'folio' => $this->whenLoaded('folio', fn () => $this->folio ? [
                'id' => $this->folio->id,
                'numero_folio' => $this->folio->numero_folio,
            ] : null),
            'incidencia_id' => $this->incidencia_carga_folio_id,
            'datos' => $this->datos,
            'leida_at' => $lectura?->leida_at?->toAtomString(),
            'confirmada_at' => $lectura?->confirmada_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
