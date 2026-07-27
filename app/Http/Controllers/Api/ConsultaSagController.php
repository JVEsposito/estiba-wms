<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarSagRequest;
use App\Http\Resources\ProductorCsgResource;
use App\Services\Consultas\ServicioConsultaSag;
use Illuminate\Http\JsonResponse;

class ConsultaSagController extends Controller
{
    public function store(
        ConsultarSagRequest $request,
        ServicioConsultaSag $servicio,
    ): JsonResponse {
        $datos = $request->validated();
        $productores = $servicio->consultar(
            $datos['tipo'],
            $datos['valor'],
            $request->user(),
        );

        return response()->json([
            'data' => ProductorCsgResource::collection($productores)->resolve($request),
            'cantidad' => $productores->count(),
            'message' => $productores->isEmpty()
                ? 'SAG no informó productores CSG para la búsqueda.'
                : 'Consulta SAG completada y productores actualizados.',
        ]);
    }
}
