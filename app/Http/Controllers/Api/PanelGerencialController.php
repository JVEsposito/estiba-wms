<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoTemporada;
use App\Http\Controllers\Controller;
use App\Models\Temporada;
use App\Services\Gerencia\ServicioPanelGerencial;
use App\Services\Temporadas\ServicioTemporadaActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PanelGerencialController extends Controller
{
    public function __invoke(
        Request $request,
        ServicioPanelGerencial $servicio,
        ServicioTemporadaActiva $temporadaActiva,
    ): JsonResponse {
        $datos = $request->validate([
            // Las temporadas de prueba no forman parte de los reportes.
            'temporada_id' => ['nullable', 'uuid', Rule::exists('temporadas', 'id')->where('tipo', TipoTemporada::Productiva->value)],
        ]);
        $temporada = isset($datos['temporada_id'])
            ? Temporada::query()->findOrFail($datos['temporada_id'])
            : $temporadaActiva->obtener();

        return response()
            ->json(['data' => $servicio->obtener($temporada)])
            ->header('Cache-Control', 'no-store, private');
    }
}
