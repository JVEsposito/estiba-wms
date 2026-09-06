<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Planificador\ServicioSaludPlanificador;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaludPlanificadorController extends Controller
{
    public function __invoke(
        Request $request,
        ServicioSaludPlanificador $salud,
    ): JsonResponse {
        Gate::authorize('consultar-integridad-operacional');
        $datos = validator($request->query(), [
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'camara_id' => ['nullable', 'uuid', 'exists:camaras,id'],
        ])->validate();

        $hasta = isset($datos['hasta'])
            ? CarbonImmutable::parse($datos['hasta'])
            : CarbonImmutable::now();
        $desde = isset($datos['desde'])
            ? CarbonImmutable::parse($datos['desde'])
            : $hasta->subHours(8);
        if ($desde->greaterThan($hasta)) {
            throw ValidationException::withMessages([
                'desde' => 'La fecha inicial debe ser anterior o igual a la fecha final.',
            ]);
        }
        if ($desde->diffInSeconds($hasta) > 7 * 24 * 60 * 60) {
            throw ValidationException::withMessages([
                'desde' => 'La ventana de métricas no puede superar siete días.',
            ]);
        }

        return response()->json([
            'data' => $salud->snapshot(
                $desde,
                $hasta,
                $datos['camara_id'] ?? null,
            ),
        ]);
    }
}
