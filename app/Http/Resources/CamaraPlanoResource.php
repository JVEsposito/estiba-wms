<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CamaraPlanoResource extends CamaraResumenResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'bandas_operacionales' => BandaOperacionalResource::collection(
                $this->whenLoaded('bandasOperacionales'),
            ),
            'folios_sin_posicion' => FolioSinPosicionResource::collection(
                $this->whenLoaded('ubicacionesSinPosicion'),
            ),
            'posiciones' => PosicionPlanoResource::collection(
                $this->whenLoaded('posiciones'),
            ),
        ];
    }
}
