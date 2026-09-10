<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPlanoPlantaRequest;
use App\Services\Operacion\ServicioPlanoPlanta;
use Illuminate\Http\JsonResponse;

class PlanoPlantaController extends Controller
{
    public function update(GuardarPlanoPlantaRequest $request, ServicioPlanoPlanta $servicio): JsonResponse
    {
        $plano = $servicio->guardar($request->validated(), $request->user());

        return response()->json([
            'data' => [
                'nombre' => $plano->nombre,
                'version' => $plano->version,
                'elementos' => $plano->elementos,
                'actualizado_at' => $plano->updated_at?->toAtomString(),
            ],
        ]);
    }
}
