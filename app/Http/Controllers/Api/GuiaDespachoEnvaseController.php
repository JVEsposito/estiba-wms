<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoGuiaDespachoEnvase;
use App\Enums\PropiedadEnvase;
use App\Enums\TipoEnvaseRomana;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\GuiaDespachoEnvase;
use App\Models\MovimientoEnvase;
use App\Services\Envases\ServicioGuiaDespachoEnvases;
use App\Services\Temporadas\ServicioTemporadaActiva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class GuiaDespachoEnvaseController extends Controller
{
    public function catalogos(ServicioTemporadaActiva $temporadaActiva): JsonResponse
    {
        Gate::authorize('consultar-cuenta-envases');
        $temporada = $temporadaActiva->obtener();
        $origenes = MovimientoEnvase::query()
            ->where('temporada_id', $temporada->id)
            ->where('signo_existencia', 1)
            ->where('documento_tipo', 'recepcion_romana')
            ->with('cliente:id,codigo,nombre')
            ->orderByDesc('ocurrido_at')
            ->limit(500)
            ->get()
            ->map(function (MovimientoEnvase $origen) use ($temporada): array {
                $ajustes = (int) MovimientoEnvase::query()->where('movimiento_origen_id', $origen->id)
                    ->where('temporada_id', $temporada->id)
                    ->selectRaw('COALESCE(SUM(cantidad * signo_existencia), 0) as saldo')->value('saldo');

                return [
                    'id' => $origen->id,
                    'tipo_envase' => $origen->tipo_envase->value,
                    'propiedad' => $origen->propiedad->value,
                    'disponible' => $origen->cantidad + $ajustes,
                    'documento' => $origen->numero_documento,
                    'cliente' => $origen->cliente ? ['id' => $origen->cliente->id, 'nombre' => $origen->cliente->nombre] : null,
                    'ingreso_at' => $origen->ingreso_at?->toAtomString(),
                ];
            })->filter(fn (array $origen): bool => $origen['disponible'] > 0)->values();

        return response()->json([
            'temporada' => ['id' => $temporada->id, 'codigo' => $temporada->codigo, 'nombre' => $temporada->nombre],
            'clientes' => Cliente::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'tipos_envase' => array_column(TipoEnvaseRomana::cases(), 'value'),
            'propiedades' => array_column(PropiedadEnvase::cases(), 'value'),
            'origenes' => $origenes,
        ]);
    }

    public function index(
        Request $request,
        ServicioTemporadaActiva $temporadaActiva,
    ): JsonResponse
    {
        Gate::authorize('consultar-cuenta-envases');
        $filtros = $request->validate([
            'temporada_id' => ['nullable', 'uuid', 'exists:temporadas,id'],
            'estado' => ['nullable', Rule::enum(EstadoGuiaDespachoEnvase::class)],
        ]);
        $temporadaId = $filtros['temporada_id'] ?? $temporadaActiva->obtener()->id;
        $guias = GuiaDespachoEnvase::query()->with(['cliente', 'temporada', 'detalles', 'creadoPor', 'confirmadoPor', 'anuladoPor'])
            ->where('temporada_id', $temporadaId)
            ->when(! empty($filtros['estado']), fn (Builder $q) => $q->where('estado', $filtros['estado']))
            ->orderByDesc('salida_at')->limit(200)->get();

        return response()->json(['data' => $guias->map(fn (GuiaDespachoEnvase $guia): array => $this->guia($guia))]);
    }

    public function store(Request $request, ServicioGuiaDespachoEnvases $servicio): JsonResponse
    {
        Gate::authorize('gestionar-despacho-envases');
        $datos = $request->validate([
            'operacion_id' => ['required', 'uuid'],
            'cliente_id' => ['required', 'uuid', Rule::exists('clientes', 'id')->where('activo', true)],
            'salida_at' => ['required', 'date'],
            'patente_camion' => ['nullable', 'regex:/^[A-Z0-9]{5,8}$/'],
            'rut_conductor' => ['nullable', 'string', 'max:12'],
            'nombre_conductor' => ['nullable', 'string', 'max:150'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'detalles' => ['required', 'array', 'min:1', 'max:30'],
            'detalles.*.tipo_envase' => ['required', Rule::enum(TipoEnvaseRomana::class)],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'detalles.*.propiedad' => ['required', Rule::enum(PropiedadEnvase::class)],
            'detalles.*.movimiento_origen_id' => ['nullable', 'uuid', 'exists:movimientos_envases,id'],
        ]);
        $guia = $servicio->crear($datos, $request->user());

        return response()->json(['data' => $this->guia($guia)], Response::HTTP_CREATED);
    }

    public function confirmar(GuiaDespachoEnvase $guiaDespachoEnvase, Request $request, ServicioGuiaDespachoEnvases $servicio): JsonResponse
    {
        Gate::authorize('gestionar-despacho-envases');

        return response()->json(['data' => $this->guia($servicio->confirmar($guiaDespachoEnvase, $request->user()))]);
    }

    public function anular(GuiaDespachoEnvase $guiaDespachoEnvase, Request $request, ServicioGuiaDespachoEnvases $servicio): JsonResponse
    {
        Gate::authorize('gestionar-despacho-envases');
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);

        return response()->json(['data' => $this->guia($servicio->anular($guiaDespachoEnvase, $datos['motivo'], $request->user()))]);
    }

    /** @return array<string, mixed> */
    private function guia(GuiaDespachoEnvase $guia): array
    {
        return [
            'id' => $guia->id, 'numero' => $guia->numero, 'estado' => $guia->estado->value,
            'temporada' => ['id' => $guia->temporada_id, 'codigo' => $guia->temporada->codigo],
            'cliente' => ['id' => $guia->cliente_id, 'codigo' => $guia->cliente->codigo, 'nombre' => $guia->cliente->nombre],
            'salida_at' => $guia->salida_at?->toAtomString(), 'patente_camion' => $guia->patente_camion,
            'conductor' => ['rut' => $guia->rut_conductor, 'nombre' => $guia->nombre_conductor],
            'observacion' => $guia->observacion, 'version' => $guia->version,
            'detalles' => $guia->detalles->map(fn ($detalle): array => [
                'id' => $detalle->id, 'tipo_envase' => $detalle->tipo_envase->value,
                'cantidad' => $detalle->cantidad, 'propiedad' => $detalle->propiedad->value,
                'movimiento_origen_id' => $detalle->movimiento_origen_id, 'origen' => $detalle->origen_snapshot,
            ])->values(),
            'creado_por' => $guia->creadoPor?->name, 'confirmado_por' => $guia->confirmadoPor?->name,
            'anulado_por' => $guia->anuladoPor?->name, 'confirmado_at' => $guia->confirmado_at?->toAtomString(),
            'anulado_at' => $guia->anulado_at?->toAtomString(), 'motivo_anulacion' => $guia->motivo_anulacion,
            'puede_confirmar' => $guia->estado === EstadoGuiaDespachoEnvase::Borrador,
            'puede_anular' => $guia->estado === EstadoGuiaDespachoEnvase::Confirmada,
        ];
    }
}
