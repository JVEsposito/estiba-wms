<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoPosicion;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarCamaraRequest;
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
            ->with(['actualizadoPor:id,name', 'creadoPor:id,name'])
            ->withCount([
                'posiciones as posiciones_activas_count' => fn ($consulta) => $consulta
                    ->where('estado', EstadoPosicion::Activa->value)
                    ->whereColumn('banda', '<=', 'camaras.cantidad_bandas')
                    ->whereColumn('posicion', '<=', 'camaras.posiciones_por_banda')
                    ->whereColumn('nivel', '<=', 'camaras.cantidad_niveles'),
                'posiciones as posiciones_ocupadas_count' => fn ($consulta) => $consulta
                    ->whereHas('ubicacionActual')
                    ->whereColumn('banda', '<=', 'camaras.cantidad_bandas')
                    ->whereColumn('posicion', '<=', 'camaras.posiciones_por_banda')
                    ->whereColumn('nivel', '<=', 'camaras.cantidad_niveles'),
            ])
            ->orderBy('codigo')
            ->get();

        return CamaraConfiguracionResource::collection($camaras);
    }

    public function siguienteCodigo(
        ServicioConfiguracionCamara $servicio,
    ): JsonResponse
    {
        Gate::authorize('configurar-camaras');

        return response()->json([
            'data' => ['codigo' => $servicio->siguienteCodigo()],
        ]);
    }

    public function store(
        CrearCamaraRequest $request,
        ServicioConfiguracionCamara $servicio,
    ): JsonResponse
    {
        $camara = $servicio->crear($request->validated(), $request->user());

        return (new CamaraConfiguracionResource($camara->load('creadoPor:id,name')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Camara $camara): CamaraConfiguracionResource
    {
        Gate::authorize('configurar-camaras');

        return new CamaraConfiguracionResource($this->cargarDetalle($camara));
    }

    public function update(
        ActualizarCamaraRequest $request,
        Camara $camara,
        ServicioConfiguracionCamara $servicio,
    ): CamaraConfiguracionResource
    {
        $actualizada = $servicio->actualizar(
            $camara,
            $request->validated(),
            $request->user(),
        );

        return new CamaraConfiguracionResource($this->cargarDetalle($actualizada));
    }

    public function destroy(
        Request $request,
        Camara $camara,
        ServicioConfiguracionCamara $servicio,
    ): CamaraConfiguracionResource
    {
        Gate::authorize('administrar-camaras');

        $desactivada = $servicio->desactivar($camara, $request->user());

        return new CamaraConfiguracionResource($this->cargarDetalle($desactivada));
    }

    private function cargarDetalle(Camara $camara): Camara
    {
        $camara->load(['actualizadoPor:id,name', 'creadoPor:id,name']);
        $camara->loadCount([
            'posiciones as posiciones_activas_count' => fn ($consulta) => $consulta
                ->where('estado', EstadoPosicion::Activa->value)
                ->where('banda', '<=', $camara->cantidad_bandas)
                ->where('posicion', '<=', $camara->posiciones_por_banda)
                ->where('nivel', '<=', $camara->cantidad_niveles),
            'posiciones as posiciones_ocupadas_count' => fn ($consulta) => $consulta
                ->whereHas('ubicacionActual')
                ->where('banda', '<=', $camara->cantidad_bandas)
                ->where('posicion', '<=', $camara->posiciones_por_banda)
                ->where('nivel', '<=', $camara->cantidad_niveles),
        ]);
        $camara->load([
            'posiciones' => fn ($consulta) => $consulta
                ->where('banda', '<=', $camara->cantidad_bandas)
                ->where('posicion', '<=', $camara->posiciones_por_banda)
                ->where('nivel', '<=', $camara->cantidad_niveles)
                ->orderBy('banda')
                ->orderBy('posicion')
                ->orderBy('nivel'),
        ]);

        return $camara;
    }
}
