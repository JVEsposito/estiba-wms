<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoRecepcionMaterial;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnularRecepcionMaterialRequest;
use App\Http\Requests\ConfirmarRecepcionMaterialRequest;
use App\Http\Requests\CrearRecepcionMaterialRequest;
use App\Http\Resources\RecepcionMaterialResource;
use App\Models\FolioMaterial;
use App\Models\RecepcionMaterial;
use App\Services\Materiales\ServicioRecepcionMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RecepcionMaterialController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('consultar-recepciones-materiales');
        $consulta = RecepcionMaterial::query()
            ->with(['temporada', 'cliente', 'proveedor', 'creadoPor', 'confirmadoPor', 'anuladoPor'])
            ->when($request->query('estado'), fn ($query, $estado) => $query->where('estado', $estado))
            ->when($request->query('cliente_id'), fn ($query, $cliente) => $query->where('cliente_id', $cliente))
            ->when($request->query('proveedor_material_id'), fn ($query, $proveedor) => $query
                ->where('proveedor_material_id', $proveedor))
            ->when($request->query('guia'), fn ($query, $guia) => $query
                ->where('numero_guia_despacho', 'like', '%'.trim((string) $guia).'%'));

        if (! $request->user()->can('gestionar-recepciones-materiales')) {
            $consulta->where('estado', EstadoRecepcionMaterial::Confirmada->value);
        }

        return RecepcionMaterialResource::collection(
            $consulta->latest()->paginate(min(100, max(10, (int) $request->query('per_page', 25)))),
        );
    }

    public function show(RecepcionMaterial $recepcionMaterial): RecepcionMaterialResource
    {
        Gate::authorize('consultar-recepciones-materiales');

        return new RecepcionMaterialResource(
            app(ServicioRecepcionMaterial::class)->cargar($recepcionMaterial),
        );
    }

    public function store(
        CrearRecepcionMaterialRequest $request,
        ServicioRecepcionMaterial $servicio,
    ): RecepcionMaterialResource {
        return new RecepcionMaterialResource(
            $servicio->crear($request->validated(), $request->user()),
        );
    }

    public function confirmar(
        ConfirmarRecepcionMaterialRequest $request,
        RecepcionMaterial $recepcionMaterial,
        ServicioRecepcionMaterial $servicio,
    ): RecepcionMaterialResource {
        return new RecepcionMaterialResource($servicio->confirmar(
            $recepcionMaterial,
            $request->validated('operacion_id'),
            $request->integer('version_conocida'),
            $request->user(),
        ));
    }

    public function anular(
        AnularRecepcionMaterialRequest $request,
        RecepcionMaterial $recepcionMaterial,
        ServicioRecepcionMaterial $servicio,
    ): RecepcionMaterialResource {
        return new RecepcionMaterialResource($servicio->anular(
            $recepcionMaterial,
            $request->validated('operacion_id'),
            $request->validated('motivo'),
            $request->user(),
        ));
    }

    public function foliosPendientes(Request $request): JsonResponse
    {
        Gate::authorize('consultar-recepciones-materiales');
        $folios = FolioMaterial::query()
            ->with([
                'folio',
                'item.cliente.cliente',
                'bultoRecepcion.detalle.recepcion.proveedor',
            ])
            ->whereHas('folio', fn ($consulta) => $consulta
                ->where('activo', true)
                ->whereNull('id', false)
                ->whereIn('estado_operacional', [
                    EstadoOperacionalFolio::PendienteUbicacion->value,
                    EstadoOperacionalFolio::Bloqueado->value,
                ])
                ->whereDoesntHave('ubicacionActual'))
            ->whereHas('bultoRecepcion.detalle.recepcion', fn ($consulta) => $consulta
                ->where('estado', EstadoRecepcionMaterial::Confirmada->value))
            ->when($request->query('cliente_id'), fn ($consulta, $cliente) => $consulta
                ->whereHas('item.cliente', fn ($catalogo) => $catalogo->where('cliente_id', $cliente)))
            ->orderBy('created_at')
            ->limit(min(1000, max(100, (int) $request->query('limit', 500))))
            ->get()
            ->map(function (FolioMaterial $material): array {
                $recepcion = $material->bultoRecepcion?->detalle?->recepcion;
                $cliente = $material->item?->cliente?->cliente;

                return [
                    'folio_id' => $material->folio_id,
                    'numero_folio' => $material->folio?->numero_folio,
                    'estado_operacional' => $material->folio?->estado_operacional?->value,
                    'cliente' => $cliente ? [
                        'id' => $cliente->id,
                        'codigo' => $cliente->codigo,
                        'nombre' => $cliente->nombre,
                    ] : null,
                    'item' => [
                        'id' => $material->item?->id,
                        'codigo' => $material->item?->codigo,
                        'nombre' => $material->item?->nombre,
                    ],
                    'categoria_operacional' => $material->categoria_operacional?->value,
                    'cantidad_actual' => $material->cantidad_actual,
                    'unidad_medida' => $material->unidad_medida,
                    'lote_proveedor' => $material->lote,
                    'bloqueado' => $material->folio?->estado_operacional === EstadoOperacionalFolio::Bloqueado,
                    'motivo_bloqueo' => $material->motivo_bloqueo,
                    'recepcion' => $recepcion ? [
                        'id' => $recepcion->id,
                        'numero_guia_despacho' => $recepcion->numero_guia_despacho,
                        'proveedor' => $recepcion->proveedor?->nombre,
                        'confirmado_at' => $recepcion->confirmado_at?->toAtomString(),
                    ] : null,
                ];
            });

        return response()->json(['data' => $folios]);
    }
}
