<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValidacionPalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operacion_id' => $this->operacion_id,
            'numero_folio' => $this->numero_folio,
            'numero_intento' => $this->numero_intento,
            'tipo_bulto' => $this->tipo_bulto,
            'cantidad_cajas' => $this->cantidad_cajas,
            'resultado' => $this->resultado->value,
            'estado' => $this->estado->value,
            'motivo' => $this->motivo?->value,
            'observacion' => $this->observacion,
            'catalogo' => [
                'version_dispositivo' => $this->catalogo_version_dispositivo,
                'version_servidor' => $this->catalogo_version_servidor,
                'desactualizado' => $this->catalogo_version_dispositivo !== $this->catalogo_version_servidor,
                'temporada' => $this->snapshot['temporada'] ?? null,
                'articulo' => $this->snapshot['articulo'] ?? null,
                'origen' => $this->snapshot['origen'] ?? null,
            ],
            'folio' => $this->whenLoaded('folio', fn () => $this->folio ? [
                'id' => $this->folio->id,
                'numero_folio' => $this->folio->numero_folio,
                'estado_operacional' => $this->folio->estado_operacional->value,
            ] : null),
            'usuario' => $this->whenLoaded('usuario', fn () => [
                'id' => $this->usuario->id,
                'nombre' => $this->usuario->name,
            ]),
            'dispositivo' => $this->whenLoaded('dispositivo', fn () => [
                'id' => $this->dispositivo->id,
                'codigo' => $this->dispositivo->codigo,
                'nombre' => $this->dispositivo->nombre,
            ]),
            'conflicto_con' => $this->whenLoaded('conflictoCon', fn () => $this->conflictoCon ? [
                'id' => $this->conflictoCon->id,
                'numero_folio' => $this->conflictoCon->numero_folio,
                'numero_intento' => $this->conflictoCon->numero_intento,
                'resultado' => $this->conflictoCon->resultado->value,
            ] : null),
            'generado_dispositivo_at' => $this->generado_dispositivo_at?->toAtomString(),
            'recibido_servidor_at' => $this->recibido_servidor_at?->toAtomString(),
        ];
    }
}
