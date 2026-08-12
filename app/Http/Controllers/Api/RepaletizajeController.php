<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularRepaletizajeRequest;
use App\Http\Requests\RegistrarRepaletizajeRequest;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\PersonalAccessToken;
use App\Models\Repaletizaje;
use App\Models\RepaletizajeDetalle;
use App\Services\Validacion\ServicioRepaletizaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepaletizajeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $porPagina = min(100, max(10, $request->integer('per_page', 25)));
        $folio = trim($request->string('folio')->value());
        $paginacion = Repaletizaje::query()
            ->with($this->relaciones())
            ->when($folio !== '', function ($consulta) use ($folio): void {
                $consulta->where(function ($subconsulta) use ($folio): void {
                    $subconsulta
                        ->whereHas('folioResultante', fn ($folios) => $folios
                            ->where('numero_folio', 'like', "%{$folio}%"))
                        ->orWhereHas('resultados.folio', fn ($folios) => $folios
                            ->where('numero_folio', 'like', "%{$folio}%"))
                        ->orWhereHas('detalles.folioOrigen', fn ($folios) => $folios
                            ->where('numero_folio', 'like', "%{$folio}%"));
                });
            })
            ->latest('confirmado_at')
            ->paginate($porPagina)
            ->withQueryString();

        return response()->json([
            'data' => collect($paginacion->items())
                ->map(fn (Repaletizaje $repa): array => $this->recurso($repa))
                ->values(),
            'meta' => [
                'current_page' => $paginacion->currentPage(),
                'last_page' => $paginacion->lastPage(),
                'per_page' => $paginacion->perPage(),
                'total' => $paginacion->total(),
            ],
        ]);
    }

    public function show(
        Repaletizaje $repaletizaje,
        ServicioRepaletizaje $servicio,
    ): JsonResponse {
        return response()->json([
            'data' => $this->recurso($servicio->cargar($repaletizaje)),
        ]);
    }

    public function buscarFolio(string $numeroFolio): JsonResponse
    {
        $numero = mb_strtoupper(trim($numeroFolio));
        $folio = Folio::query()
            ->where('numero_folio', $numero)
            ->whereHas('temporada', fn ($consulta) => $consulta->where('activa', true))
            ->with([
                'ubicacionActual.camara:id,codigo,nombre',
                'ubicacionActual.posicion:id,etiqueta',
            ])
            ->first();

        if (! $folio) {
            return response()->json([
                'existe' => false,
                'numero_folio' => $numero,
            ]);
        }

        return response()->json([
            'existe' => true,
            'id' => $folio->id,
            'numero_folio' => $folio->numero_folio,
            'tipo_bulto' => $folio->tipo_bulto?->value,
            'cantidad_cajas' => (int) ($folio->datos_externos['cantidad_cajas'] ?? 0),
            'activo' => $folio->activo,
            'estado_operacional' => $folio->estado_operacional?->value,
            'condicion_termica' => $folio->condicion_termica?->value,
            'cliente' => $folio->exportadora,
            'especie' => $folio->datos_externos['especie'] ?? null,
            'marca' => $folio->marca,
            'variedad' => $folio->variedad,
            'calibre' => $folio->calibre,
            'envase' => $folio->datos_externos['envase'] ?? null,
            'categoria' => $folio->datos_externos['categoria'] ?? null,
            'csg' => $folio->datos_externos['csg'] ?? null,
            'predio' => $folio->datos_externos['predio'] ?? null,
            'cuartel' => $folio->datos_externos['cuartel'] ?? null,
            'composicion' => $this->composicionFolio($folio),
            'ubicacion' => $folio->ubicacionActual ? [
                'camara' => $folio->ubicacionActual->camara?->only([
                    'id',
                    'codigo',
                    'nombre',
                ]),
                'posicion' => $folio->ubicacionActual->posicion?->only([
                    'id',
                    'etiqueta',
                ]),
            ] : null,
        ]);
    }

    public function store(
        RegistrarRepaletizajeRequest $request,
        ServicioRepaletizaje $servicio,
    ): JsonResponse {
        $usuario = $request->user();
        $token = $usuario->currentAccessToken();
        $dispositivo = $token instanceof PersonalAccessToken && $token->dispositivo_id
            ? Dispositivo::query()->find($token->dispositivo_id)
            : null;
        $repa = $servicio->registrar(
            $request->validated(),
            $usuario,
            $dispositivo,
        );

        return response()->json(['data' => $this->recurso($repa)]);
    }

    public function anular(
        AnularRepaletizajeRequest $request,
        Repaletizaje $repaletizaje,
        ServicioRepaletizaje $servicio,
    ): JsonResponse {
        $repa = $servicio->anular(
            $repaletizaje,
            $request->validated('operacion_id'),
            $request->validated('motivo'),
            $request->user(),
        );

        return response()->json(['data' => $this->recurso($repa)]);
    }

    /** @return array<int, string> */
    private function relaciones(): array
    {
        return [
            'folioResultante',
            'folioConservado',
            'resultados.folio',
            'detalles.folioOrigen',
            'usuario:id,name',
            'dispositivo:id,codigo,nombre',
            'anuladoPor:id,name',
        ];
    }

    /** @return array<string, mixed> */
    private function recurso(Repaletizaje $repa): array
    {
        return [
            'id' => $repa->id,
            'codigo' => $repa->codigo,
            'modalidad' => $repa->modalidad ?? 'consolidacion',
            'tipo_resultado' => $repa->tipo_resultado,
            'estrategia_folio' => $repa->estrategia_folio,
            'cantidad_objetivo' => $repa->cantidad_objetivo,
            'cantidad_resultante' => $repa->cantidad_resultante,
            'condicion_termica' => $repa->condicion_termica,
            'estado' => $repa->estado,
            'campos_mix' => $repa->campos_mix,
            'advertencias' => $repa->snapshot['advertencias'] ?? [],
            'folio_resultante' => $repa->folioResultante ? [
                'id' => $repa->folioResultante->id,
                'numero_folio' => $repa->folioResultante->numero_folio,
                'tipo_bulto' => $repa->folioResultante->tipo_bulto?->value,
                'cantidad_cajas' => (int) (
                    $repa->folioResultante->datos_externos['cantidad_cajas'] ?? 0
                ),
                'estado_operacional' => $repa->folioResultante->estado_operacional?->value,
                'condicion_termica' => $repa->folioResultante->condicion_termica?->value,
                'cliente' => $repa->folioResultante->exportadora,
                'especie' => $repa->folioResultante->datos_externos['especie'] ?? null,
                'marca' => $repa->folioResultante->marca,
                'variedad' => $repa->folioResultante->variedad,
                'calibre' => $repa->folioResultante->calibre,
                'csg' => $repa->folioResultante->datos_externos['csg'] ?? null,
                'predio' => $repa->folioResultante->datos_externos['predio'] ?? null,
                'fecha_embalaje' => $repa->folioResultante->datos_externos['fecha_embalaje'] ?? null,
                'fechas_embalaje' => $repa->folioResultante->datos_externos['fechas_embalaje'] ?? [],
                'composicion' => $this->composicionFolio($repa->folioResultante),
            ] : null,
            'resultados' => $repa->resultados->map(fn ($resultado): array => [
                'id' => $resultado->id,
                'orden' => $resultado->orden,
                'tipo_resultado' => $resultado->tipo_resultado,
                'cantidad_objetivo' => $resultado->cantidad_objetivo,
                'cantidad_resultante' => $resultado->cantidad_resultante,
                'hereda_ubicacion' => $resultado->hereda_ubicacion,
                'folio' => $resultado->folio ? [
                    'id' => $resultado->folio->id,
                    'numero_folio' => $resultado->folio->numero_folio,
                    'tipo_bulto' => $resultado->folio->tipo_bulto?->value,
                    'cantidad_cajas' => (int) ($resultado->folio->datos_externos['cantidad_cajas'] ?? 0),
                    'composicion' => $this->composicionFolio($resultado->folio),
                ] : null,
            ])->values(),
            'origenes' => $repa->detalles->map(
                fn (RepaletizajeDetalle $detalle): array => [
                    'id' => $detalle->id,
                    'orden' => $detalle->orden,
                    'es_folio_conservado' => $detalle->es_folio_conservado,
                    'folio' => [
                        'id' => $detalle->folioOrigen->id,
                        'numero_folio' => $detalle->folioOrigen->numero_folio,
                    ],
                    'cajas_antes' => $detalle->cajas_antes,
                    'cajas_aportadas' => $detalle->cajas_aportadas,
                    'cajas_despues' => $detalle->cajas_despues,
                    'tipo_bulto_antes' => $detalle->tipo_bulto_antes,
                    'tipo_bulto_despues' => $detalle->tipo_bulto_despues,
                    'estado_antes' => $detalle->estado_antes,
                    'estado_despues' => $detalle->estado_despues,
                    'especificaciones' => $detalle->snapshot_antes['especificaciones'] ?? [],
                    'composicion_antes' => $detalle->snapshot_antes['atributos']['datos_externos']['composicion'] ?? [],
                    'composicion_despues' => $detalle->snapshot_despues['atributos']['datos_externos']['composicion'] ?? [],
                ],
            )->values(),
            'operador' => $repa->usuario ? [
                'id' => $repa->usuario->id,
                'nombre' => $repa->usuario->name,
            ] : null,
            'dispositivo' => $repa->dispositivo ? [
                'id' => $repa->dispositivo->id,
                'codigo' => $repa->dispositivo->codigo,
                'nombre' => $repa->dispositivo->nombre,
            ] : null,
            'observacion' => $repa->observacion,
            'confirmado_at' => $repa->confirmado_at?->toAtomString(),
            'anulado_at' => $repa->anulado_at?->toAtomString(),
            'motivo_anulacion' => $repa->motivo_anulacion,
            'puede_anular' => $repa->estado === 'confirmado',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function composicionFolio(Folio $folio): array
    {
        $datos = $folio->datos_externos ?? [];
        $lineas = collect($datos['composicion'] ?? [])
            ->filter(fn (mixed $linea): bool => is_array($linea)
                && array_key_exists('cantidad_cajas', $linea)
                && array_key_exists('csg', $linea));

        if ($lineas->isEmpty() && (int) ($datos['cantidad_cajas'] ?? 0) > 0) {
            $lineas->push([
                'origen_validacion_id' => null,
                'csg' => $datos['csg'] ?? 'SIN CSG',
                'predio' => $datos['predio'] ?? null,
                'fecha_embalaje' => $datos['fecha_embalaje'] ?? null,
                'cantidad_cajas' => (int) $datos['cantidad_cajas'],
            ]);
        }

        return $lineas->map(function (array $linea): array {
            $linea['clave'] = hash('sha256', implode('|', [
                mb_strtoupper(trim((string) ($linea['csg'] ?? ''))),
                mb_strtoupper(trim((string) ($linea['predio'] ?? ''))),
                (string) ($linea['fecha_embalaje'] ?? ''),
            ]));
            $linea['cantidad_cajas'] = (int) $linea['cantidad_cajas'];

            return $linea;
        })->values()->all();
    }
}
