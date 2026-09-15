<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Planificador\ServicioComparacionCiclosArbitraje;
use App\Services\Temporadas\ServicioTemporadaActiva;
use Illuminate\Http\JsonResponse;

class ComparacionCiclosPlanificadorController extends Controller
{
    public function __invoke(
        ServicioComparacionCiclosArbitraje $comparacion,
        ServicioTemporadaActiva $temporadas,
    ): JsonResponse {
        return response()
            ->json(['data' => $comparacion->obtener($temporadas->obtener())])
            ->header('Cache-Control', 'no-store, private');
    }
}
