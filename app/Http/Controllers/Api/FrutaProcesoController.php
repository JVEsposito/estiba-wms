<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoLoteMateriaPrima;
use App\Enums\TipoEnvaseRomana;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnularEntregaFrutaProcesoRequest;
use App\Http\Requests\RegistrarEntregaFrutaProcesoRequest;
use App\Http\Resources\FrutaProcesoLoteResource;
use App\Models\EntregaFrutaProceso;
use App\Models\LoteMateriaPrima;
use App\Models\Temporada;
use App\Services\MateriaPrima\ServicioFrutaProceso;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class FrutaProcesoController extends Controller
{
    public function resumen(): JsonResponse
    {
        Gate::authorize('consultar-fruta-proceso');
        $temporada = Temporada::query()->where('activa', true)->first();
        if (! $temporada) {
            return response()->json([
                'temporada' => null,
                'lotes_abiertos' => 0,
                'lotes_completados' => 0,
                'bins_disponibles' => 0,
                'bins_entregados' => 0,
            ]);
        }

        $lotes = $this->consultaBase()
            ->withSum([
                'entregasProceso as cantidad_entregada' => fn (Builder $consulta) => $consulta
                    ->whereNull('anulado_at'),
            ], 'cantidad_envases')
            ->get();

        return response()->json([
            'temporada' => [
                'id' => $temporada->id,
                'codigo' => $temporada->codigo,
                'nombre' => $temporada->nombre,
            ],
            'lotes_abiertos' => $lotes->filter(fn (LoteMateriaPrima $lote): bool => in_array(
                $lote->estado,
                [
                    EstadoLoteMateriaPrima::AsignadoCamara,
                    EstadoLoteMateriaPrima::EntregaParcialProceso,
                ],
                true,
            ))->count(),
            'lotes_completados' => $lotes->filter(
                fn (LoteMateriaPrima $lote): bool => (
                    $lote->estado === EstadoLoteMateriaPrima::EntregadoProceso
                ),
            )->count(),
            'bins_disponibles' => $lotes->sum(fn (LoteMateriaPrima $lote): int => max(
                0,
                $lote->cantidad_envases_primarios - (int) $lote->cantidad_entregada,
            )),
            'bins_entregados' => (int) $lotes->sum('cantidad_entregada'),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('consultar-fruta-proceso');
        $consulta = $this->consultaBase()
            ->when($request->filled('estado'), function (Builder $query) use ($request): void {
                $estado = $request->string('estado')->toString();
                if ($estado === 'abiertos') {
                    $query->whereIn('estado', [
                        EstadoLoteMateriaPrima::AsignadoCamara->value,
                        EstadoLoteMateriaPrima::EntregaParcialProceso->value,
                    ]);
                } elseif ($estado === 'completados') {
                    $query->where('estado', EstadoLoteMateriaPrima::EntregadoProceso->value);
                }
            })
            ->when($request->filled('camara_id'), fn (Builder $query) => $query
                ->whereHas('asignacionCamara', fn (Builder $asignacion) => $asignacion
                    ->where('camara_id', $request->string('camara_id')->toString())))
            ->when($request->filled('buscar'), function (Builder $query) use ($request): void {
                $buscar = '%'.$request->string('buscar')->trim()->toString().'%';
                $query->where(function (Builder $filtro) use ($buscar): void {
                    $filtro->where('numero_lote', 'like', $buscar)
                        ->orWhere('csg_snapshot', 'like', $buscar)
                        ->orWhere('predio', 'like', $buscar)
                        ->orWhereHas('cliente', fn (Builder $cliente) => $cliente
                            ->where('nombre', 'like', $buscar)
                            ->orWhere('codigo', 'like', $buscar))
                        ->orWhereHas('entregasProceso', fn (Builder $entrega) => $entrega
                            ->where('numero_orden', 'like', $buscar));
                });
            })
            ->with($this->relaciones())
            ->orderByRaw("case estado when 'entrega_parcial_proceso' then 0 when 'asignado_camara' then 1 else 2 end")
            ->orderBy('created_at');

        return FrutaProcesoLoteResource::collection(
            $consulta->paginate(min(200, max(1, $request->integer('per_page', 100)))),
        );
    }

    public function show(LoteMateriaPrima $loteMateriaPrima): FrutaProcesoLoteResource
    {
        Gate::authorize('consultar-fruta-proceso');
        abort_unless(
            $loteMateriaPrima->temporada()->where('activa', true)->exists()
            && $loteMateriaPrima->envase_primario === TipoEnvaseRomana::Bins
            && in_array($loteMateriaPrima->estado, $this->estadosProceso(), true),
            Response::HTTP_NOT_FOUND,
        );

        return new FrutaProcesoLoteResource(
            $loteMateriaPrima->load($this->relaciones()),
        );
    }

    public function store(
        RegistrarEntregaFrutaProcesoRequest $request,
        LoteMateriaPrima $loteMateriaPrima,
        ServicioFrutaProceso $servicio,
    ): FrutaProcesoLoteResource {
        Gate::authorize('entregar-fruta-proceso');

        return new FrutaProcesoLoteResource(
            $servicio->registrar(
                $loteMateriaPrima,
                $request->validated(),
                $request->user(),
            ),
        );
    }

    public function anular(
        AnularEntregaFrutaProcesoRequest $request,
        EntregaFrutaProceso $entregaFrutaProceso,
        ServicioFrutaProceso $servicio,
    ): FrutaProcesoLoteResource {
        Gate::authorize('anular-entregas-fruta-proceso');
        $datos = $request->validated();

        return new FrutaProcesoLoteResource(
            $servicio->anular(
                $entregaFrutaProceso,
                $datos['operacion_id'],
                $datos['motivo'],
                $request->user(),
            ),
        );
    }

    private function consultaBase(): Builder
    {
        return LoteMateriaPrima::query()
            ->whereHas('temporada', fn (Builder $consulta) => $consulta->where('activa', true))
            ->where('envase_primario', TipoEnvaseRomana::Bins->value)
            ->whereIn('estado', array_map(
                fn (EstadoLoteMateriaPrima $estado): string => $estado->value,
                $this->estadosProceso(),
            ));
    }

    /** @return array<int, EstadoLoteMateriaPrima> */
    private function estadosProceso(): array
    {
        return [
            EstadoLoteMateriaPrima::AsignadoCamara,
            EstadoLoteMateriaPrima::EntregaParcialProceso,
            EstadoLoteMateriaPrima::EntregadoProceso,
        ];
    }

    /** @return array<int, string> */
    private function relaciones(): array
    {
        return [
            'cliente',
            'recepcion',
            'asignacionCamara.camara',
            'entregasProceso.entregadoPor',
            'entregasProceso.anuladoPor',
            'entregasProceso.dispositivo',
        ];
    }
}
