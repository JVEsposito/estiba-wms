<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccionResolucionDiscrepancia;
use App\Http\Controllers\Controller;
use App\Models\DiscrepanciaManiobra;
use App\Services\Estiba\ServicioManiobrasOperacionales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscrepanciaManiobraController extends Controller
{
    public function resolver(
        Request $request,
        DiscrepanciaManiobra $discrepanciaManiobra,
        ServicioManiobrasOperacionales $maniobras,
    ): JsonResponse {
        $datos = $request->validate([
            'accion' => ['required', Rule::enum(AccionResolucionDiscrepancia::class)],
            'version_maniobra' => ['required', 'integer', 'min:1'],
            'resolucion' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $discrepancia = $maniobras->resolverDiscrepancia(
            $discrepanciaManiobra,
            $request->user(),
            AccionResolucionDiscrepancia::from($datos['accion']),
            (int) $datos['version_maniobra'],
            $datos['resolucion'],
        );

        $maniobra = $discrepancia->maniobraOperacional()->firstOrFail();
        $tarea = $discrepancia->tareaMovimiento()->firstOrFail();

        return response()->json(['data' => [
            'id' => $discrepancia->id,
            'estado' => $discrepancia->estado->value,
            'accion_resolucion' => $discrepancia->accion_resolucion?->value,
            'resolucion' => $discrepancia->resolucion,
            'resuelta_at' => $discrepancia->resuelta_at?->toAtomString(),
            'resuelta_por_user_id' => $discrepancia->resuelta_por_user_id,
            'maniobra' => [
                'id' => $maniobra->id,
                'estado' => $maniobra->estado->value,
                'version' => $maniobra->version,
            ],
            'tarea' => [
                'id' => $tarea->id,
                'estado' => $tarea->estado->value,
            ],
        ]]);
    }
}
