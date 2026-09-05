<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanOperacionalResource;
use App\Models\Camara;
use App\Services\Camaras\ServicioDesocupacionProgramada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesocupacionCamaraController extends Controller
{
    public function candidatas(ServicioDesocupacionProgramada $servicio): JsonResponse
    {
        return response()->json(['data' => $servicio->candidatas()]);
    }

    public function store(
        Request $request,
        Camara $camara,
        ServicioDesocupacionProgramada $servicio,
    ): PlanOperacionalResource {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return new PlanOperacionalResource(
            $servicio->iniciar($camara, $request->user(), $datos['motivo']),
        );
    }

    public function cancelar(
        Request $request,
        Camara $camara,
        ServicioDesocupacionProgramada $servicio,
    ): PlanOperacionalResource {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return new PlanOperacionalResource(
            $servicio->cancelar($camara, $request->user(), $datos['motivo']),
        );
    }
}
