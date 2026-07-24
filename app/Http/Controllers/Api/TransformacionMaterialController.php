<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelarOrdenTransformacionMaterialRequest;
use App\Http\Requests\CrearOrdenTransformacionMaterialRequest;
use App\Http\Requests\CrearRecetaMaterialRequest;
use App\Http\Requests\CrearVersionRecetaMaterialRequest;
use App\Http\Requests\PlanificarOrdenTransformacionMaterialRequest;
use App\Http\Resources\OrdenTransformacionMaterialResource;
use App\Http\Resources\RecetaMaterialResource;
use App\Models\OrdenTransformacionMaterial;
use App\Models\RecetaMaterial;
use App\Services\Materiales\ServicioTransformacionMaterial;
use App\Services\Materiales\ServicioVersionRecetaMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TransformacionMaterialController extends Controller
{
    public function recetas(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('consultar-transformaciones-materiales');
        $recetas = RecetaMaterial::query()
            ->with([
                'temporada:id,codigo,nombre,activa',
                'cliente:id,codigo,nombre,codigo_folio_materiales,activo',
                'itemSalida',
                'versiones' => fn ($consulta) => $consulta->orderByDesc('numero_version'),
                'versiones.detalles.itemEntrada',
                'creadoPor:id,name',
                'actualizadoPor:id,name',
            ])
            ->when($request->query('cliente_id'), fn ($consulta, $cliente) => $consulta
                ->where('cliente_id', $cliente))
            ->when($request->query('activa') !== null, fn ($consulta) => $consulta
                ->where('activa', $request->boolean('activa')))
            ->latest()
            ->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        return RecetaMaterialResource::collection($recetas);
    }

    public function guardarReceta(
        CrearRecetaMaterialRequest $request,
        ServicioTransformacionMaterial $servicio,
    ): JsonResponse {
        return (new RecetaMaterialResource(
            $servicio->crearReceta($request->validated(), $request->user()),
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function guardarVersionReceta(
        CrearVersionRecetaMaterialRequest $request,
        RecetaMaterial $recetaMaterial,
        ServicioVersionRecetaMaterial $servicio,
    ): JsonResponse {
        return (new RecetaMaterialResource(
            $servicio->crear($recetaMaterial, $request->validated(), $request->user()),
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function ordenes(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('consultar-transformaciones-materiales');
        $ordenes = OrdenTransformacionMaterial::query()
            ->with([
                'temporada:id,codigo,nombre,activa',
                'cliente:id,codigo,nombre,codigo_folio_materiales,activo',
                'versionReceta.receta.itemSalida',
                'reservas' => fn ($consulta) => $consulta
                    ->orderBy('item_material_id')
                    ->orderBy('orden_fifo'),
                'reservas.folioMaterial.folio.ubicacionActual.posicion.camara',
                'eventos' => fn ($consulta) => $consulta->orderBy('ocurrido_at'),
                'eventos.usuario:id,name',
                'creadoPor:id,name',
            ])
            ->when($request->query('estado'), fn ($consulta, $estado) => $consulta
                ->where('estado', $estado))
            ->when($request->query('cliente_id'), fn ($consulta, $cliente) => $consulta
                ->where('cliente_id', $cliente))
            ->latest()
            ->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        return OrdenTransformacionMaterialResource::collection($ordenes);
    }

    public function mostrarOrden(
        OrdenTransformacionMaterial $ordenTransformacionMaterial,
        ServicioTransformacionMaterial $servicio,
    ): OrdenTransformacionMaterialResource {
        Gate::authorize('consultar-transformaciones-materiales');

        return new OrdenTransformacionMaterialResource(
            $servicio->cargarOrden($ordenTransformacionMaterial),
        );
    }

    public function guardarOrden(
        CrearOrdenTransformacionMaterialRequest $request,
        ServicioTransformacionMaterial $servicio,
    ): JsonResponse {
        return (new OrdenTransformacionMaterialResource(
            $servicio->crearOrden($request->validated(), $request->user()),
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function planificar(
        PlanificarOrdenTransformacionMaterialRequest $request,
        OrdenTransformacionMaterial $ordenTransformacionMaterial,
        ServicioTransformacionMaterial $servicio,
    ): OrdenTransformacionMaterialResource {
        return new OrdenTransformacionMaterialResource($servicio->planificar(
            $ordenTransformacionMaterial,
            $request->validated('operacion_id'),
            $request->integer('version_conocida'),
            $request->user(),
        ));
    }

    public function cancelar(
        CancelarOrdenTransformacionMaterialRequest $request,
        OrdenTransformacionMaterial $ordenTransformacionMaterial,
        ServicioTransformacionMaterial $servicio,
    ): OrdenTransformacionMaterialResource {
        return new OrdenTransformacionMaterialResource($servicio->cancelar(
            $ordenTransformacionMaterial,
            $request->validated('operacion_id'),
            $request->validated('motivo'),
            $request->user(),
        ));
    }
}
