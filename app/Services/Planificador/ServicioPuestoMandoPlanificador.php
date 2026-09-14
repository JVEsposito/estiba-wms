<?php

namespace App\Services\Planificador;

use App\Enums\DecisionArbitrajeManiobra;
use App\Enums\EstadoCustodiaTemporal;
use App\Enums\EstadoDiscrepanciaManiobra;
use App\Enums\EstadoTareaMovimiento;
use App\Models\Camara;
use App\Models\CicloArbitrajeManiobras;
use App\Models\DecisionArbitrajeManiobra as DecisionPersistida;
use App\Models\ManiobraOperacional;
use App\Models\Posicion;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use Illuminate\Support\Collection;

final class ServicioPuestoMandoPlanificador
{
    public function __construct(
        private readonly ServicioDesplieguePlanificador $despliegue,
        private readonly ServicioEstadoArbitrajePlanificador $estadoArbitraje,
        private readonly ServicioSaludPlanificador $salud,
        private readonly ServicioIntervencionesPlanificador $intervenciones,
    ) {}

    /** @return array<string, mixed> */
    public function obtener(Temporada $temporada): array
    {
        $modo = $this->despliegue->modoGlobal();
        $proyeccion = $this->estadoArbitraje->consultar($temporada);
        $ciclo = $proyeccion['ciclo'];
        unset($proyeccion['ciclo']);

        if ($ciclo) {
            $this->cargarDecisiones($ciclo);
        }

        return [
            'generado_at' => now()->toIso8601String(),
            'despliegue' => $this->despliegue->configuracion(),
            'salud' => $this->salud->saludActual($temporada->id),
            'arbitraje' => [
                'activo' => in_array($modo, ['shadow', 'guided'], true),
                'vigencia' => $proyeccion,
                'ciclo' => $ciclo ? $this->serializarCiclo($ciclo) : null,
            ],
        ];
    }

