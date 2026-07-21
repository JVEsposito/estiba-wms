<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoRecepcionRomana;
use App\Enums\TipoEnvaseRomana;
use App\Enums\TipoServicioRomana;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarRecepcionRomanaRequest;
use App\Http\Requests\CerrarRecepcionRomanaRequest;
use App\Http\Requests\ConfirmarIngresoRomanaRequest;
use App\Http\Requests\ConsultarRecepcionesRomanaRequest;
use App\Http\Requests\CrearRecepcionRomanaRequest;
use App\Models\Cliente;
use App\Models\EventoRecepcionRomana;
use App\Models\RecepcionRomana;
use App\Services\Romana\GeneradorAvisoReciboPdf;
use App\Services\Romana\ServicioRecepcionRomana;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RecepcionRomanaController extends Controller
{
    public function catalogos(): JsonResponse
    {
        Gate::authorize('consultar-romana');

        return response()->json([
            'clientes' => Cliente::query()
                ->where('activo', true)
                ->withCount(['catalogosValidacion', 'catalogosMateriales'])
                ->orderBy('nombre')
                ->get()
                ->map(fn (Cliente $cliente): array => [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'codigo' => $cliente->codigo,
                    'codigo_externo' => $cliente->codigo_externo,
                    'presente_en_validacion' => $cliente->catalogos_validacion_count > 0,
                    'presente_en_materiales' => $cliente->catalogos_materiales_count > 0,
                ]),
            'tipos_servicio' => array_map(
                fn (TipoServicioRomana $tipo): array => ['codigo' => $tipo->value, 'nombre' => match ($tipo) {
                    TipoServicioRomana::Almacenaje => 'Almacenaje',
                    TipoServicioRomana::Proceso => 'Proceso',
                    TipoServicioRomana::Prefrio => 'Pre-frío',
                }],
                TipoServicioRomana::cases(),
            ),
            'tipos_envase' => array_map(
                fn (TipoEnvaseRomana $tipo): array => ['codigo' => $tipo->value, 'nombre' => ucfirst($tipo->value)],
                TipoEnvaseRomana::cases(),
            ),
        ]);
    }

    public function index(ConsultarRecepcionesRomanaRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        $base = RecepcionRomana::query();
        if (! empty($filtros['desde'])) {
            $base->whereDate('ingreso_at', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $base->whereDate('ingreso_at', '<=', $filtros['hasta']);
        }
        if (! empty($filtros['buscar'])) {
            $buscar = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($filtros['buscar'])).'%';
            $base->where(function (Builder $consulta) use ($buscar): void {
                $consulta
                    ->where('numero_recepcion', 'like', $buscar)
                    ->orWhere('numero_guia_despacho', 'like', $buscar)
                    ->orWhere('patente_camion', 'like', $buscar)
                    ->orWhere('nombre_conductor', 'like', $buscar)
                    ->orWhere('cliente_nombre_snapshot', 'like', $buscar);
            });
        }

        $resumen = [
            'en_bascula_ingreso' => (clone $base)->where('estado', EstadoRecepcionRomana::EnBasculaIngreso)->count(),
            'en_bascula_salida' => (clone $base)->where('estado', EstadoRecepcionRomana::EnBasculaSalida)->count(),
            'cerradas' => (clone $base)->where('estado', EstadoRecepcionRomana::Cerrado)->count(),
            'peso_neto' => round((float) (clone $base)->where('estado', EstadoRecepcionRomana::Cerrado)->sum('peso_neto'), 2),
        ];

        if (! empty($filtros['estado'])) {
            $base->where('estado', $filtros['estado']);
        }
        $paginacion = $base
            ->with(['creadoPor', 'ingresoConfirmadoPor', 'cerradoPor'])
            ->orderByDesc('ingreso_at')
            ->paginate((int) ($filtros['por_pagina'] ?? 30));

        return response()->json([
            'data' => collect($paginacion->items())
                ->map(fn (RecepcionRomana $recepcion): array => $this->recepcion($recepcion)),
            'resumen' => $resumen,
            'meta' => [
                'pagina_actual' => $paginacion->currentPage(),
                'ultima_pagina' => $paginacion->lastPage(),
                'por_pagina' => $paginacion->perPage(),
                'total' => $paginacion->total(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(
        CrearRecepcionRomanaRequest $request,
        ServicioRecepcionRomana $servicio,
    ): JsonResponse {
        $recepcion = $servicio->crear($request->validated(), $request->user());

        return response()->json(['data' => $this->recepcion($recepcion, true)], Response::HTTP_CREATED);
    }

    public function show(RecepcionRomana $recepcion): JsonResponse
    {
        Gate::authorize('consultar-romana');
        $recepcion->load([
            'creadoPor',
            'ingresoConfirmadoPor',
            'cerradoPor',
            'eventos' => fn ($consulta) => $consulta->with('usuario')->orderBy('ocurrido_at'),
        ]);

        return response()->json(['data' => $this->recepcion($recepcion, true)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(
        ActualizarRecepcionRomanaRequest $request,
        RecepcionRomana $recepcion,
        ServicioRecepcionRomana $servicio,
    ): JsonResponse {
        $recepcion = $servicio->actualizar($recepcion, $request->validated(), $request->user());

        return response()->json(['data' => $this->recepcion($recepcion, true)]);
    }

    public function confirmarIngreso(
        ConfirmarIngresoRomanaRequest $request,
        RecepcionRomana $recepcion,
        ServicioRecepcionRomana $servicio,
    ): JsonResponse {
        $recepcion = $servicio->confirmarIngreso(
            $recepcion,
            (string) $request->validated('operacion_id'),
            $request->user(),
        );

        return response()->json(['data' => $this->recepcion($recepcion, true)]);
    }

    public function cerrar(
        CerrarRecepcionRomanaRequest $request,
        RecepcionRomana $recepcion,
        ServicioRecepcionRomana $servicio,
    ): JsonResponse {
        $recepcion = $servicio->cerrar($recepcion, $request->validated(), $request->user());

        return response()->json(['data' => $this->recepcion($recepcion, true)]);
    }

    public function avisoRecibo(
        RecepcionRomana $recepcion,
        GeneradorAvisoReciboPdf $generador,
    ): Response {
        Gate::authorize('consultar-romana');
        $pdf = $generador->generar($recepcion);
        $nombre = 'aviso-recibo-'.strtolower((string) $recepcion->numero_recepcion).'.pdf';

        return response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @return array<string, mixed> */
    private function recepcion(RecepcionRomana $recepcion, bool $conEventos = false): array
    {
        $datos = [
            'id' => $recepcion->id,
            'numero_recepcion' => $recepcion->numero_recepcion,
            'estado' => $recepcion->estado->value,
            'cliente' => [
                'id' => $recepcion->cliente_id,
                'codigo' => $recepcion->cliente_codigo_snapshot,
                'nombre' => $recepcion->cliente_nombre_snapshot,
            ],
            'tipo_servicio' => $recepcion->tipo_servicio->value,
            'cantidad_envases_declarados' => $recepcion->cantidad_envases_declarados,
            'tipo_envase_declarado' => $recepcion->tipo_envase_declarado->value,
            'numero_guia_despacho' => $recepcion->numero_guia_despacho,
            'patente_camion' => $recepcion->patente_camion,
            'patente_carro' => $recepcion->patente_carro,
            'rut_conductor' => $recepcion->rut_conductor,
            'nombre_conductor' => $recepcion->nombre_conductor,
            'peso_bruto' => (float) $recepcion->peso_bruto,
            'peso_tara' => $recepcion->peso_tara !== null ? (float) $recepcion->peso_tara : null,
            'peso_neto' => $recepcion->peso_neto !== null ? (float) $recepcion->peso_neto : null,
            'ingreso_at' => $recepcion->ingreso_at?->toAtomString(),
            'ingreso_confirmado_at' => $recepcion->ingreso_confirmado_at?->toAtomString(),
            'salida_at' => $recepcion->salida_at?->toAtomString(),
            'observacion' => $recepcion->observacion,
            'observacion_cierre' => $recepcion->observacion_cierre,
            'version' => $recepcion->version,
            'puede_editar' => $recepcion->estado->esEditable(),
            'puede_confirmar_ingreso' => $recepcion->estado === EstadoRecepcionRomana::EnBasculaIngreso,
            'puede_cerrar' => $recepcion->estado === EstadoRecepcionRomana::EnBasculaSalida,
            'aviso_recibo_disponible' => $recepcion->estado === EstadoRecepcionRomana::Cerrado,
            'creado_por' => $this->usuario($recepcion->creadoPor),
            'ingreso_confirmado_por' => $this->usuario($recepcion->ingresoConfirmadoPor),
            'cerrado_por' => $this->usuario($recepcion->cerradoPor),
        ];

        if ($conEventos) {
            $datos['eventos'] = $recepcion->eventos
                ->map(fn (EventoRecepcionRomana $evento): array => [
                    'id' => $evento->id,
                    'tipo' => $evento->tipo->value,
                    'estado_anterior' => $evento->estado_anterior?->value,
                    'estado_nuevo' => $evento->estado_nuevo->value,
                    'ocurrido_at' => $evento->ocurrido_at?->toAtomString(),
                    'usuario' => $this->usuario($evento->usuario),
                    'datos' => $evento->datos,
                ])->values();
        }

        return $datos;
    }

    /** @return array{id: int, nombre: string}|null */
    private function usuario(mixed $usuario): ?array
    {
        return $usuario ? ['id' => $usuario->id, 'nombre' => $usuario->name] : null;
    }
}
