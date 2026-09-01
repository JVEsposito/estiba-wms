<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Temporada;
use App\Services\Gerencia\ServicioPanelGerencial;
use App\Services\Temporadas\ServicioTemporadaActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelGerencialController extends Controller
{
    public function __invoke(
        Request $request,
        ServicioPanelGerencial $servicio,
        ServicioTemporadaActiva $temporadaActiva,
    ): JsonResponse {
        $datos = $request->validate([
            'temporada_id' => ['nullable', 'uuid', 'exists:temporadas,id'],
        ]);
        $temporada = isset($datos['temporada_id'])
            ? Temporada::query()->findOrFail($datos['temporada_id'])
            : $temporadaActiva->obtener();

        return response()
            ->json(['data' => $servicio->obtener($temporada)])
            ->header('Cache-Control', 'no-store, private');
    }
}
