<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoPosicion;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarCamaraRequest;
use App\Http\Requests\CrearCamaraRequest;
use App\Http\Resources\CamaraConfiguracionResource;
use App\Models\Camara;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use App\Services\Camaras\ServicioBandasOperacionales;
use App\Services\Camaras\ServicioConfiguracionCamara;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ConfiguracionCamaraController extends Controller
{
    public function index(
        Request $request,
        AlcanceOperacionalUsuario $alcance,
    ): AnonymousResourceCollection {
        Gate::authorize('consultar-configuracion-camaras');
        $contenidos = collect($alcance->contenidosVisibles($request->user()))
            ->map->value
            ->all();

        $camaras = Camara::query()
            ->whereIn('contenido', $contenidos)
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
    ): JsonResponse {
        Gate::authorize('consultar-configuracion-camaras');

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

    public function show(
        Request $request,
        Camara $camara,
        AlcanceOperacionalUsuario $alcance,
        ServicioBandasOperacionales $bandasOperacionales,
    ): CamaraConfiguracionResource {
        Gate::authorize('consultar-configuracion-camaras');
        abort_unless($alcance->puedeVerCamara($request->user(), $camara), 403);

        return new CamaraConfiguracionResource(
            $this->cargarDetalle($camara, $bandasOperacionales),
        );
    }

    public function update(
        ActualizarCamaraRequest $request,
        Camara $camara,
        ServicioConfiguracionCamara $servicio,
        ServicioBandasOperacionales $bandasOperacionales,
    ): CamaraConfiguracionResource {
        $actualizada = $servicio->actualizar(
            $camara,
            $request->validated(),
            $request->user(),
        );

        return new CamaraConfiguracionResource(
            $this->cargarDetalle($actualizada, $bandasOperacionales),
        );
    }

    public function destroy(
        Request $request,
        Camara $camara,
        ServicioConfiguracionCamara $servicio,
        ServicioBandasOperacionales $bandasOperacionales,
    ): CamaraConfiguracionResource {
        Gate::authorize('administrar-camaras');

        $desactivada = $servicio->desactivar($camara, $request->user());

        return new CamaraConfiguracionResource(
            $this->cargarDetalle($desactivada, $bandasOperacionales),
        );
    }

    private function cargarDetalle(
        Camara $camara,
        ServicioBandasOperacionales $bandasOperacionales,
    ): Camara {
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
            'bandasOperacionales' => fn ($consulta) => $consulta
                ->where('numero', '<=', $camara->cantidad_bandas)
                ->with('actualizadoPor:id,name'),
            'posiciones' => fn ($consulta) => $consulta
                ->with('ubicacionesActuales:id,posicion_id')
                ->where('banda', '<=', $camara->cantidad_bandas)
                ->where('posicion', '<=', $camara->posiciones_por_banda)
                ->where('nivel', '<=', $camara->cantidad_niveles)
                ->orderBy('banda')
                ->orderBy('posicion')
                ->orderBy('nivel'),
        ]);

        $bandasOperacionales->enriquecer($camara);

        return $camara;
    }
}
