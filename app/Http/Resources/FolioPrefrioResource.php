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
        $datosExternos = is_array($this->datos_externos) ? $this->datos_externos : [];

        return [
            'id' => $this->id,
            'numero_folio' => $this->numero_folio,
            'tipo_bulto' => $this->tipo_bulto->value,
            'estado_operacional' => $this->estado_operacional->value,
            'condicion_termica' => $this->condicion_termica?->value,
            'habilitacion_almacenamiento' => $this->habilitacion_almacenamiento?->value,
            'especie' => $datosExternos['especie'] ?? null,
            'variedad' => $this->variedad,
            'calibre' => $this->calibre,
            'envase' => $datosExternos['envase'] ?? null,
            'categoria' => $datosExternos['categoria'] ?? null,
            'marca' => $this->marca,
            'exportadora' => $this->exportadora,
            'csg' => $datosExternos['csg'] ?? null,
            'predio' => $datosExternos['predio'] ?? null,
            'cantidad_cajas' => isset($datosExternos['cantidad_cajas'])
                ? (int) $datosExternos['cantidad_cajas']
                : null,
            'fecha_ingreso' => $this->fecha_ingreso?->toAtomString(),
        ];
    }
}
