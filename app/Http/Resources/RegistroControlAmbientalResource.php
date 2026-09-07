<?php

namespace App\Http\Resources;

use App\Models\RegistroControlAmbiental;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistroControlAmbientalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ahora = now();
        $vigenteHasta = $this->vigenteHasta();
        $vigente = $this->estaVigente($ahora);

        return [
            'id' => $this->id,
            'operacion_id' => $this->operacion_id,
            'camara' => $this->whenLoaded('camara', fn () => [
                'id' => $this->camara->id,
                'codigo' => $this->camara->codigo,
                'nombre' => $this->camara->nombre,
            ]),
            'temperaturas' => [
                'inicio_c' => (float) $this->temperatura_inicio_c,
                'medio_c' => (float) $this->temperatura_medio_c,
                'fondo_c' => (float) $this->temperatura_fondo_c,
            ],
            'frecuencia_minutos' => RegistroControlAmbiental::FRECUENCIA_MINUTOS,
            'estado_vigencia' => $vigente ? 'vigente' : 'vencido',
            'vigente_hasta' => $vigenteHasta->toAtomString(),
            'version' => $this->version,
            'registrado_por' => $this->whenLoaded('registradoPor', fn () => [
                'id' => $this->registradoPor->id,
                'nombre' => $this->registradoPor->name,
            ]),
            'dispositivo' => $this->whenLoaded('dispositivo', fn () => [
                'id' => $this->dispositivo->id,
                'codigo' => $this->dispositivo->codigo,
                'nombre' => $this->dispositivo->nombre,
            ]),
            'correcciones' => $this->when(
                $this->resource->relationLoaded('correcciones'),
                fn () => CorreccionControlAmbientalResource::collection($this->correcciones),
            ),
            'capturado_at' => $this->capturado_at?->toAtomString(),
            'recibido_servidor_at' => $this->recibido_servidor_at?->toAtomString(),
        ];
    }
}
