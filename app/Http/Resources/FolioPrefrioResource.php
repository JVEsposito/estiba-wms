<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FolioPrefrioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_folio' => $this->numero_folio,
            'tipo_bulto' => $this->tipo_bulto->value,
            'estado_operacional' => $this->estado_operacional->value,
            'condicion_termica' => $this->condicion_termica?->value,
            'habilitacion_almacenamiento' => $this->habilitacion_almacenamiento?->value,
            'variedad' => $this->variedad,
            'calibre' => $this->calibre,
            'marca' => $this->marca,
            'exportadora' => $this->exportadora,
            'fecha_ingreso' => $this->fecha_ingreso?->toAtomString(),
        ];
    }
}
