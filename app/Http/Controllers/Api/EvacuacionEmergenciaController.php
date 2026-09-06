<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanOperacionalResource;
use App\Models\Camara;
use App\Models\PersonalAccessToken;
use App\Services\Camaras\ServicioControlEvacuacionEmergencia;
use Illuminate\Http\Request;

class EvacuacionEmergenciaController extends Controller
{
    public function store(
        Request $request,
        Camara $camara,
        ServicioControlEvacuacionEmergencia $servicio,
    ): PlanOperacionalResource {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $token = $request->user()?->currentAccessToken();

        return new PlanOperacionalResource($servicio->declarar(
            $camara,
            $request->user(),
            $datos['motivo'],
            $token instanceof PersonalAccessToken ? $token->dispositivo_id : null,
        ));
    }

    public function cancelar(
        Request $request,
        Camara $camara,
        ServicioControlEvacuacionEmergencia $servicio,
    ): PlanOperacionalResource {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $token = $request->user()?->currentAccessToken();

        return new PlanOperacionalResource($servicio->cancelar(
            $camara,
            $request->user(),
            $datos['motivo'],
            $token instanceof PersonalAccessToken ? $token->dispositivo_id : null,
        ));
    }
}
