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
            'posiciones' => PosicionPlanoResource::collection(
                $this->whenLoaded('posiciones'),
            ),
        ];
    }
}
