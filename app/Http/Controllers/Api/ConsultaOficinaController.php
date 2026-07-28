<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AsociarProductorCsgRequest;
use App\Http\Resources\ProductorCsgResource;
use App\Models\Cliente;
use App\Models\ConsultaSag;
use App\Models\LoteMateriaPrima;
use App\Models\ProductorCsg;
use App\Services\Consultas\ServicioAsociacionProductorCsg;
use App\Services\Consultas\ServicioConsultaOperacional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConsultaOficinaController extends Controller
{
    public function resumen(): JsonResponse
    {
        Gate::authorize('consultar-oficina-consultas');

        return response()->json([
            'productores' => [
                'total' => ProductorCsg::query()->count(),
                'pendientes_cliente' => ProductorCsg::query()
                    ->whereDoesntHave('clientes', fn ($consulta) => $consulta
                        ->where('clientes_productores_csg.activo', true))
                    ->count(),
                'asociados' => ProductorCsg::query()
                    ->whereHas('clientes', fn ($consulta) => $consulta
                        ->where('clientes_productores_csg.activo', true))
                    ->count(),
            ],
            'lotes' => LoteMateriaPrima::query()->count(),
            'consultas_sag_hoy' => ConsultaSag::query()
                ->whereDate('ocurrido_at', today())
                ->count(),
        ]);
    }

    public function buscar(
        Request $request,
        ServicioConsultaOperacional $servicio,
    ): JsonResponse {
        Gate::authorize('consultar-oficina-consultas');
        $datos = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'tipo' => ['nullable', Rule::in([
                'todos',
                'folios',
                'lotes',
                'productores',
                'recepciones',
            ])],
        ]);

        return response()->json(
            $servicio->buscar($datos['q'], $datos['tipo'] ?? 'todos'),
        );
    }

    public function catalogos(): JsonResponse
    {
        Gate::authorize('consultar-oficina-consultas');

        return response()->json([
            'clientes' => Cliente::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function productores(Request $request): JsonResponse
    {
        Gate::authorize('consultar-oficina-consultas');
        $estado = $request->string('estado')->toString();
        $buscar = $request->string('buscar')->trim()->toString();
        $consulta = ProductorCsg::query()
            ->with('clientes')
            ->when($buscar !== '', function ($query) use ($buscar): void {
                $patron = '%'.$buscar.'%';
                $query->where(function ($filtro) use ($patron): void {
                    $filtro->where('codigo', 'like', $patron)
                        ->orWhere('rut', 'like', $patron)
                        ->orWhere('razon_social', 'like', $patron)
                        ->orWhere('predio', 'like', $patron);
                });
            })
            ->when($estado === 'pendiente_cliente', fn ($query) => $query
                ->whereDoesntHave('clientes', fn ($clientes) => $clientes
                    ->where('clientes_productores_csg.activo', true)))
            ->when($estado === 'asociado', fn ($query) => $query
                ->whereHas('clientes', fn ($clientes) => $clientes
                    ->where('clientes_productores_csg.activo', true)))
            ->latest('ultima_verificacion_at')
            ->paginate(100);

        return ProductorCsgResource::collection($consulta)->response();
    }

    public function productor(ProductorCsg $productorCsg): JsonResponse
    {
        Gate::authorize('consultar-oficina-consultas');
        $productorCsg->load(['clientes', 'catalogosTemporada.temporada']);
        $lotes = LoteMateriaPrima::query()
            ->whereRaw('UPPER(csg_snapshot) = ?', [mb_strtoupper($productorCsg->codigo)])
            ->with(['temporada', 'cliente', 'recepcion', 'asignacionCamara.camara'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (LoteMateriaPrima $lote): array => [
                'id' => $lote->id,
                'numero' => $lote->numero_lote,
                'estado' => $lote->estado->value,
                'cliente' => $lote->cliente?->nombre,
                'recepcion' => $lote->recepcion?->numero_recepcion,
                'especie' => $lote->especie_snapshot,
                'variedad' => $lote->variedad_snapshot,
                'kilos_netos' => (float) $lote->kilos_netos_confirmados,
                'temporada' => $lote->temporada?->codigo,
                'camara' => $lote->asignacionCamara?->camara?->codigo,
            ]);

        return response()->json([
            'productor' => (new ProductorCsgResource($productorCsg))->resolve(request()),
            'lotes' => $lotes,
            'totales' => [
                'lotes' => $lotes->count(),
                'kilos_netos' => round($lotes->sum('kilos_netos'), 3),
            ],
        ]);
    }

    public function asociar(
        AsociarProductorCsgRequest $request,
        ProductorCsg $productorCsg,
        ServicioAsociacionProductorCsg $servicio,
    ): ProductorCsgResource {
        $datos = $request->validated();

        return new ProductorCsgResource(
            $servicio->sincronizar($productorCsg, $datos['cliente_ids'], $request->user()),
        );
    }
}
