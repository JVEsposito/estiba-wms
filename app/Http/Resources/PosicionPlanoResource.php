<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosicionPlanoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ubicacion = $this->resource->relationLoaded('ubicacionActual')
            ? $this->ubicacionActual
            : null;
        $folio = $ubicacion?->folio;

        return [
            'id' => $this->id,
            'fila' => $this->fila,
            'profundidad' => $this->profundidad,
            'nivel' => $this->nivel,
            'etiqueta' => $this->etiqueta,
            'estado' => $this->estado->value,
            'ocupada' => $ubicacion !== null,
            'folio' => $folio ? [
                'id' => $folio->id,
                'numero_folio' => $folio->numero_folio,
                'tipo_bulto' => $folio->tipo_bulto->value,
                'estado_operacional' => $folio->estado_operacional->value,
                'condicion_sag' => $folio->condicionSag ? [
                    'id' => $folio->condicionSag->id,
                    'codigo' => $folio->condicionSag->codigo,
                    'nombre' => $folio->condicionSag->nombre,
                ] : null,
                'fecha_ingreso' => $folio->fecha_ingreso?->toAtomString(),
                'variedad' => $folio->variedad,
                'calibre' => $folio->calibre,
                'marca' => $folio->marca,
                'exportadora' => $folio->exportadora,
                'ubicado_at' => $ubicacion->ubicado_at?->toAtomString(),
            ] : null,
        ];
    }
}