    private function cargarDecisiones(CicloArbitrajeManiobras $ciclo): void
    {
        $ciclo->load([
            'decisiones.maniobraOperacional' => fn ($consulta) => $consulta->with([
                'planOperacional:id,tipo,titulo',
                'responsable:id,name',
                'dispositivo:id,codigo,nombre',
                'pasos.folio:id,numero_folio',
                'pasos.camaraOrigen:id,codigo,nombre',
                'pasos.posicionOrigen:id,camara_id,etiqueta,banda,posicion,nivel',
                'pasos.camaraDestino:id,codigo,nombre',
                'pasos.posicionDestino:id,camara_id,etiqueta,banda,posicion,nivel',
                'pasos.reservaActiva:id,bloqueo_tarea_id,estado,vence_at',
                'custodiasTemporales' => fn ($consulta) => $consulta->where(
                    'estado',
                    EstadoCustodiaTemporal::Activa->value,
                ),
                'discrepancias' => fn ($consulta) => $consulta->where(
                    'estado',
                    EstadoDiscrepanciaManiobra::Abierta->value,
                ),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function serializarCiclo(CicloArbitrajeManiobras $ciclo): array
    {
        $decisiones = $ciclo->decisiones
            ->filter(fn (DecisionPersistida $decision): bool => $decision->maniobraOperacional !== null)
            ->values();
        $resumen = collect(DecisionArbitrajeManiobra::cases())
            ->mapWithKeys(fn (DecisionArbitrajeManiobra $decision): array => [
                $decision->value => $decisiones
                    ->filter(fn (DecisionPersistida $persistida): bool => $persistida->decision === $decision)
                    ->count(),
            ])
            ->all();

        return [
            'id' => $ciclo->id,
            'snapshot_version' => $ciclo->snapshot_version,
            'generado_at' => $ciclo->created_at?->toIso8601String(),
            'capacidad_ejecucion' => $ciclo->capacidad_ejecucion,
            'frontera_max' => $ciclo->frontera_max,
            'resumen' => $resumen,
            'decisiones' => $decisiones
                ->map(fn (DecisionPersistida $decision): array => $this->serializarDecision($decision))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarDecision(DecisionPersistida $decision): array
    {
        $maniobra = $decision->maniobraOperacional;
        $pasos = $maniobra->pasos;
        $completados = $pasos
            ->filter(fn (TareaMovimiento $paso): bool => $paso->estado === EstadoTareaMovimiento::Completada)
            ->count();
        $actual = $this->pasoActual($maniobra, $pasos);

        return [
            'maniobra_id' => $maniobra->id,
            'orden' => $decision->orden,
            'decision' => $decision->decision->value,
            'estado' => $maniobra->estado->value,
            'prioridad' => $maniobra->prioridad->value,
            'version' => $maniobra->version,
            'acciones_autorizadas' => $this->intervenciones->accionesAutorizadas($maniobra),
            'titulo' => $maniobra->titulo,
            'motivo' => $decision->motivo,
            'puntaje' => $decision->puntaje,
            'beneficio_neto' => $decision->beneficio_neto,
            'costo_movimientos' => $maniobra->costo_movimientos,
            'riesgo_operacional' => $maniobra->riesgo_operacional,
            'objetivo' => [
                'tipo' => $maniobra->planOperacional?->tipo?->value,
                'titulo' => $maniobra->planOperacional?->titulo,
            ],
            'progreso' => [
                'secuencia_actual' => $maniobra->secuencia_actual,
                'pasos_total' => $pasos->count(),
                'pasos_completados' => $completados,
                'porcentaje' => $pasos->isEmpty()
                    ? 0
                    : (int) round(($completados / $pasos->count()) * 100),
            ],
            'paso_actual' => $actual ? $this->serializarPaso($actual) : null,
            'pasos' => $pasos
                ->map(fn (TareaMovimiento $paso): array => $this->serializarPaso($paso))
                ->values()
                ->all(),
            'responsable' => $maniobra->responsable ? [
                'id' => $maniobra->responsable->id,
                'nombre' => $maniobra->responsable->name,
            ] : null,
            'dispositivo' => $maniobra->dispositivo ? [
                'id' => $maniobra->dispositivo->id,
                'codigo' => $maniobra->dispositivo->codigo,
                'nombre' => $maniobra->dispositivo->nombre,
            ] : null,
            'conflictos' => array_values($decision->conflictos ?? []),
        ];
    }

    /**
     * @param  Collection<int, TareaMovimiento>  $pasos
     */
    private function pasoActual(ManiobraOperacional $maniobra, Collection $pasos): ?TareaMovimiento
    {
        return $pasos->firstWhere('secuencia_maniobra', $maniobra->secuencia_actual)
            ?? $pasos->first(fn (TareaMovimiento $paso): bool => ! $paso->estado->esFinal());
    }

    /** @return array<string, mixed> */
    private function serializarPaso(TareaMovimiento $paso): array
    {
        return [
            'id' => $paso->id,
            'secuencia' => $paso->secuencia_maniobra,
            'estado' => $paso->estado->value,
            'tipo_movimiento' => $paso->tipo_movimiento->value,
            'instruccion' => $paso->instruccion,
            'folio' => $paso->folio ? [
                'id' => $paso->folio->id,
                'numero_folio' => $paso->folio->numero_folio,
            ] : null,
            'origen' => $this->serializarUbicacion($paso->camaraOrigen, $paso->posicionOrigen),
            'destino' => $this->serializarUbicacion($paso->camaraDestino, $paso->posicionDestino),
        ];
    }

    /** @return array<string, mixed>|null */
    private function serializarUbicacion(?Camara $camara, ?Posicion $posicion): ?array
    {
        if (! $camara && ! $posicion) {
            return null;
        }

        return [
            // Se conservan estas claves para los consumidores actuales del puesto de mando.
            'id' => $camara?->id,
            'codigo' => $camara?->codigo,
            'nombre' => $camara?->nombre,
            'camara' => $camara ? [
                'id' => $camara->id,
                'codigo' => $camara->codigo,
                'nombre' => $camara->nombre,
            ] : null,
            'posicion' => $posicion ? [
                'id' => $posicion->id,
                'etiqueta' => $posicion->etiqueta,
                'banda' => $posicion->banda,
                'posicion' => $posicion->posicion,
                'nivel' => $posicion->nivel,
            ] : null,
        ];
    }
}
