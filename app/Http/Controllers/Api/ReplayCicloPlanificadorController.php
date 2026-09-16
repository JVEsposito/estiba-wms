<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Planificador\ServicioReplayCiclosArbitraje;
use App\Services\Temporadas\ServicioTemporadaActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReplayCicloPlanificadorController extends Controller
{
    public function __invoke(
        Request $request,
        ServicioReplayCiclosArbitraje $replay,
        ServicioTemporadaActiva $temporadas,
    ): JsonResponse {
        $datos = validator(
            ['ciclo' => $request->query('ciclo', 'actual')],
            ['ciclo' => ['required', Rule::in(['actual', 'anterior'])]],
        )->validate();

        return response()
            ->json(['data' => $replay->verificar($temporadas->obtener(), $datos['ciclo'])])
            ->header('Cache-Control', 'no-store, private');
    }
}
