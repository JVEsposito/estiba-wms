<?php

namespace App\Services\Cargas;

use App\Enums\EstadoCarga;
use App\Enums\EstadoCargaFolio;
use App\Enums\EstadoManiobraOperacional;
use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoPosicion;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\ModoBandaOperacional;
use App\Enums\PrioridadCarga;
use App\Enums\PrioridadOperacional;
use App\Enums\TipoBulto;
use App\Enums\TipoEventoCarga;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPasoManiobra;
use App\Enums\TipoPlanOperacional;
use App\Models\BandaOperacional;
use App\Models\Carga;
use App\Models\CargaFolio;
use App\Models\EventoCarga;
use App\Models\Movimiento;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\TareaMovimiento;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Services\Estiba\ServicioManiobrasOperacionales;
use App\Services\Estiba\ServicioPlanesOperacionales;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServicioPlanConcentracionCarga
{
    private const REFERENCIA = 'carga_concentracion';

    public function __construct(
        private readonly CalculadorConcentracionCarga $calculador,
        private readonly ServicioPlanesOperacionales $planes,
        private readonly ServicioManiobrasOperacionales $maniobras,
    ) {}

    public function sincronizar(Carga $carga, User $usuario): ?PlanOperacional
    {
        if (! config('planificador.generacion_automatica')
            || config('planificador.mode') === 'off') {
            return $this->suspenderTrabajoDirigido(
                $carga,
                $usuario,
                'Planificador apagado: se retiró la concentración reversible de la bandeja.',
            );
        }

        return DB::transaction(function () use ($carga, $usuario): ?PlanOperacional {
            $carga = Carga::query()
                ->with(['temporada', 'camaraObjetivo', 'presenciaAndenActiva'])
                ->lockForUpdate()
                ->findOrFail($carga->id);
            $plan = $this->planExistente($carga->id, bloquear: true);

            if (! $carga->temporada?->activa
                || ! in_array($carga->estado, EstadoCarga::visiblesEnOperacion(), true)) {
                $this->cancelarObjetivoFinalizado(
                    $plan,
                    $usuario,
                    'La carga ya no se encuentra disponible para concentración.',
                );

                return $plan?->refresh();
            }

            $asignaciones = $this->asignaciones($carga);
            if ($asignaciones->isEmpty()) {
                $this->cancelarObjetivoFinalizado(
                    $plan,
                    $usuario,
                    'La carga ya no posee pallets completos vigentes para concentración.',
                );

                return $plan?->refresh();
            }

            $analisisGeneral = $this->calculador->analizar($asignaciones);
            $camaraObjetivoId = $carga->camara_objetivo_id
                ?? ($analisisGeneral['grupo_principal']['camara']['id'] ?? null);

            if ($camaraObjetivoId === null) {
                return $plan?->refresh();
            }

            $analisis = $this->calculador->analizar($asignaciones, $camaraObjetivoId);
            $necesarios = max(
                0,
                (int) ceil(
                    ($analisis['total'] * CalculadorConcentracionCarga::UMBRAL_PORCENTAJE) / 100,
                ) - (int) $analisis['concentrados'],
            );
            $geometriaHash = $this->geometriaHash($camaraObjetivoId, $analisis);

            if (config('planificador.mode') === 'shadow') {
                if ($plan) {
                    $this->cancelarReversibles(
                        $plan,
                        $usuario,
                        'Modo shadow: la recomendación se audita sin dirigir trabajo.',
                    );
                }
                $this->registrarShadow(
                    $carga,
                    $usuario,
                    $analisis,
                    $camaraObjetivoId,
                    $necesarios,
                );

                return $plan?->refresh();
            }
            if (config('planificador.mode') !== 'guided'
                || config('planificador.compute') !== 'tablet') {
                return $plan?->refresh();
            }

            if ($carga->presenciaAndenActiva) {
                if ($plan) {
                    $this->cancelarReversibles(
                        $plan,
                        $usuario,
                        'Camión presente en andén: despacho directo tiene prioridad sobre concentración.',
                    );
                    $this->actualizarContexto(
                        $plan,
                        $carga,
                        $analisis,
                        $camaraObjetivoId,
                        $geometriaHash,
                        suspendidoPorAnden: true,
                    );
                }

                return $plan?->refresh();
            }

            if ($analisis['cumple_umbral']) {
                if ($plan) {
                    $this->cancelarReversibles(
                        $plan,
                        $usuario,
                        'La carga ya alcanzó el umbral de concentración.',
                    );
                    $this->actualizarContexto(
                        $plan,
                        $carga,
                        $analisis,
                        $camaraObjetivoId,
                        $geometriaHash,
                    );
                    $this->completarSiCorresponde($plan, $usuario);
                }

                return $plan?->refresh();
            }

            $plan = $this->asegurarPlan(
                $plan,
                $carga,
                $usuario,
                $analisis,
                $camaraObjetivoId,
                $geometriaHash,
            );
            $candidatos = $this->candidatos(
                $carga,
                $asignaciones,
                $analisis,
                $camaraObjetivoId,
                $geometriaHash,
            );
            $this->sincronizarManiobras(
                $plan,
                $usuario,
                $candidatos,
                min(
                    (int) config('planificador.frontier_max', 4),
                    max(1, $necesarios),
                ),
                $geometriaHash,
            );
            $this->actualizarContexto(
                $plan,
                $carga,
                $analisis,
                $camaraObjetivoId,
                $geometriaHash,
            );

            return $plan->refresh();
        }, attempts: 3);
    }

    /**
     * Recalcula únicamente las cargas cuyo grafo pudo cambiar por el folio
     * movido o por las bandas de origen/destino afectadas.
     */
    public function sincronizarTrasMovimiento(Movimiento $movimiento, User $usuario): void
    {
        if (! config('planificador.generacion_automatica')
            || config('planificador.mode') === 'off') {
            return;
        }

        $cargaIds = CargaFolio::query()
            ->where('folio_id', $movimiento->folio_id)
            ->whereHas('reservaActiva')
            ->pluck('carga_id');
        $posiciones = Posicion::query()
            ->whereIn('id', array_values(array_filter([
                $movimiento->posicion_origen_id,
                $movimiento->posicion_destino_id,
            ])))
            ->get(['id', 'camara_id', 'banda', 'nivel']);

        foreach ($posiciones as $posicion) {
            $foliosAfectados = UbicacionActual::query()
                ->whereHas('posicion', fn ($consulta) => $consulta
                    ->where('camara_id', $posicion->camara_id)
                    ->where('banda', $posicion->banda)
                    ->where('nivel', $posicion->nivel))
                ->pluck('folio_id');

            if ($foliosAfectados->isEmpty()) {
                continue;
            }

            $cargaIds = $cargaIds->merge(
                CargaFolio::query()
                    ->whereIn('folio_id', $foliosAfectados)
                    ->whereHas('reservaActiva')
                    ->pluck('carga_id'),
            );
        }

        $cargas = Carga::query()
            ->whereIn('id', $cargaIds->unique()->values())
            ->get();

        foreach ($cargas as $carga) {
            $this->sincronizar($carga, $usuario);
        }
    }

    /** @return Collection<int, CargaFolio> */
    private function asignaciones(Carga $carga): Collection
    {
        return CargaFolio::query()
            ->where('carga_id', $carga->id)
            ->whereIn('estado', [
                EstadoCargaFolio::Pendiente->value,
                EstadoCargaFolio::ConIncidencia->value,
                EstadoCargaFolio::EnAnden->value,
            ])
            ->whereHas('reservaActiva')
            ->whereHas('folio', fn ($consulta) => $consulta
                ->where('activo', true)
                ->where('tipo_bulto', TipoBulto::Pallet->value))
            ->with('folio.ubicacionActual.posicion.camara')
            ->orderBy('asignado_at')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  Collection<int, CargaFolio>  $asignaciones
     * @param  array<string, mixed>  $analisis
     * @return Collection<int, array<string, mixed>>
     */
    private function candidatos(
        Carga $carga,
        Collection $asignaciones,
        array $analisis,
        string $camaraObjetivoId,
        string $geometriaHash,
    ): Collection {
        $grupoIds = collect($analisis['grupo_principal_folio_ids']);
        $puntos = collect($analisis['grupo_principal_puntos']);
        $destinosLibres = $this->destinosLibresVecinos($camaraObjetivoId, $puntos);
        if ($destinosLibres->isEmpty()) {
            return collect();
        }

        $pendientes = $asignaciones
            ->where('estado', EstadoCargaFolio::Pendiente)
            ->filter(fn (CargaFolio $asignacion): bool => $asignacion
                ->folio
                ?->ubicacionActual
                ?->posicion !== null)
            ->reject(fn (CargaFolio $asignacion): bool => $grupoIds->contains($asignacion->folio_id))
            ->values();
        if ($pendientes->isEmpty()) {
            return collect();
        }

        $camaraIds = $pendientes
            ->map(fn (CargaFolio $asignacion): string => $asignacion
                ->folio
                ->ubicacionActual
                ->posicion
                ->camara_id)
            ->unique()
            ->values();
        $ocupaciones = UbicacionActual::query()
            ->whereHas('posicion', fn ($consulta) => $consulta->whereIn('camara_id', $camaraIds))
            ->with(['folio.asignacionCargaActual', 'posicion'])
            ->get();

        $rutas = $pendientes
            ->map(function (CargaFolio $asignacion) use (
                $ocupaciones,
                $camaraObjetivoId,
                $analisis,
                $geometriaHash,
            ): array {
                $bloqueadores = $this->bloqueadores($asignacion, $ocupaciones);

                return [
                    'asignacion' => $asignacion,
                    'bloqueadores' => $bloqueadores,
                    'distancia' => $this->distanciaAlGrupo($asignacion, $analisis),
                    'candidato' => $this->candidatoConcentracion(
                        $asignacion,
                        $camaraObjetivoId,
                        $analisis,
                        $geometriaHash,
                    ),
                ];
            })
            ->sortBy(fn (array $ruta): string => sprintf(
                '%01d:%08d:%s',
                $ruta['asignacion']->folio->ubicacionActual->posicion->camara_id === $camaraObjetivoId ? 0 : 1,
                $ruta['distancia'],
                $ruta['asignacion']->folio->numero_folio,
            ))
            ->values();

        $directos = $rutas
            ->filter(fn (array $ruta): bool => $ruta['bloqueadores']->isEmpty())
            ->map(fn (array $ruta): array => $this->maniobraDirecta(
                $ruta['candidato'],
                $carga,
                $geometriaHash,
            ))
            ->values();
        if ($directos->isNotEmpty()) {
            if ($puntos->isEmpty()) {
                return $directos->take(1)->values();
            }

            return $directos
                ->take($destinosLibres->count())
                ->values();
        }

        return $rutas
            ->map(function (array $ruta) use ($carga, $grupoIds, $geometriaHash): ?array {
                $bloqueadores = $ruta['bloqueadores'];
                if ($bloqueadores->isEmpty()
                    || $bloqueadores->contains(fn (UbicacionActual $bloqueador): bool => ! $bloqueador->folio?->activo
                        || $bloqueador->folio->tipo_bulto !== TipoBulto::Pallet
                        || $grupoIds->contains($bloqueador->folio_id))) {
                    return null;
                }

                return $this->maniobraConBloqueadores(
                    $bloqueadores,
                    $ruta['asignacion'],
                    $carga,
                    $geometriaHash,
                    $ruta['candidato'],
                );
            })
            ->filter()
            ->unique(fn (array $candidato): string => $candidato['candidate_key'])
            ->values();
    }

    /** @return Collection<int, UbicacionActual> */
    private function bloqueadores(CargaFolio $asignacion, Collection $ocupaciones): Collection
    {
        $posicion = $asignacion->folio->ubicacionActual->posicion;

        return $ocupaciones
            ->filter(function (UbicacionActual $ubicacion) use ($asignacion, $posicion): bool {
                $otra = $ubicacion->posicion;

                return $ubicacion->folio_id !== $asignacion->folio_id
                    && $otra->camara_id === $posicion->camara_id
                    && $otra->nivel === $posicion->nivel
                    && $otra->banda === $posicion->banda
                    && $otra->posicion > $posicion->posicion;
            })
            ->sortByDesc(fn (UbicacionActual $ubicacion): int => $ubicacion->posicion->posicion)
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $puntos
     * @return Collection<int, Posicion>
     */
    private function destinosLibresVecinos(string $camaraId, Collection $puntos): Collection
    {
        $bandas = BandaOperacional::query()
            ->where('camara_id', $camaraId)
            ->where('modo', ModoBandaOperacional::Operativa->value)
            ->get()
            ->filter(fn (BandaOperacional $banda): bool => in_array(
                'transito_pt',
                $banda->usos_permitidos ?? [],
                true,
            ))
            ->pluck('numero')
            ->flip();

        return Posicion::query()
            ->where('camara_id', $camaraId)
            ->where('estado', EstadoPosicion::Activa->value)
            ->whereDoesntHave('ubicacionActual')
            ->whereDoesntHave('reservaTareaActiva')
            ->get()
            ->filter(function (Posicion $posicion) use ($bandas, $puntos): bool {
                if (! $bandas->has($posicion->banda)) {
                    return false;
                }
                if ($puntos->isEmpty()) {
                    return true;
                }

                return $puntos->contains(
                    fn (array $punto): bool => $this->sonPuntosVecinos($punto, [
                        'banda' => $posicion->banda,
                        'posicion' => $posicion->posicion,
                        'nivel' => $posicion->nivel,
                    ]),
                );
            })
            ->values();
    }

    /** @param array<string, mixed> $analisis */
    private function distanciaAlGrupo(CargaFolio $asignacion, array $analisis): int
    {
        $posicion = $asignacion->folio->ubicacionActual->posicion;
        $puntos = collect($analisis['grupo_principal_puntos']);
        if ($puntos->isEmpty()) {
            return 0;
        }

        return (int) $puntos->min(function (array $punto) use ($posicion): int {
            return abs($posicion->nivel - $punto['nivel']) * 10000
                + abs($posicion->banda - $punto['banda']) * 100
                + abs($posicion->posicion - $punto['posicion']);
        });
    }

    /** @return array<string, mixed> */
    private function candidatoConcentracion(
        CargaFolio $asignacion,
        string $camaraObjetivoId,
        array $analisis,
        string $geometriaHash,
    ): array {
        $folio = $asignacion->folio;
        $origen = $folio->ubicacionActual->posicion;
        $tipo = $origen->camara_id === $camaraObjetivoId
            ? TipoMovimiento::Reubicacion
            : TipoMovimiento::TrasladoEntreCamaras;

        return [
            'candidate_key' => "concentrar:{$folio->id}",
            'folio_id' => $folio->id,
            'tipo_movimiento' => $tipo,
            'camara_origen_id' => $origen->camara_id,
            'posicion_origen_id' => $origen->id,
            'camara_destino_id' => $camaraObjetivoId,
            'posicion_destino_id' => null,
            'instruccion' => sprintf(
                'Integrar %s al grupo principal de la carga (%d%% → objetivo %d%%).',
                $folio->numero_folio,
                $analisis['porcentaje'],
                CalculadorConcentracionCarga::UMBRAL_PORCENTAJE,
            ),
            'contexto' => [
                'candidate_key' => "concentrar:{$folio->id}",
                'tipo_decision' => 'concentrar_carga',
                'carga_folio_id' => $asignacion->id,
                'camara_objetivo_id' => $camaraObjetivoId,
                'concentracion_actual' => $analisis['porcentaje'],
                'concentracion_umbral' => CalculadorConcentracionCarga::UMBRAL_PORCENTAJE,
                'concentracion_geometry_hash' => $geometriaHash,
                'concentracion_puntos' => $analisis['grupo_principal_puntos'],
                'cliente' => $folio->exportadora,
                'marca' => $folio->marca,
            ],
        ];
    }

    /** @param array<string, mixed> $paso */
    private function maniobraDirecta(
        array $paso,
        Carga $carga,
        string $geometriaHash,
    ): array {
        return [
            'candidate_key' => $paso['candidate_key'],
            'titulo' => "Concentrar {$carga->codigo}",
            'motivo' => 'Movimiento directo que amplía el grupo principal de la carga.',
            'beneficio_estimado' => 1000,
            'riesgo_operacional' => 0,
            'contexto' => [
                'tipo_objetivo' => TipoPlanOperacional::ConcentracionCarga->value,
                'concentracion_geometry_hash' => $geometriaHash,
                'blockers' => 0,
                'movimientos_totales' => 1,
                'cerrable' => true,
            ],
            'bloqueos_banda' => [],
            'pasos' => [[
                ...$paso,
                'tipo_paso_maniobra' => TipoPasoManiobra::MovimientoPermanente,
            ]],
        ];
    }

    /**
     * @param  Collection<int, UbicacionActual>  $bloqueadores
     * @param  array<string, mixed>  $pasoObjetivo
     * @return array<string, mixed>
     */
    private function maniobraConBloqueadores(
        Collection $bloqueadores,
        CargaFolio $habilitada,
        Carga $carga,
        string $geometriaHash,
        array $pasoObjetivo,
    ): array {
        $pasos = [];
        $temporales = [];
        $destinosUsados = collect();

        foreach ($bloqueadores as $bloqueador) {
            $destinoUtil = $this->destinoUtilBloqueador(
                $bloqueador,
                $carga,
                $destinosUsados,
            );
            if ($destinoUtil) {
                $destinosUsados->push($destinoUtil->id);
                $pasos[] = $this->pasoBlockerDestinoUtil(
                    $bloqueador,
                    $destinoUtil,
                    $carga,
                    $habilitada,
                    $geometriaHash,
                );

                continue;
            }

            $temporales[] = $bloqueador;
            $pasos[] = $this->pasoExtraccionTemporal(
                $bloqueador,
                $carga,
                $habilitada,
                $geometriaHash,
            );
        }

        $pasos[] = [
            ...$pasoObjetivo,
            'tipo_paso_maniobra' => TipoPasoManiobra::MovimientoPermanente,
            'contexto' => [
                ...$pasoObjetivo['contexto'],
                'blockers_resueltos' => $bloqueadores->count(),
            ],
        ];

        $profundidadResultante = (int) $habilitada
            ->folio
            ->ubicacionActual
            ->posicion
            ->posicion;
        foreach (array_values(array_reverse($temporales)) as $indice => $bloqueador) {
            $pasos[] = $this->pasoRetornoBanda(
                $bloqueador,
                $carga,
                $habilitada,
                $geometriaHash,
                $profundidadResultante + $indice,
            );
        }

        $blockerIds = $bloqueadores->pluck('folio_id')->sort()->values()->implode(':');
        $clave = 'maniobra_concentracion:'.hash(
            'sha256',
            "{$habilitada->folio_id}:{$blockerIds}:{$geometriaHash}",
        );
        $bloqueosBanda = $temporales !== []
            ? collect($temporales)
                ->map(fn (UbicacionActual $ubicacion): array => [
                    'camara_id' => $ubicacion->posicion->camara_id,
                    'banda' => $ubicacion->posicion->banda,
                    'nivel' => $ubicacion->posicion->nivel,
                ])
                ->unique(fn (array $item): string => implode(':', $item))
                ->values()
                ->all()
            : [];

        return [
            'candidate_key' => $clave,
            'titulo' => "Despejar y concentrar {$carga->codigo}",
            'motivo' => sprintf(
                'Resolver %d blocker(s), integrar %s y cerrar físicamente la banda.',
                $bloqueadores->count(),
                $habilitada->folio->numero_folio,
            ),
            'beneficio_estimado' => 1000 + (($bloqueadores->count() - count($temporales)) * 300),
            'riesgo_operacional' => count($temporales) * 25,
            'contexto' => [
                'tipo_objetivo' => TipoPlanOperacional::ConcentracionCarga->value,
                'concentracion_geometry_hash' => $geometriaHash,
                'folio_objetivo_id' => $habilitada->folio_id,
                'blockers' => $bloqueadores->count(),
                'blockers_destino_util' => $bloqueadores->count() - count($temporales),
                'blockers_retorno' => count($temporales),
                'movimientos_totales' => count($pasos),
                'cerrable' => true,
            ],
            'bloqueos_banda' => $bloqueosBanda,
            'pasos' => $pasos,
        ];
    }

    private function pasoExtraccionTemporal(
        UbicacionActual $bloqueador,
        Carga $carga,
        CargaFolio $habilitada,
        string $geometriaHash,
    ): array {
        $posicion = $bloqueador->posicion;

        return [
            'folio_id' => $bloqueador->folio_id,
            'tipo_movimiento' => TipoMovimiento::Retiro,
            'tipo_paso_maniobra' => TipoPasoManiobra::ExtraccionTemporal,
            'camara_origen_id' => $posicion->camara_id,
            'posicion_origen_id' => $posicion->id,
            'camara_destino_id' => null,
            'posicion_destino_id' => null,
            'instruccion' => "Retirar temporalmente {$bloqueador->folio->numero_folio}; no abandonar la maniobra.",
            'contexto' => [
                'tipo_decision' => 'extraccion_temporal_blocker',
                'carga_id' => $carga->id,
                'habilita_folio_id' => $habilitada->folio_id,
                'camara_retorno_id' => $posicion->camara_id,
                'banda_retorno' => $posicion->banda,
                'nivel_retorno' => $posicion->nivel,
                'posicion_original' => $posicion->posicion,
                'concentracion_geometry_hash' => $geometriaHash,
            ],
        ];
    }

    private function pasoRetornoBanda(
        UbicacionActual $bloqueador,
        Carga $carga,
        CargaFolio $habilitada,
        string $geometriaHash,
        int $profundidadResultante,
    ): array {
        $posicion = $bloqueador->posicion;

        return [
            'folio_id' => $bloqueador->folio_id,
            'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
            'tipo_paso_maniobra' => TipoPasoManiobra::RetornoBanda,
            'camara_origen_id' => null,
            'posicion_origen_id' => null,
            'camara_destino_id' => $posicion->camara_id,
            'posicion_destino_id' => null,
            'instruccion' => "Devolver {$bloqueador->folio->numero_folio} a la profundidad resultante de la banda.",
            'contexto' => [
                'tipo_decision' => 'retorno_blocker_banda',
                'carga_id' => $carga->id,
                'habilita_folio_id' => $habilitada->folio_id,
                'camara_retorno_id' => $posicion->camara_id,
                'banda_retorno' => $posicion->banda,
                'nivel_retorno' => $posicion->nivel,
                'posicion_original' => $posicion->posicion,
                'profundidad_resultante' => $profundidadResultante,
                'concentracion_geometry_hash' => $geometriaHash,
            ],
        ];
    }

    private function pasoBlockerDestinoUtil(
        UbicacionActual $bloqueador,
        Posicion $destino,
        Carga $carga,
        CargaFolio $habilitada,
        string $geometriaHash,
    ): array {
        $origen = $bloqueador->posicion;

        return [
            'folio_id' => $bloqueador->folio_id,
            'tipo_movimiento' => $origen->camara_id === $destino->camara_id
                ? TipoMovimiento::Reubicacion
                : TipoMovimiento::TrasladoEntreCamaras,
            'tipo_paso_maniobra' => TipoPasoManiobra::MovimientoPermanente,
            'camara_origen_id' => $origen->camara_id,
            'posicion_origen_id' => $origen->id,
            'camara_destino_id' => $destino->camara_id,
            'posicion_destino_id' => $destino->id,
            'instruccion' => "Mover {$bloqueador->folio->numero_folio} a un destino útil; no requiere retorno.",
            'contexto' => [
                'tipo_decision' => 'blocker_destino_util',
                'beneficio_secundario' => 'concentracion_otra_carga',
                'destino_precalculado_inmutable' => true,
                'carga_id' => $carga->id,
                'habilita_folio_id' => $habilitada->folio_id,
                'concentracion_geometry_hash' => $geometriaHash,
            ],
        ];
    }

    private function destinoUtilBloqueador(
        UbicacionActual $bloqueador,
        Carga $cargaObjetivo,
        Collection $destinosUsados,
    ): ?Posicion {
        $asignacion = $bloqueador->folio->asignacionCargaActual;
        if (! $asignacion || $asignacion->carga_id === $cargaObjetivo->id) {
            return null;
        }

        $otraCarga = Carga::query()->find($asignacion->carga_id);
        if (! $otraCarga || ! in_array($otraCarga->estado, EstadoCarga::visiblesEnOperacion(), true)) {
            return null;
        }
        $asignaciones = $this->asignaciones($otraCarga);
        if ($asignaciones->isEmpty()) {
            return null;
        }
        $analisisGeneral = $this->calculador->analizar($asignaciones);
        $camaraObjetivoId = $otraCarga->camara_objetivo_id
            ?? ($analisisGeneral['grupo_principal']['camara']['id'] ?? null);
        if (! $camaraObjetivoId) {
            return null;
        }
        $analisis = $this->calculador->analizar($asignaciones, $camaraObjetivoId);

        return $this->destinosLibresVecinos(
            $camaraObjetivoId,
            collect($analisis['grupo_principal_puntos']),
        )->first(fn (Posicion $posicion): bool => ! $destinosUsados->contains($posicion->id));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidatos
     */
    private function sincronizarManiobras(
        PlanOperacional $plan,
        User $usuario,
        Collection $candidatos,
        int $maxActivas,
        string $geometriaHash,
    ): void {
        $deseados = $candidatos->keyBy('candidate_key');
        $activas = $plan->maniobras()
            ->whereIn('estado', [
                EstadoManiobraOperacional::Pendiente->value,
                EstadoManiobraOperacional::EnEjecucion->value,
                EstadoManiobraOperacional::PausadaDiscrepancia->value,
            ])
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($activas as $maniobra) {
            if ($maniobra->estado === EstadoManiobraOperacional::PausadaDiscrepancia) {
                continue;
            }
            $mismaGeometria = ($maniobra->contexto['concentracion_geometry_hash'] ?? null)
                === $geometriaHash;
            if ($deseados->has($maniobra->candidate_key) && $mismaGeometria) {
                continue;
            }

            $this->maniobras->cancelarReversible(
                $maniobra,
                $usuario,
                'La geometría o la frontera de concentración cambió.',
            );
        }

        $activas = $plan->maniobras()
            ->whereIn('estado', [
                EstadoManiobraOperacional::Pendiente->value,
                EstadoManiobraOperacional::EnEjecucion->value,
                EstadoManiobraOperacional::PausadaDiscrepancia->value,
            ])
            ->orderBy('created_at')
            ->get();
        $cupos = max(0, $maxActivas - $activas->count());
        if ($cupos === 0) {
            return;
        }

        $clavesActivas = $activas
            ->pluck('candidate_key')
            ->flip();

        foreach ($candidatos as $candidato) {
            if ($cupos === 0 || $clavesActivas->has($candidato['candidate_key'])) {
                continue;
            }

            $folios = collect($candidato['pasos'])->pluck('folio_id')->unique();
            $otraTarea = TareaMovimiento::query()
                ->whereIn('folio_id', $folios)
                ->whereIn('estado', [
                    EstadoTareaMovimiento::Bloqueada->value,
                    EstadoTareaMovimiento::Pendiente->value,
                    EstadoTareaMovimiento::Asumida->value,
                    EstadoTareaMovimiento::EnProceso->value,
                ])
                ->lockForUpdate()
                ->first();
            if ($otraTarea) {
                continue;
            }

            $this->maniobras->crearCerrada(
                $plan,
                $usuario,
                $candidato,
            );
            $clavesActivas->put($candidato['candidate_key'], true);
            $cupos--;
        }
    }

    private function asegurarPlan(
        ?PlanOperacional $plan,
        Carga $carga,
        User $usuario,
        array $analisis,
        string $camaraObjetivoId,
        string $geometriaHash,
    ): PlanOperacional {
        if (! $plan) {
            return PlanOperacional::create([
                'temporada_id' => $carga->temporada_id,
                'tipo' => TipoPlanOperacional::ConcentracionCarga,
                'estado' => EstadoPlanOperacional::Programado,
                'prioridad' => $this->prioridad($carga->prioridad),
                'titulo' => "Concentrar {$carga->codigo}",
                'motivo' => sprintf(
                    'Carga bajo el umbral de concentración: %d%% de %d%%.',
                    $analisis['porcentaje'],
                    CalculadorConcentracionCarga::UMBRAL_PORCENTAJE,
                ),
                'referencia_tipo' => self::REFERENCIA,
                'referencia_id' => $carga->id,
                'contexto' => $this->contextoPlan(
                    $carga,
                    $analisis,
                    $camaraObjetivoId,
                    $geometriaHash,
                ),
                'creado_por_user_id' => $usuario->id,
                'programado_at' => now(),
            ]);
        }

        if ($plan->estado->esFinal()) {
            $contexto = $plan->contexto ?? [];
            $plan->update([
                'estado' => EstadoPlanOperacional::Programado,
                'prioridad' => $this->prioridad($carga->prioridad),
                'iniciado_por_user_id' => null,
                'iniciado_at' => null,
                'completado_por_user_id' => null,
                'completado_at' => null,
                'cancelado_por_user_id' => null,
                'cancelado_at' => null,
                'motivo_cancelacion' => null,
                'contexto' => [
                    ...$contexto,
                    'reactivaciones' => ((int) ($contexto['reactivaciones'] ?? 0)) + 1,
                ],
                'version' => $plan->version + 1,
            ]);
        }

        return $plan->refresh();
    }

    private function actualizarContexto(
        PlanOperacional $plan,
        Carga $carga,
        array $analisis,
        string $camaraObjetivoId,
        string $geometriaHash,
        bool $suspendidoPorAnden = false,
    ): void {
        $contexto = [
            ...($plan->contexto ?? []),
            ...$this->contextoPlan(
                $carga,
                $analisis,
                $camaraObjetivoId,
                $geometriaHash,
                $suspendidoPorAnden,
            ),
        ];
        $prioridad = $this->prioridad($carga->prioridad);

        if ($plan->contexto === $contexto && $plan->prioridad === $prioridad) {
            return;
        }

        $plan->update([
            'contexto' => $contexto,
            'prioridad' => $prioridad,
            'version' => $plan->version + 1,
        ]);
    }

    /** @return array<string, mixed> */
    private function contextoPlan(
        Carga $carga,
        array $analisis,
        string $camaraObjetivoId,
        string $geometriaHash,
        bool $suspendidoPorAnden = false,
    ): array {
        return [
            'planner_horizon' => 'rolling',
            'planner_compute' => 'tablet',
            'objetivo' => 'alcanzar_concentracion_minima_sin_movimientos_inutiles',
            'carga_id' => $carga->id,
            'camara_objetivo_id' => $camaraObjetivoId,
            'umbral_porcentaje' => CalculadorConcentracionCarga::UMBRAL_PORCENTAJE,
            'porcentaje_actual' => $analisis['porcentaje'],
            'concentrados' => $analisis['concentrados'],
            'total' => $analisis['total'],
            'grupo_principal' => $analisis['grupo_principal'],
            'concentracion_geometry_hash' => $geometriaHash,
            'suspendido_por_anden' => $suspendidoPorAnden,
        ];
    }

    private function completarSiCorresponde(PlanOperacional $plan, User $usuario): void
    {
        $enProceso = $plan->maniobras()
            ->whereIn('estado', [
                EstadoManiobraOperacional::EnEjecucion->value,
                EstadoManiobraOperacional::PausadaDiscrepancia->value,
            ])
            ->lockForUpdate()
            ->exists();
        if ($enProceso || $plan->estado->esFinal()) {
            return;
        }

        $plan->update([
            'estado' => EstadoPlanOperacional::Completado,
            'completado_por_user_id' => $usuario->id,
            'completado_at' => now(),
            'version' => $plan->version + 1,
        ]);
    }

    private function cancelarReversibles(
        PlanOperacional $plan,
        User $usuario,
        string $motivo,
    ): void {
        $maniobras = $plan->maniobras()
            ->whereIn('estado', [
                EstadoManiobraOperacional::Pendiente->value,
                EstadoManiobraOperacional::EnEjecucion->value,
            ])
            ->lockForUpdate()
            ->get();
        foreach ($maniobras as $maniobra) {
            $this->maniobras->cancelarReversible($maniobra, $usuario, $motivo);
        }

        $tareas = $plan->tareas()
            ->whereNull('maniobra_operacional_id')
            ->whereIn('estado', [
                EstadoTareaMovimiento::Pendiente->value,
                EstadoTareaMovimiento::Asumida->value,
            ])
            ->orderBy('secuencia')
            ->lockForUpdate()
            ->get();

        foreach ($tareas as $tarea) {
            $this->planes->cancelarPorReplanificacion($tarea, $usuario, $motivo);
        }
    }

    private function cancelarObjetivoFinalizado(
        ?PlanOperacional $plan,
        User $usuario,
        string $motivo,
    ): void {
        if (! $plan || $plan->estado->esFinal()) {
            return;
        }

        $this->cancelarReversibles($plan, $usuario, $motivo);
        if ($plan->tareas()
            ->where('estado', EstadoTareaMovimiento::EnProceso->value)
            ->lockForUpdate()
            ->exists()) {
            return;
        }
        if ($plan->maniobras()
            ->whereIn('estado', [
                EstadoManiobraOperacional::EnEjecucion->value,
                EstadoManiobraOperacional::PausadaDiscrepancia->value,
            ])
            ->lockForUpdate()
            ->exists()) {
            return;
        }

        $plan->update([
            'estado' => EstadoPlanOperacional::Cancelado,
            'cancelado_por_user_id' => $usuario->id,
            'cancelado_at' => now(),
            'motivo_cancelacion' => $motivo,
            'version' => $plan->version + 1,
        ]);
    }

    private function planExistente(string $cargaId, bool $bloquear = false): ?PlanOperacional
    {
        $consulta = PlanOperacional::query()
            ->where('referencia_tipo', self::REFERENCIA)
            ->where('referencia_id', $cargaId);

        return ($bloquear ? $consulta->lockForUpdate() : $consulta)->first();
    }

    private function suspenderTrabajoDirigido(
        Carga $carga,
        User $usuario,
        string $motivo,
    ): ?PlanOperacional {
        return DB::transaction(function () use ($carga, $usuario, $motivo): ?PlanOperacional {
            $plan = $this->planExistente($carga->id, bloquear: true);
            if (! $plan) {
                return null;
            }

            $this->cancelarReversibles($plan, $usuario, $motivo);

            return $plan->refresh();
        }, attempts: 3);
    }

    /** @param array<string, mixed> $analisis */
    private function geometriaHash(string $camaraObjetivoId, array $analisis): string
    {
        return hash('sha256', json_encode([
            'camara_objetivo_id' => $camaraObjetivoId,
            'grupo_folio_ids' => $analisis['grupo_principal_folio_ids'],
            'puntos' => $analisis['grupo_principal_puntos'],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array{banda: int, posicion: int, nivel: int}  $primero
     * @param  array{banda: int, posicion: int, nivel: int}  $segundo
     */
    private function sonPuntosVecinos(array $primero, array $segundo): bool
    {
        if ($primero['nivel'] !== $segundo['nivel']) {
            return false;
        }

        $diferenciaBanda = abs($primero['banda'] - $segundo['banda']);
        $diferenciaPosicion = abs($primero['posicion'] - $segundo['posicion']);

        return ($diferenciaBanda === 0 && $diferenciaPosicion === 1)
            || ($diferenciaBanda === 1 && $diferenciaPosicion <= 1);
    }

    private function prioridad(PrioridadCarga $prioridad): PrioridadOperacional
    {
        return match ($prioridad) {
            PrioridadCarga::Urgente => PrioridadOperacional::Urgente,
            PrioridadCarga::Alta => PrioridadOperacional::Alta,
            PrioridadCarga::Normal => PrioridadOperacional::Normal,
        };
    }

    /** @param array<string, mixed> $analisis */
    private function registrarShadow(
        Carga $carga,
        User $usuario,
        array $analisis,
        string $camaraObjetivoId,
        int $necesarios,
    ): void {
        EventoCarga::create([
            'carga_id' => $carga->id,
            'user_id' => $usuario->id,
            'tipo' => TipoEventoCarga::TareasGeneradas,
            'datos' => [
                'planner_mode' => 'shadow',
                'objetivo' => 'concentracion_carga',
                'porcentaje_actual' => $analisis['porcentaje'],
                'umbral_porcentaje' => CalculadorConcentracionCarga::UMBRAL_PORCENTAJE,
                'camara_objetivo_id' => $camaraObjetivoId,
                'movimientos_minimos_estimados' => $necesarios,
            ],
        ]);
    }
}
