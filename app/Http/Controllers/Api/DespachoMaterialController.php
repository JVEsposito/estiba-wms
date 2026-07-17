<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelarDespachoMaterialRequest;
use App\Http\Requests\CrearDespachoMaterialRequest;
use App\Http\Requests\RetirarDespachoMaterialRequest;
use App\Http\Resources\DespachoMaterialResource;
use App\Models\DespachoMaterial;
use App\Models\FolioMaterial;
use App\Models\MovimientoInventarioMaterial;
use App\Models\PersonalAccessToken;
use App\Services\Autenticacion\ContextoOperacional;
use App\Services\Materiales\ServicioDespachoMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DespachoMaterialController extends Controller
{
    public function index(
        Request $request,
        ServicioDespachoMaterial $servicio,
    ): JsonResponse {
        Gate::authorize('consultar-despachos-materiales');
        $estados = array_filter(explode(',', (string) $request->query('estados', '')));
        $despachos = DespachoMaterial::query()
            ->when($estados !== [], fn ($consulta) => $consulta->whereIn('estado', $estados))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (DespachoMaterial $despacho) => $servicio->cargar($despacho));

        return response()->json(['data' => DespachoMaterialResource::collection($despachos)]);
    }

    public function store(
        CrearDespachoMaterialRequest $request,
        ServicioDespachoMaterial $servicio,
    ): JsonResponse {
        $token = $request->user()->currentAccessToken();
        $dispositivo = $token instanceof PersonalAccessToken && $token->dispositivo_id
            ? $token->dispositivo()->first()
            : null;
        $despacho = $servicio->crear(
            $request->validated(),
            $request->user(),
            $dispositivo,
        );

        return (new DespachoMaterialResource($despacho))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        DespachoMaterial $despachoMaterial,
        ServicioDespachoMaterial $servicio,
    ): DespachoMaterialResource {
        Gate::authorize('consultar-despachos-materiales');

        return new DespachoMaterialResource($servicio->cargar($despachoMaterial));
    }

    public function retirar(
        RetirarDespachoMaterialRequest $request,
        DespachoMaterial $despachoMaterial,
        ContextoOperacional $contexto,
        ServicioDespachoMaterial $servicio,
    ): DespachoMaterialResource {
        [$usuario, $dispositivo] = $contexto->obtener($request);
        $despacho = $servicio->retirar(
            $despachoMaterial,
            $request->validated('operacion_id'),
            $request->validated('retiros'),
            $usuario,
            $dispositivo,
        );

        return new DespachoMaterialResource($despacho);
    }

    public function cancelar(
        CancelarDespachoMaterialRequest $request,
        DespachoMaterial $despachoMaterial,
        ServicioDespachoMaterial $servicio,
    ): DespachoMaterialResource {
        $token = $request->user()->currentAccessToken();
        $dispositivo = $token instanceof PersonalAccessToken && $token->dispositivo_id
            ? $token->dispositivo()->first()
            : null;
        $despacho = $servicio->cancelar(
            $despachoMaterial,
            $request->validated('operacion_id'),
            $request->validated('motivo'),
            $request->user(),
            $dispositivo,
        );

        return new DespachoMaterialResource($despacho);
    }

    public function inventario(): JsonResponse
    {
        Gate::authorize('consultar-despachos-materiales');
        $folios = FolioMaterial::query()
            ->with(['item', 'folio.ubicacionActual.posicion.camara'])
            ->whereHas('folio', fn ($consulta) => $consulta->where('activo', true))
            ->orderBy('item_material_id')
            ->get()
            ->map(function (FolioMaterial $material): array {
                $folio = $material->folio;
                $posicion = $folio->ubicacionActual?->posicion;

                return [
                    'folio_id' => $folio->id,
                    'numero_folio' => $folio->numero_folio,
                    'item' => [
                        'id' => $material->item->id,
                        'codigo' => $material->item->codigo,
                        'nombre' => $material->item->nombre,
                    ],
                    'cantidad_actual' => $material->cantidad_actual,
                    'cantidad_reservada' => $material->cantidad_reservada,
                    'cantidad_disponible' => number_format(max(
                        0,
                        (float) $material->cantidad_actual - (float) $material->cantidad_reservada,
                    ), 3, '.', ''),
                    'unidad_medida' => $material->unidad_medida,
                    'lote' => $material->lote,
                    'fecha_ingreso' => $folio->fecha_ingreso?->toAtomString(),
                    'camara' => $posicion?->camara ? [
                        'id' => $posicion->camara->id,
                        'codigo' => $posicion->camara->codigo,
                        'nombre' => $posicion->camara->nombre,
                    ] : null,
                    'posicion' => $posicion ? [
                        'id' => $posicion->id,
                        'etiqueta' => $posicion->etiqueta,
                    ] : null,
                ];
            });

        return response()->json(['data' => $folios]);
    }

    public function kardex(Request $request): JsonResponse
    {
        Gate::authorize('consultar-kardex-materiales');
        $movimientos = MovimientoInventarioMaterial::query()
            ->with(['folioMaterial.folio:id,numero_folio', 'item:id,codigo,nombre'])
            ->when($request->query('folio_id'), fn ($consulta, $folio) => $consulta
                ->where('folio_id', $folio))
            ->when($request->query('item_material_id'), fn ($consulta, $item) => $consulta
                ->where('item_material_id', $item))
            ->latest('ocurrido_at')
            ->limit(250)
            ->get()
            ->map(fn (MovimientoInventarioMaterial $movimiento): array => [
                'id' => $movimiento->id,
                'folio' => [
                    'id' => $movimiento->folio_id,
                    'numero_folio' => $movimiento->folioMaterial->folio->numero_folio,
                ],
                'item' => [
                    'id' => $movimiento->item->id,
                    'codigo' => $movimiento->item->codigo,
                    'nombre' => $movimiento->item->nombre,
                ],
                'tipo' => $movimiento->tipo->value,
                'cantidad' => $movimiento->cantidad,
                'cantidad_anterior' => $movimiento->cantidad_anterior,
                'cantidad_resultante' => $movimiento->cantidad_resultante,
                'destino_nombre' => $movimiento->destino_nombre,
                'destino_centro_costo' => $movimiento->destino_centro_costo,
                'ocurrido_at' => $movimiento->ocurrido_at?->toAtomString(),
            ]);

        return response()->json(['data' => $movimientos]);
    }
}
