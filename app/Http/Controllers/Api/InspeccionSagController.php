<?php

namespace App\Http\Controllers\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoLoteInspeccionSag;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\ResultadoInspeccionSag;
use App\Enums\TipoAprobacionSag;
use App\Enums\TipoBulto;
use App\Enums\TipoDestinoSag;
use App\Enums\TipoLoteInspeccionSag;
use App\Http\Controllers\Controller;
use App\Models\BloqueMercado;
use App\Models\Folio;
use App\Models\LoteInspeccionSag;
use App\Models\Pais;
use App\Models\ResultadoDestinoInspeccionSag;
use App\Services\InspeccionSag\ServicioEstadoSagFolio;
use App\Services\InspeccionSag\ServicioInspeccionSag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class InspeccionSagController extends Controller
{
    public function resumen(Request $request): JsonResponse
    {
        $activos = collect(EstadoLoteInspeccionSag::cases())->filter->esActivo()->map->value->all();

        return response()->json([
            'lotes_activos' => LoteInspeccionSag::query()
                ->whereHas('temporada', fn (Builder $consulta): Builder => $consulta->where('activa', true))
                ->whereIn('estado', $activos)->count(),
            'pallets_en_inspeccion' => Folio::query()
                ->whereHas('inspeccionesSag.lote', fn (Builder $consulta): Builder => $consulta
                    ->whereIn('estado', $activos)
                    ->whereHas('temporada', fn (Builder $temporada): Builder => $temporada->where('activa', true)))
                ->count(),
            'finalizados_hoy' => LoteInspeccionSag::query()
                ->whereHas('temporada', fn (Builder $consulta): Builder => $consulta->where('activa', true))
                ->where('estado', EstadoLoteInspeccionSag::Finalizado)
                ->whereDate('finalizado_at', today())
                ->count(),
            'autorizaciones_hoy' => ResultadoDestinoInspeccionSag::query()
                ->where('resultado', ResultadoInspeccionSag::Aprobado)
                ->whereDate('resuelto_at', today())
                ->count(),
        ]);
    }

    public function catalogos(): JsonResponse
    {
        $bloques = BloqueMercado::query()->with('paises:id,iso_alpha2,nombre_es')
            ->where('activo', true)->orderBy('nombre')->get();

        return response()->json([
            'tipos_lote' => collect(TipoLoteInspeccionSag::cases())->map(fn ($tipo): array => [
                'value' => $tipo->value,
                'label' => $tipo === TipoLoteInspeccionSag::Segregacion ? 'Segregación' : 'Cambio de mercado',
            ])->all(),
            'tipos_aprobacion' => collect(TipoAprobacionSag::cases())->map(fn ($tipo): array => [
                'value' => $tipo->value,
                'label' => $tipo->etiqueta(),
            ])->all(),
            'bloques' => $bloques->map(fn (BloqueMercado $bloque): array => [
                'id' => $bloque->id,
                'codigo' => $bloque->codigo,
                'nombre' => $bloque->nombre,
                'paises' => $bloque->paises->map(fn (Pais $pais): array => [
                    'id' => $pais->id,
                    'codigo' => $pais->iso_alpha2,
                    'nombre' => $pais->nombre_es,
                ])->values(),
            ])->values(),
            'paises' => Pais::query()->where('activo', true)->orderBy('nombre_es')->get()
                ->map(fn (Pais $pais): array => [
                    'id' => $pais->id,
                    'codigo' => $pais->iso_alpha2,
                    'nombre' => $pais->nombre_es,
                    'es_iso_oficial' => $pais->es_iso_oficial,
                ]),
        ]);
    }

    public function opcionesFolios(): JsonResponse
    {
        $folios = $this->consultaElegibles()->get([
            'id', 'exportadora', 'variedad', 'condicion_sag_id', 'condicion_termica',
            'fecha_ingreso', 'datos_externos',
        ]);

        return response()->json([
            'clientes' => $folios->pluck('exportadora')->filter()->unique()->sort()->values(),
            'especies' => $folios->pluck('datos_externos')->pluck('especie')->filter()->unique()->sort()->values(),
            'variedades' => $folios->pluck('variedad')->filter()->unique()->sort()->values(),
            'csg' => $folios->pluck('datos_externos')->pluck('csg')->filter()->unique()->sort()->values(),
            'condiciones_termicas' => collect(CondicionTermicaFolio::cases())->map(fn ($condicion): array => [
                'value' => $condicion->value,
                'label' => str($condicion->value)->replace('_', ' ')->title()->toString(),
            ]),
        ]);
    }

    public function folios(Request $request, ServicioEstadoSagFolio $estadoSag): JsonResponse
    {
        $datos = $request->validate([
            'cliente' => ['required', 'string', 'max:150'],
            'especie' => ['required', 'string', 'max:150'],
            'variedad' => ['nullable', 'string', 'max:150'],
            'condicion_sag' => ['nullable', Rule::in(['con', 'sin'])],
            'csg' => ['nullable', 'string', 'max:100'],
            'fecha_ingreso' => ['nullable', 'date'],
            'condicion_termica' => ['nullable', Rule::enum(CondicionTermicaFolio::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $folios = $this->consultaElegibles()
            ->where('exportadora', $datos['cliente'])
            ->where('datos_externos->especie', $datos['especie'])
            ->when($datos['variedad'] ?? null, fn (Builder $consulta, string $valor): Builder => $consulta
                ->where('variedad', $valor))
            ->when(($datos['condicion_sag'] ?? null) === 'con', fn (Builder $consulta): Builder => $consulta
                ->whereNotNull('condicion_sag_id'))
            ->when(($datos['condicion_sag'] ?? null) === 'sin', fn (Builder $consulta): Builder => $consulta
                ->whereNull('condicion_sag_id'))
            ->when($datos['csg'] ?? null, fn (Builder $consulta, string $valor): Builder => $consulta
                ->where('datos_externos->csg', $valor))
            ->when($datos['fecha_ingreso'] ?? null, fn (Builder $consulta, string $valor): Builder => $consulta
                ->whereDate('fecha_ingreso', $valor))
            ->when($datos['condicion_termica'] ?? null, fn (Builder $consulta, string $valor): Builder => $consulta
                ->where('condicion_termica', $valor))
            ->with($this->relacionesFolio())
            ->orderBy('numero_folio')
            ->paginate($datos['per_page'] ?? 50);

        $folios->through(fn (Folio $folio): array => $this->serializarFolio($folio, $estadoSag));

        return response()->json($folios);
    }

    public function index(Request $request, ServicioInspeccionSag $servicio): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoLoteInspeccionSag::class)],
            'tipo' => ['nullable', Rule::enum(TipoLoteInspeccionSag::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $lotes = LoteInspeccionSag::query()
            ->whereHas('temporada', fn (Builder $consulta): Builder => $consulta->where('activa', true))
            ->when($datos['estado'] ?? null, fn (Builder $consulta, string $estado): Builder => $consulta->where('estado', $estado))
            ->when($datos['tipo'] ?? null, fn (Builder $consulta, string $tipo): Builder => $consulta->where('tipo', $tipo))
            ->withCount('folios')
            ->with(['destinos', 'creadoPor:id,name'])
            ->latest()
            ->paginate($datos['per_page'] ?? 25);
        $lotes->through(fn (LoteInspeccionSag $lote): array => $this->serializarLote($lote, false));

        return response()->json($lotes);
    }

    public function show(LoteInspeccionSag $loteInspeccionSag, ServicioInspeccionSag $servicio): JsonResponse
    {
        return response()->json($this->serializarLote($servicio->cargar($loteInspeccionSag), true));
    }

    public function store(Request $request, ServicioInspeccionSag $servicio): JsonResponse
    {
        $datos = $request->validate([
            'operacion_id' => ['nullable', 'uuid'],
            'tipo' => ['required', Rule::enum(TipoLoteInspeccionSag::class)],
            'cantidad_solicitada' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'referencia_correo' => ['nullable', 'string', 'max:250'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'folios' => ['required', 'array', 'min:1', 'max:1000'],
            'folios.*' => ['required', 'uuid', 'distinct'],
            'destinos' => ['required', 'array', 'min:1'],
            'destinos.*.tipo' => ['required', Rule::enum(TipoDestinoSag::class)],
            'destinos.*.id' => ['required', 'uuid'],
        ]);
        $lote = $servicio->crear($datos, $request->user());

        return response()->json($this->serializarLote($lote, true), Response::HTTP_CREATED);
    }

    public function iniciar(Request $request, LoteInspeccionSag $loteInspeccionSag, ServicioInspeccionSag $servicio): JsonResponse
    {
        return response()->json($this->serializarLote($servicio->iniciar($loteInspeccionSag, $request->user()), true));
    }

    public function resolver(
        Request $request,
        LoteInspeccionSag $loteInspeccionSag,
        ResultadoDestinoInspeccionSag $resultado,
        ServicioInspeccionSag $servicio,
    ): JsonResponse {
        $datos = $request->validate([
            'resultado' => ['required', Rule::in([
                ResultadoInspeccionSag::Aprobado->value,
                ResultadoInspeccionSag::Rechazado->value,
                ResultadoInspeccionSag::SinResolucion->value,
            ])],
            'tipo_aprobacion' => ['nullable', Rule::enum(TipoAprobacionSag::class)],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json($this->serializarLote(
            $servicio->resolver($loteInspeccionSag, $resultado, $datos, $request->user()),
            true,
        ));
    }

    public function finalizar(Request $request, LoteInspeccionSag $loteInspeccionSag, ServicioInspeccionSag $servicio): JsonResponse
    {
        return response()->json($this->serializarLote($servicio->finalizar($loteInspeccionSag, $request->user()), true));
    }

    public function cancelar(Request $request, LoteInspeccionSag $loteInspeccionSag, ServicioInspeccionSag $servicio): JsonResponse
    {
        return response()->json($this->serializarLote($servicio->cancelar($loteInspeccionSag, $request->user()), true));
    }

    private function consultaElegibles(): Builder
    {
        $estadosTerminales = [
            EstadoOperacionalFolio::Anulado,
            EstadoOperacionalFolio::RetiradoDefinitivo,
            EstadoOperacionalFolio::Despachado,
            EstadoOperacionalFolio::Agotado,
        ];
        $estadosLoteActivos = collect(EstadoLoteInspeccionSag::cases())->filter->esActivo()->map->value->all();

        return Folio::query()
            ->where('activo', true)
            ->where('tipo_bulto', TipoBulto::Pallet)
            ->whereNotIn('estado_operacional', $estadosTerminales)
            ->whereDoesntHave('material')
            ->whereHas('temporada', fn (Builder $consulta): Builder => $consulta->where('activa', true))
            ->whereHas('ubicacionActual.camara', fn (Builder $consulta): Builder => $consulta
                ->where('contenido', ContenidoCamara::Productos)
                ->where('estado', EstadoCamara::Activa))
            ->whereDoesntHave('inspeccionesSag.lote', fn (Builder $consulta): Builder => $consulta
                ->whereIn('estado', $estadosLoteActivos));
    }

    /** @return array<int, string> */
    private function relacionesFolio(): array
    {
        return [
            'condicionSag:id,codigo,nombre',
            'ubicacionActual.camara:id,codigo,nombre',
            'ubicacionActual.posicion:id,etiqueta',
            'autorizacionesSagActivas',
            'inspeccionesSag.lote',
            'inspeccionesSag.resultados.destino',
        ];
    }

    /** @return array<string, mixed> */
    private function serializarFolio(Folio $folio, ServicioEstadoSagFolio $estadoSag): array
    {
        $datos = $folio->datos_externos ?? [];

        return [
            'id' => $folio->id,
            'folio' => $folio->numero_folio,
            'cliente' => $folio->exportadora,
            'especie' => $datos['especie'] ?? null,
            'variedad' => $folio->variedad,
            'condicion_sag_origen' => $folio->condicionSag?->nombre,
            'csg' => $datos['csg'] ?? null,
            'fecha_ingreso' => $folio->fecha_ingreso?->toDateString(),
            'condicion_termica' => $folio->condicion_termica->value,
            'cantidad_cajas' => $datos['cantidad_cajas'] ?? null,
            'camara' => $folio->ubicacionActual?->camara?->codigo,
            'posicion' => $folio->ubicacionActual?->posicion?->etiqueta,
            'sag' => $estadoSag->resumir($folio),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarLote(LoteInspeccionSag $lote, bool $detalle): array
    {
        $base = [
            'id' => $lote->id,
            'codigo' => $lote->codigo,
            'tipo' => $lote->tipo->value,
            'estado' => $lote->estado->value,
            'cantidad_solicitada' => $lote->cantidad_solicitada,
            'cantidad_folios' => $lote->folios_count ?? $lote->folios->count(),
            'referencia_correo' => $lote->referencia_correo,
            'observacion' => $lote->observacion,
            'creado_por' => $lote->creadoPor?->name,
            'creado_at' => $lote->created_at?->toAtomString(),
            'iniciado_at' => $lote->iniciado_at?->toAtomString(),
            'finalizado_at' => $lote->finalizado_at?->toAtomString(),
            'destinos' => $lote->destinos->map(fn ($destino): array => [
                'id' => $destino->id,
                'tipo' => $destino->tipo_destino->value,
                'codigo' => $destino->destino_snapshot['codigo'] ?? null,
                'nombre' => $destino->destino_snapshot['nombre'] ?? null,
                'miembros' => $destino->miembros_snapshot,
            ])->values(),
        ];

        if (! $detalle) {
            return $base;
        }

        return $base + [
            'folios' => $lote->folios->map(fn ($asignacion): array => [
                'id' => $asignacion->id,
                'estado' => $asignacion->estado->value,
                'estado_sag_anterior' => $asignacion->estado_sag_anterior,
                'folio' => [
                    'id' => $asignacion->folio->id,
                    'numero' => $asignacion->folio->numero_folio,
                    'cliente' => $asignacion->folio->exportadora,
                    'especie' => $asignacion->folio->datos_externos['especie'] ?? null,
                    'variedad' => $asignacion->folio->variedad,
                    'camara' => $asignacion->folio->ubicacionActual?->camara?->codigo,
                    'posicion' => $asignacion->folio->ubicacionActual?->posicion?->etiqueta,
                ],
                'resultados' => $asignacion->resultados->map(fn ($resultado): array => [
                    'id' => $resultado->id,
                    'destino' => $resultado->destino->destino_snapshot,
                    'resultado' => $resultado->resultado->value,
                    'tipo_aprobacion' => $resultado->tipo_aprobacion?->value,
                    'observacion' => $resultado->observacion,
                    'resuelto_at' => $resultado->resuelto_at?->toAtomString(),
                ])->values(),
            ])->values(),
        ];
    }
}
