<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoPosicion;
use App\Http\Controllers\Controller;
use App\Http\Requests\CrearCamaraRequest;
use App\Http\Resources\CamaraConfiguracionResource;
use App\Models\Camara;
use App\Services\Camaras\ServicioConfiguracionCamara;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ConfiguracionCamaraController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('configurar-camaras');

        $camaras = Camara::query()
            ->with('creadoPor:id,name')
            ->withCount([
                'posiciones',
                'posiciones as posiciones_activas_count' => fn ($consulta) => $consulta
                    ->where('estado', EstadoPosicion::Activa->value),
            ])
            ->withMax('posiciones', 'banda')
            ->withMax('posiciones', 'posicion')
            ->withMax('posiciones', 'nivel')
            ->orderBy('codigo')
            ->get();

        return CamaraConfiguracionResource::collection($camaras);
    }

    public function siguienteCodigo(
        ServicioConfiguracionCamara $servicio,
    ): JsonResponse {
        Gate::authorize('configurar-camaras');

        return response()->json([
            'data' => ['codigo' => $servicio->siguienteCodigo()],
        ]);
    }

    public function store(
        CrearCamaraRequest $request,
        ServicioConfiguracionCamara $servicio,
    ): JsonResponse {
        $camara = $servicio->crear($request->validated(), $request->user());

        return (new CamaraConfiguracionResource($camara->load('creadoPor:id,name')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
