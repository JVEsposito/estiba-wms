<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarBandaOperacionalRequest;
use App\Http\Resources\BandaOperacionalResource;
use App\Models\BandaOperacional;
use App\Models\Camara;
use App\Services\Camaras\ServicioBandasOperacionales;

class BandaOperacionalController extends Controller
{
    public function update(
        ActualizarBandaOperacionalRequest $request,
        Camara $camara,
        BandaOperacional $bandaOperacional,
        ServicioBandasOperacionales $servicio,
    ): BandaOperacionalResource {
        $banda = $servicio->configurar(
            $camara,
            $bandaOperacional,
            $request->validated(),
            $request->user(),
        );

        $camara->refresh()->load([
            'bandasOperacionales' => fn ($consulta) => $consulta
                ->whereKey($banda->id)
                ->with('actualizadoPor:id,name'),
            'posiciones' => fn ($consulta) => $consulta
                ->where('banda', $banda->numero)
                ->with('ubicacionesActuales:id,posicion_id'),
        ]);
        $servicio->enriquecer($camara);

        return new BandaOperacionalResource(
            $camara->bandasOperacionales->firstWhere('id', $banda->id),
        );
    }
}
