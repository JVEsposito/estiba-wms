<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanOperacionalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'temporada' => $this->whenLoaded('temporada', fn (): array => [
                'id' => $this->temporada->id,
                'codigo' => $this->temporada->codigo,
                'nombre' => $this->temporada->nombre,
            ]),
            'tipo' => $this->tipo->value,
            'estado' => $this->estado->value,
            'prioridad' => $this->prioridad->value,
            'titulo' => $this->titulo,
            'motivo' => $this->motivo,
            'referencia' => $this->referencia_tipo && $this->referencia_id ? [
                'tipo' => $this->referencia_tipo,
                'id' => $this->referencia_id,
            ] : null,
            'contexto' => $this->contexto ?? [],
            'creado_por' => $this->whenLoaded('creadoPor', fn (): array => [
                'id' => $this->creadoPor->id,
                'nombre' => $this->creadoPor->name,
            ]),
            'iniciado_por' => $this->whenLoaded('iniciadoPor', fn (): ?array => $this->iniciadoPor ? [
                'id' => $this->iniciadoPor->id,
                'nombre' => $this->iniciadoPor->name,
            ] : null),
            'tareas' => TareaMovimientoResource::collection($this->whenLoaded('tareas')),
            'total_tareas' => $this->whenCounted('tareas'),
            'programado_at' => $this->programado_at?->toAtomString(),
            'iniciado_at' => $this->iniciado_at?->toAtomString(),
            'pausado_at' => $this->pausado_at?->toAtomString(),
            'completado_at' => $this->completado_at?->toAtomString(),
            'cancelado_at' => $this->cancelado_at?->toAtomString(),
            'motivo_cancelacion' => $this->motivo_cancelacion,
            'version' => $this->version,
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
