<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelarDespachoMaterialRequest;
use App\Http\Requests\CrearDespachoMaterialRequest;
use App\Http\Requests\RetirarDespachoMaterialRequest;
use App\Http\Resources\DespachoMaterialResource;
use App\Http\Resources\ResumenDespachoMaterialResource;
use App\Models\DespachoMaterial;
use App\Models\MovimientoInventarioMaterial;
use App\Models\PersonalAccessToken;
use App\Models\Temporada;
use App\Services\Autenticacion\ContextoOperacional;
use App\Services\Materiales\ServicioConsultaInventarioMaterial;
use App\Services\Materiales\ServicioDespachoMaterial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
            ->where('temporada_id', '=', $this->consultaTemporadaActiva())
            ->when($estados !== [], fn ($consulta) => $consulta->whereIn('estado', $estados))
            ->latest()
            ->limit(100)
            ->get();
        $vista = $request->query('vista');

        if ($vista === 'resumen') {
            $servicio->cargarColeccionResumen($despachos);
        } elseif ($vista === 'operacion') {
            $servicio->cargarColeccionOperacion($despachos);
        } else {
            $servicio->cargarColeccion($despachos);
        }

        return response()->json([
            'data' => $vista === 'resumen'
                ? ResumenDespachoMaterialResource::collection($despachos)
                : DespachoMaterialResource::collection($despachos),
        ]);
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

    public function inventario(
        Request $request,
        ServicioConsultaInventarioMaterial $servicio,
    ): JsonResponse
    {
        Gate::authorize('consultar-despachos-materiales');
        $filtros = $request->validate([
            'vista' => ['nullable', Rule::in(['detalle', 'resumen'])],
            'cliente_id' => ['nullable', 'uuid', 'exists:clientes_materiales,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $resumen = $servicio->resumen($filtros['cliente_id'] ?? null);

        if (($filtros['vista'] ?? 'detalle') === 'resumen') {
            return response()->json(['data' => [], ...$resumen]);
        }

        $paginacion = $servicio->detalle($filtros);

        return response()->json([
            'data' => $paginacion->items(),
            ...$resumen,
            'meta' => [
                'current_page' => $paginacion->currentPage(),
                'last_page' => $paginacion->lastPage(),
                'per_page' => $paginacion->perPage(),
                'from' => $paginacion->firstItem(),
                'to' => $paginacion->lastItem(),
                'total' => $paginacion->total(),
            ],
        ]);
    }

    public function kardex(Request $request): JsonResponse
    {
        Gate::authorize('consultar-kardex-materiales');
        $movimientos = MovimientoInventarioMaterial::query()
            ->with(['folioMaterial.folio:id,numero_folio', 'item.cliente.temporada'])
            ->whereHas('folioMaterial.folio', fn ($consulta) => $consulta
                ->where('temporada_id', '=', $this->consultaTemporadaActiva()))
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
                    'cliente' => [
                        'id' => $movimiento->item->cliente->id,
                        'temporada' => [
                            'id' => $movimiento->item->cliente->temporada->id,
                            'codigo' => $movimiento->item->cliente->temporada->codigo,
                            'nombre' => $movimiento->item->cliente->temporada->nombre,
                            'activa' => $movimiento->item->cliente->temporada->activa,
                        ],
                        'codigo' => $movimiento->item->cliente->codigo,
                        'nombre' => $movimiento->item->cliente->nombre,
                        'activo' => $movimiento->item->cliente->activo,
                    ],
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

    private function consultaTemporadaActiva(): Builder
    {
        return Temporada::query()
            ->select('id')
            ->where('activa', true)
            ->limit(1);
    }
}
