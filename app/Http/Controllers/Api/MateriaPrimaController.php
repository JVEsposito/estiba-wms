<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoLoteMateriaPrima;
use App\Enums\TipoEnvaseRomana;
use App\Enums\TipoProductoMateriaPrima;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnularLoteMateriaPrimaRequest;
use App\Http\Requests\AsignarCamaraLoteMateriaPrimaRequest;
use App\Http\Requests\CompletarHidrocoolerMateriaPrimaRequest;
use App\Http\Requests\ConfirmarLoteMateriaPrimaRequest;
use App\Http\Requests\GuardarLoteMateriaPrimaRequest;
use App\Http\Requests\IniciarHidrocoolerMateriaPrimaRequest;
use App\Http\Resources\LoteMateriaPrimaResource;
use App\Models\CalibreValidacion;
use App\Models\Camara;
use App\Models\CsgValidacion;
use App\Models\EspecieValidacion;
use App\Models\LoteMateriaPrima;
use App\Models\SegmentoValidacionMp;
use App\Models\Temporada;
use App\Models\VariedadValidacion;
use App\Services\MateriaPrima\ServicioLoteMateriaPrima;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MateriaPrimaController extends Controller
{
    public function resumen(): JsonResponse
    {
        Gate::authorize('consultar-materia-prima');
        $temporada = Temporada::query()->where('activa', true)->first();
        $base = LoteMateriaPrima::query()
            ->when($temporada, fn (Builder $consulta) => $consulta
                ->where('temporada_id', $temporada->id));

        return response()->json([
            'temporada' => $temporada ? [
                'id' => $temporada->id,
                'codigo' => $temporada->codigo,
                'nombre' => $temporada->nombre,
            ] : null,
            'segmentos_pendientes' => SegmentoValidacionMp::query()
                ->whereIn('estado', ['pendiente_lote', 'lotizacion_parcial'])
                ->whereHas('validacion.recepcion.temporada', fn (Builder $consulta) => $consulta
                    ->where('activa', true))
                ->count(),
            'lotes' => [
                'borradores' => (clone $base)
                    ->where('estado', EstadoLoteMateriaPrima::Borrador->value)->count(),
                'pendientes_hidrocooler' => (clone $base)->whereIn('estado', [
                    EstadoLoteMateriaPrima::PendienteHidrocooler,
                    EstadoLoteMateriaPrima::HidrocoolerEnCurso,
                ])->count(),
                'pendientes_asignacion' => (clone $base)
                    ->where('estado', EstadoLoteMateriaPrima::PendienteAsignacion->value)->count(),
                'asignados_camara' => (clone $base)
                    ->where('estado', EstadoLoteMateriaPrima::AsignadoCamara->value)->count(),
            ],
        ]);
    }

    public function catalogos(): JsonResponse
    {
        Gate::authorize('consultar-materia-prima');
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $especies = EspecieValidacion::query()
            ->where('temporada_id', $temporada->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
        $variedades = VariedadValidacion::query()
            ->where('activo', true)
            ->whereIn('especie_validacion_id', $especies->pluck('id'))
            ->orderBy('nombre')
            ->get(['id', 'especie_validacion_id', 'nombre']);
        $calibres = CalibreValidacion::query()
            ->where('activo', true)
            ->whereIn('especie_validacion_id', $especies->pluck('id'))
            ->orderBy('nombre')
            ->get(['id', 'especie_validacion_id', 'nombre']);

        return response()->json([
            'temporada' => [
                'id' => $temporada->id,
                'codigo' => $temporada->codigo,
                'nombre' => $temporada->nombre,
            ],
            'csg' => CsgValidacion::query()
                ->where('temporada_id', $temporada->id)
                ->where('activo', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'predio']),
            'especies' => $especies->map(fn (EspecieValidacion $especie): array => [
                'id' => $especie->id,
                'nombre' => $especie->nombre,
                'variedades' => $variedades
                    ->where('especie_validacion_id', $especie->id)
                    ->values()
                    ->map->only(['id', 'nombre']),
                'calibres' => $calibres
                    ->where('especie_validacion_id', $especie->id)
                    ->values()
                    ->map->only(['id', 'nombre']),
            ]),
            'tipos_producto' => array_column(TipoProductoMateriaPrima::cases(), 'value'),
            'envases_primarios' => array_column(TipoEnvaseRomana::cases(), 'value'),
            'envases_secundarios' => [
                TipoEnvaseRomana::Totes->value,
                TipoEnvaseRomana::Esponjas->value,
            ],
            'camaras' => Camara::query()
                ->where('contenido', ContenidoCamara::MateriaPrima->value)
                ->where('estado', EstadoCamara::Activa->value)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function segmentosPendientes(): JsonResponse
    {
        Gate::authorize('consultar-materia-prima');
        $segmentos = SegmentoValidacionMp::query()
            ->whereIn('estado', ['pendiente_lote', 'lotizacion_parcial'])
            ->whereHas('validacion.recepcion.temporada', fn (Builder $consulta) => $consulta
                ->where('activa', true))
            ->with([
                'envases',
                'csg',
                'variedad.especie',
                'validacion.recepcion.detallesEnvases',
                'validacion.recepcion.cliente',
                'lotesMateriaPrima' => fn ($consulta) => $consulta
                    ->where('estado', '!=', EstadoLoteMateriaPrima::Anulado->value),
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => $segmentos->map(function (SegmentoValidacionMp $segmento): array {
                $recepcion = $segmento->validacion->recepcion;
                $tipoBase = $recepcion->tipo_envase_calculo_neto;
                $envases = $segmento->envases->map(function ($envase) use ($segmento): array {
                    $tipo = $envase->tipo_envase->value;
                    $reservada = $segmento->lotesMateriaPrima->sum(function (LoteMateriaPrima $lote) use ($tipo): int {
                        $cantidad = $lote->envase_primario->value === $tipo
                            ? $lote->cantidad_envases_primarios
                            : 0;
                        if ($lote->envase_secundario?->value === $tipo) {
                            $cantidad += $lote->cantidad_envases_secundarios;
                        }

                        return $cantidad;
                    });

                    return [
                        'tipo_envase' => $tipo,
                        'cantidad' => $envase->cantidad,
                        'cantidad_reservada' => $reservada,
                        'cantidad_disponible' => max(0, $envase->cantidad - $reservada),
                    ];
                });

                return [
                    'id' => $segmento->id,
                    'secuencia' => $segmento->secuencia,
                    'estado' => $segmento->estado,
                    'motivos' => $segmento->motivos,
                    'csg' => $segmento->csg ? [
                        'id' => $segmento->csg->id,
                        'codigo' => $segmento->csg->codigo,
                        'predio' => $segmento->csg->predio,
                    ] : null,
                    'cuartel' => $segmento->cuartel,
                    'variedad' => $segmento->variedad ? [
                        'id' => $segmento->variedad->id,
                        'nombre' => $segmento->variedad->nombre,
                        'especie_id' => $segmento->variedad->especie_validacion_id,
                    ] : null,
                    'envases' => $envases,
                    'recepcion' => [
                        'id' => $recepcion->id,
                        'numero_recepcion' => $recepcion->numero_recepcion,
                        'numero_guia_despacho' => $recepcion->numero_guia_despacho,
                        'cliente' => [
                            'id' => $recepcion->cliente->id,
                            'codigo' => $recepcion->cliente->codigo,
                            'nombre' => $recepcion->cliente->nombre,
                        ],
                        'peso_neto' => (float) $recepcion->peso_neto,
                        'tipo_envase_calculo_neto' => $tipoBase,
                        'cantidad_envase_calculo_neto' => $recepcion->cantidad_envase_calculo_neto,
                        'peso_neto_por_envase' => (float) $recepcion->peso_neto_por_envase,
                    ],
                ];
            }),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('consultar-materia-prima');
        $temporada = Temporada::query()->where('activa', true)->first();
        $lotes = LoteMateriaPrima::query()
            ->when($temporada, fn (Builder $consulta) => $consulta
                ->where('temporada_id', $temporada->id))
            ->when($request->filled('estado'), fn (Builder $consulta) => $consulta
                ->where('estado', $request->string('estado')->toString()))
            ->when($request->filled('buscar'), function (Builder $consulta) use ($request): void {
                $buscar = '%'.$request->string('buscar')->trim()->toString().'%';
                $consulta->where(function (Builder $filtro) use ($buscar): void {
                    $filtro->where('numero_lote', 'like', $buscar)
                        ->orWhere('ggn', 'like', $buscar)
                        ->orWhere('sdp', 'like', $buscar)
                        ->orWhereHas('recepcion', fn (Builder $recepcion) => $recepcion
                            ->where('numero_recepcion', 'like', $buscar));
                });
            })
            ->with($this->relaciones())
            ->latest()
            ->paginate(100);

        return LoteMateriaPrimaResource::collection($lotes);
    }

    public function show(LoteMateriaPrima $loteMateriaPrima): LoteMateriaPrimaResource
    {
        Gate::authorize('consultar-materia-prima');

        return new LoteMateriaPrimaResource(
            $loteMateriaPrima->load($this->relaciones(conEventos: true)),
        );
    }

    public function store(
        GuardarLoteMateriaPrimaRequest $request,
        ServicioLoteMateriaPrima $servicio,
    ): JsonResponse {
        $lote = $servicio->crear($request->validated(), $request->user());

        return (new LoteMateriaPrimaResource($lote))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        GuardarLoteMateriaPrimaRequest $request,
        LoteMateriaPrima $loteMateriaPrima,
        ServicioLoteMateriaPrima $servicio,
    ): LoteMateriaPrimaResource {
        return new LoteMateriaPrimaResource(
            $servicio->actualizar(
                $loteMateriaPrima,
                $request->validated(),
                $request->user(),
            ),
        );
    }

    public function confirmar(
        ConfirmarLoteMateriaPrimaRequest $request,
        LoteMateriaPrima $loteMateriaPrima,
        ServicioLoteMateriaPrima $servicio,
    ): LoteMateriaPrimaResource {
        $datos = $request->validated();

        return new LoteMateriaPrimaResource(
            $servicio->confirmar(
                $loteMateriaPrima,
                $datos['operacion_id'],
                $datos['version_conocida'],
                $request->user(),
            ),
        );
    }

    public function iniciarHidrocooler(
        IniciarHidrocoolerMateriaPrimaRequest $request,
        LoteMateriaPrima $loteMateriaPrima,
        ServicioLoteMateriaPrima $servicio,
    ): LoteMateriaPrimaResource {
        return new LoteMateriaPrimaResource(
            $servicio->iniciarHidrocooler(
                $loteMateriaPrima,
                $request->validated(),
                $request->user(),
            ),
        );
    }

    public function completarHidrocooler(
        CompletarHidrocoolerMateriaPrimaRequest $request,
        LoteMateriaPrima $loteMateriaPrima,
        ServicioLoteMateriaPrima $servicio,
    ): LoteMateriaPrimaResource {
        return new LoteMateriaPrimaResource(
            $servicio->completarHidrocooler(
                $loteMateriaPrima,
                $request->validated(),
                $request->user(),
            ),
        );
    }

    public function asignarCamara(
        AsignarCamaraLoteMateriaPrimaRequest $request,
        LoteMateriaPrima $loteMateriaPrima,
        ServicioLoteMateriaPrima $servicio,
    ): LoteMateriaPrimaResource {
        return new LoteMateriaPrimaResource(
            $servicio->asignarCamara(
                $loteMateriaPrima,
                $request->validated(),
                $request->user(),
            ),
        );
    }

    public function anular(
        AnularLoteMateriaPrimaRequest $request,
        LoteMateriaPrima $loteMateriaPrima,
        ServicioLoteMateriaPrima $servicio,
    ): LoteMateriaPrimaResource {
        $datos = $request->validated();

        return new LoteMateriaPrimaResource(
            $servicio->anular(
                $loteMateriaPrima,
                $datos['operacion_id'],
                $datos['motivo'],
                $request->user(),
            ),
        );
    }

    /** @return array<int, string> */
    private function relaciones(bool $conEventos = false): array
    {
        $relaciones = [
            'segmento.envases',
            'recepcion',
            'temporada',
            'cliente',
            'csg',
            'especie',
            'variedad',
            'calibre',
            'creadoPor',
            'actualizadoPor',
            'confirmadoPor',
            'anuladoPor',
            'hidrocooler.iniciadoPor',
            'hidrocooler.completadoPor',
            'asignacionCamara.camara',
            'asignacionCamara.asignadoPor',
        ];
        if ($conEventos) {
            $relaciones[] = 'eventos.usuario';
        }

        return $relaciones;
    }
}
