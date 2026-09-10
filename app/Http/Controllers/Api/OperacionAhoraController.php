<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Operacion\ServicioOperacionAhora;
use App\Services\Temporadas\ServicioTemporadaActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OperacionAhoraController extends Controller
{
    public function __invoke(
        ServicioOperacionAhora $servicio,
        ServicioTemporadaActiva $temporadas,
    ): JsonResponse {
        return response()
            ->json(['data' => $servicio->obtener(
                $temporadas->obtener(),
                Gate::allows('administrar-plano-planta'),
            )])
            ->header('Cache-Control', 'no-store, private');
    }
}
