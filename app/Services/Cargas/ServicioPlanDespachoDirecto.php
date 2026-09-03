<?php

namespace App\Services\Cargas;

use App\Enums\EstadoCargaFolio;
use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoPresenciaCargaAnden;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\PrioridadOperacional;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPlanOperacional;
use App\Models\CargaFolio;
use App\Models\Movimiento;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\PresenciaCargaAnden;
use App\Models\TareaMovimiento;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Services\Estiba\ServicioPlanesOperacionales;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServicioPlanDespachoDirecto
{
    private const REFERENCIA = 'presencia_carga_anden';

    public function __construct(
        private readonly ServicioPlanesOperacionales $planes,
    ) {}

    public function sincronizar(
        PresenciaCargaAnden $presencia,
        User $usuario,
    ): ?PlanOperacional {
        if (config('planificador.mode') !== 'guided'
            || ! config('planificador.generacion_automatica')) {
            return null;
        }

        return DB::transaction(function () use ($presencia, $usuario): ?PlanOperacional {
            $presencia = PresenciaCargaAnden::query()
                ->with(['carga.temporada', 'anden'])
                ->lockForUpdate()
                ->findOrFail($presencia->id);

            if ($presencia->estado !== EstadoPresenciaCargaAnden::Activa
                || $presencia->bloqueo_carga_id === null
                || ! $presencia->carga->temporada?->activa) {
                return null;
            }

            $plan = PlanOperacional::query()
                ->where('referencia_tipo', self::REFERENCIA)
                ->where('referencia_id', $presencia->id)
                ->lockForUpdate()
                ->first();

            if ($plan?->estado->esFinal()) {
                return $plan;
            }

            $tareaActivaPlan = $plan?->tareas()
                ->whereIn('estado', [
                    EstadoTareaMovimiento::Pendiente->value,
                    EstadoTareaMovimiento::Asumida->value,
                    EstadoTareaMovimiento::EnProceso->value,
                ])
                ->lockForUpdate()
                ->first();
            if ($tareaActivaPlan?->estado === EstadoTareaMovimiento::EnProceso) {
                return $plan;
            }

            $asignaciones = $this->asignacionesPendientes($presencia);

            if ($asignaciones->isEmpty()) {
                if ($tareaActivaPlan) {
                    $this->planes->cancelarPorReplanificacion(
                        $tareaActivaPlan,
                        $usuario,
                        'La carga ya no posee pallets completos pendientes de retiro.',
                    );
                }
                if ($plan) {
                    $plan->refresh();
                    $plan->update([
                        'estado' => EstadoPlanOperacional::Completado,
                        'completado_por_user_id' => $usuario->id,
                        'completado_at' => now(),
                        'version' => $plan->version + 1,
                    ]);
                }

                return $plan?->refresh();
            }

            $decision = $this->siguienteDecision($asignaciones, $presencia);
            if ($decision === null) {
                if ($tareaActivaPlan) {
                    $this->planes->cancelarPorReplanificacion(
                        $tareaActivaPlan,
                        $usuario,
                        'La decisión anterior dejó de ser ejecutable con el estado físico vigente.',
                    );
                }

                return $plan;
            }

            if ($tareaActivaPlan && $this->mismaDecision($tareaActivaPlan, $decision)) {
                return $plan;
            }

            $tareasReemplazadas = collect();
            if ($tareaActivaPlan) {
                $cancelada = $this->planes->cancelarPorReplanificacion(
                    $tareaActivaPlan,
                    $usuario,
                    "La accesibilidad física cambió mientras el camión {$presencia->patente} continúa en andén.",
                );
                if ($cancelada === null) {
                    return $plan;
                }
                $tareasReemplazadas->push($cancelada);
            }

            $tareaAnterior = TareaMovimiento::query()
                ->where('folio_id', $decision['folio_id'])
                ->whereIn('estado', [
                    EstadoTareaMovimiento::Pendiente->value,
                    EstadoTareaMovimiento::Asumida->value,
                    EstadoTareaMovimiento::EnProceso->value,
                ])
                ->lockForUpdate()
                ->first();

            if ($tareaAnterior?->estado === EstadoTareaMovimiento::EnProceso) {
                return $plan;
            }
            if ($tareaAnterior && $tareaAnterior->plan_operacional_id !== $plan?->id) {
                $cancelada = $this->planes->cancelarPorReplanificacion(
                    $tareaAnterior,
                    $usuario,
                    "Camión {$presencia->patente} presente en {$presencia->anden->nombre}; el destino prioritario cambió a andén.",
                );
                if ($cancelada === null) {
                    return $plan;
                }
                $tareasReemplazadas->push($cancelada);
            }

            if (! $plan) {
                $plan = $this->planes->crear(
                    temporada: $presencia->carga->temporada,
                    tipo: TipoPlanOperacional::DespachoDirecto,
                    titulo: "Despacho directo {$presencia->carga->codigo} → {$presencia->anden->nombre}",
                    creadoPor: $usuario,
                    tareas: [$decision],
                    prioridad: PrioridadOperacional::Critica,
                    motivo: "Camión {$presencia->patente} confirmado en andén.",
                    referenciaTipo: self::REFERENCIA,
                    referenciaId: $presencia->id,
                    contexto: [
                        'planner_horizon' => 'rolling',
                        'carga_id' => $presencia->carga_id,
                        'anden_id' => $presencia->anden_id,
                        'patente' => $presencia->patente,
                    ],
                );
                $nuevaTarea = $plan->tareas->first();
            } else {
                $nuevaTarea = $this->planes->agregarTareaRolling($plan, $decision);
            }

            if (isset($nuevaTarea)) {
                $tareasReemplazadas
                    ->unique('id')
                    ->each(fn (TareaMovimiento $tarea): bool => $tarea->update([
                        'reemplazada_por_tarea_id' => $nuevaTarea->id,
                    ]));
            }

            return $plan->refresh();
        }, attempts: 3);
    }

    public function sincronizarTrasMovimiento(Movimiento $movimiento, User $usuario): void
    {
        if (config('planificador.mode') !== 'guided'
            || ! config('planificador.generacion_automatica')) {
            return;
        }

        $presencias = PresenciaCargaAnden::query()
            ->where('estado', EstadoPresenciaCargaAnden::Activa->value)
            ->whereNotNull('bloqueo_carga_id')
            ->orderBy('ingresada_at')
            ->get();

        foreach ($presencias as $presencia) {
            $this->sincronizar($presencia, $usuario);
        }
    }

    public function cancelar(PresenciaCargaAnden $presencia, User $usuario, string $motivo): void
    {
        $plan = PlanOperacional::query()
            ->where('referencia_tipo', self::REFERENCIA)
            ->where('referencia_id', $presencia->id)
            ->lockForUpdate()
            ->first();

        if (! $plan || $plan->estado->esFinal()) {
            return;
        }

        $tareas = $plan->tareas()
            ->whereIn('estado', [
                EstadoTareaMovimiento::Pendiente->value,
                EstadoTareaMovimiento::Asumida->value,
                EstadoTareaMovimiento::EnProceso->value,
            ])
            ->orderBy('secuencia')
            ->lockForUpdate()
            ->get();

        if ($tareas->contains(
            fn (TareaMovimiento $tarea): bool => $tarea->estado === EstadoTareaMovimiento::EnProceso,
        )) {
            throw new DomainException(
                'No se puede liberar el andén mientras un pallet está físicamente en movimiento hacia él.',
            );
        }

        foreach ($tareas as $tarea) {
            $this->planes->cancelarPorReplanificacion($tarea, $usuario, $motivo);
        }

        $plan->refresh()->update([
            'estado' => EstadoPlanOperacional::Cancelado,
            'cancelado_por_user_id' => $usuario->id,
            'cancelado_at' => now(),
            'motivo_cancelacion' => $motivo,
            'version' => $plan->version + 1,
        ]);
    }

    /** @return Collection<int, CargaFolio> */
    private function asignacionesPendientes(PresenciaCargaAnden $presencia): Collection
    {
        return CargaFolio::query()
            ->where('carga_id', $presencia->carga_id)
            ->where('estado', EstadoCargaFolio::Pendiente->value)
            ->whereHas('reservaActiva')
            ->whereHas('folio', fn ($folios) => $folios
                ->where('activo', true)
                ->where('tipo_bulto', TipoBulto::Pallet->value))
            ->with('folio.ubicacionActual.posicion.camara')
            ->orderBy('asignado_at')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  Collection<int, CargaFolio>  $asignaciones
     * @return array<string, mixed>|null
     */
    private function siguienteDecision(
        Collection $asignaciones,
        PresenciaCargaAnden $presencia,
    ): ?array {
        $ubicadas = $asignaciones->filter(
            fn (CargaFolio $asignacion): bool => $asignacion->folio?->ubicacionActual?->posicion !== null,
        );
        if ($ubicadas->isEmpty()) {
            return null;
        }

        $camaraIds = $ubicadas
            ->map(fn (CargaFolio $asignacion): string => $asignacion->folio->ubicacionActual->posicion->camara_id)
            ->unique()
            ->values();
        $ocupaciones = UbicacionActual::query()
            ->whereHas('posicion', fn ($posiciones) => $posiciones->whereIn('camara_id', $camaraIds))
            ->with(['folio.asignacionCargaActual.carga', 'posicion.camara'])
            ->lockForUpdate()
            ->get();

        $rutas = $ubicadas->map(function (CargaFolio $asignacion) use ($ocupaciones): array {
            $posicion = $asignacion->folio->ubicacionActual->posicion;
            $bloqueadores = $ocupaciones
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

            return compact('asignacion', 'posicion', 'bloqueadores');
        });

        $accesible = $rutas
            ->filter(fn (array $ruta): bool => $ruta['bloqueadores']->isEmpty())
            ->sortByDesc(fn (array $ruta): int => $ruta['posicion']->posicion)
            ->first();

        if ($accesible) {
            /** @var CargaFolio $asignacion */
            $asignacion = $accesible['asignacion'];
            $posicion = $accesible['posicion'];

            return [
                'folio_id' => $asignacion->folio_id,
                'tipo_movimiento' => TipoMovimiento::Retiro,
                'prioridad' => PrioridadOperacional::Critica,
                'camara_origen_id' => $posicion->camara_id,
                'posicion_origen_id' => $posicion->id,
                'instruccion' => "Retirar {$asignacion->folio->numero_folio} directamente a {$presencia->anden->nombre}.",
                'contexto' => [
                    'tipo_decision' => 'retiro_directo_anden',
                    'presencia_carga_anden_id' => $presencia->id,
                    'carga_id' => $presencia->carga_id,
                    'carga_folio_id' => $asignacion->id,
                    'anden_id' => $presencia->anden_id,
                    'anden_nombre' => $presencia->anden->nombre,
                    'patente' => $presencia->patente,
                ],
            ];
        }

        $ruta = $rutas
            ->sortBy(fn (array $item): int => $item['bloqueadores']->count())
            ->first();
        /** @var UbicacionActual|null $bloqueador */
        $bloqueador = $ruta['bloqueadores']->first();

        if (! $bloqueador
            || ! $bloqueador->folio?->activo
            || $bloqueador->folio->tipo_bulto !== TipoBulto::Pallet) {
            return null;
        }

        $posicion = $bloqueador->posicion;
        $hayDestinoMismaCamara = Posicion::query()
            ->where('camara_id', $posicion->camara_id)
            ->where('estado', 'activa')
            ->whereDoesntHave('ubicacionActual')
            ->whereDoesntHave('reservaTareaActiva')
            ->exists();

        return [
            'folio_id' => $bloqueador->folio_id,
            'tipo_movimiento' => $hayDestinoMismaCamara
                ? TipoMovimiento::Reubicacion
                : TipoMovimiento::TrasladoEntreCamaras,
            'prioridad' => PrioridadOperacional::Critica,
            'camara_origen_id' => $posicion->camara_id,
            'posicion_origen_id' => $posicion->id,
            'instruccion' => "Despejar {$bloqueador->folio->numero_folio} para habilitar la salida directa de la carga {$presencia->carga->codigo}.",
            'contexto' => [
                'tipo_decision' => 'despeje_salida_directa',
                'presencia_carga_anden_id' => $presencia->id,
                'carga_id' => $presencia->carga_id,
                'anden_id' => $presencia->anden_id,
                'anden_nombre' => $presencia->anden->nombre,
                'habilita_folio_id' => $ruta['asignacion']->folio_id,
            ],
        ];
    }

    /** @param array<string, mixed> $decision */
    private function mismaDecision(TareaMovimiento $tarea, array $decision): bool
    {
        return $tarea->folio_id === $decision['folio_id']
            && $tarea->tipo_movimiento === $decision['tipo_movimiento']
            && $tarea->camara_origen_id === ($decision['camara_origen_id'] ?? null)
            && $tarea->posicion_origen_id === ($decision['posicion_origen_id'] ?? null)
            && ($tarea->contexto['presencia_carga_anden_id'] ?? null)
                === ($decision['contexto']['presencia_carga_anden_id'] ?? null);
    }
}
