<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoCarga;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarCargaRequest;
use App\Http\Requests\AgregarFoliosCargaRequest;
use App\Http\Requests\CrearCargaRequest;
use App\Http\Requests\VersionCargaRequest;
use App\Http\Resources\CargaResource;
use App\Models\Carga;
use App\Models\Folio;
use App\Services\Cargas\ServicioCarga;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CargaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('gestionar-cargas');

        $cargas = Carga::query()
            ->with($this->relacionesDetalle())
            ->orderByDesc('created_at')
            ->get();

        return CargaResource::collection($cargas);
    }

    public function pendientes(): AnonymousResourceCollection
    {
        Gate::authorize('consultar-cargas-operacion');

        $cargas = Carga::query()
            ->whereIn(
                'estado',
                collect(EstadoCarga::visiblesEnOperacion())
                    ->map(fn (EstadoCarga $estado): string => $estado->value)
                    ->all(),
            )
            ->with($this->relacionesDetalle())
            ->orderByRaw(
                "CASE prioridad WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 ELSE 3 END",
            )
            ->orderBy('publicada_at')
            ->get();

        return CargaResource::collection($cargas);
    }

    public function store(
        CrearCargaRequest $request,
        ServicioCarga $servicio,
    ): JsonResponse {
        $carga = $servicio->crear(
            $request->validated(),
            $request->user(),
        );

        return (new CargaResource($this->cargarDetalle($carga)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Carga $carga): CargaResource
    {
        Gate::authorize('gestionar-cargas');

        return new CargaResource($this->cargarDetalle($carga));
    }

    public function update(
        ActualizarCargaRequest $request,
        Carga $carga,
        ServicioCarga $servicio,
    ): CargaResource {
        $actualizada = $servicio->actualizar(
            $carga,
            $request->validated(),
            $request->user(),
            $request->integer('version_esperada'),
        );

        return new CargaResource($this->cargarDetalle($actualizada));
    }

    public function agregarFolios(
        AgregarFoliosCargaRequest $request,
        Carga $carga,
        ServicioCarga $servicio,
    ): CargaResource {
        $actualizada = $servicio->agregarFolios(
            $carga,
            $request->validated('folios'),
            $request->user(),
            $request->integer('version_esperada'),
        );

        return new CargaResource($this->cargarDetalle($actualizada));
    }

    public function quitarFolio(
        VersionCargaRequest $request,
        Carga $carga,
        Folio $folio,
        ServicioCarga $servicio,
    ): CargaResource {
        Gate::authorize('gestionar-cargas');

        $actualizada = $servicio->quitarFolio(
            $carga,
            $folio,
            $request->user(),
            $request->integer('version_esperada'),
            $request->validated('motivo'),
        );

        return new CargaResource($this->cargarDetalle($actualizada));
    }

    public function publicar(
        VersionCargaRequest $request,
        Carga $carga,
        ServicioCarga $servicio,
    ): CargaResource {
        Gate::authorize('gestionar-cargas');

        $publicada = $servicio->publicar(
            $carga,
            $request->user(),
            $request->integer('version_esperada'),
        );

        return new CargaResource($this->cargarDetalle($publicada));
    }

    public function cancelar(
        VersionCargaRequest $request,
        Carga $carga,
        ServicioCarga $servicio,
    ): CargaResource {
        Gate::authorize('gestionar-cargas');

        $cancelada = $servicio->cancelar(
            $carga,
            $request->user(),
            $request->integer('version_esperada'),
            $request->validated('motivo'),
        );

        return new CargaResource($this->cargarDetalle($cancelada));
    }

    private function cargarDetalle(Carga $carga): Carga
    {
        return $carga->load($this->relacionesDetalle());
    }

    /**
     * @return array<int, string>
     */
    private function relacionesDetalle(): array
    {
        return [
            'camaraObjetivo:id,codigo,nombre',
            'creadaPor:id,name',
            'actualizadaPor:id,name',
            'publicadaPor:id,name',
            'canceladaPor:id,name',
            'asignacionesActuales.asignadoPor:id,name',
            'asignacionesActuales.folio.ubicacionActual.posicion.camara:id,codigo,nombre',
        ];
    }
}
