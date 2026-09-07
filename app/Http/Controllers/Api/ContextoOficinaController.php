<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use App\Services\Temporadas\ServicioTemporadaActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextoOficinaController extends Controller
{
    public function __invoke(Request $request, AlcanceOperacionalUsuario $alcance, ServicioTemporadaActiva $temporadas): JsonResponse
    {
        abort_unless($alcance->puedeAccederOficina($request->user()), 403);
        $temporada = $temporadas->buscar();

        return response()->json(['data' => [
            'planta' => config('oficina.planta') ?: null,
            'temporada' => $temporada ? [
                'id' => $temporada->id,
                'codigo' => $temporada->codigo,
                'nombre' => $temporada->nombre,
            ] : null,
        ]])->header('Cache-Control', 'no-store, private');
    }
}
