<?php

namespace App\Services\Planificador;

use App\Enums\DominioTransicionOperacional;
use App\Enums\EstadoCustodiaTemporal;
use App\Enums\EstadoDiscrepanciaManiobra;
use App\Enums\EstadoManiobraOperacional;
use App\Enums\EstadoReservaTareaMovimiento;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\EstadoTransicionOperacional;
use App\Enums\PrioridadOperacional;
use App\Exceptions\ConflictoOperacion;
use App\Models\ManiobraOperacional;
use App\Models\ReservaTareaMovimiento;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Models\TransicionOperacional;
use App\Models\User;
use App\Services\Estiba\ServicioReservasTareasMovimiento;
use App\Services\Transiciones\ComandoTransicionOperacional;
use App\Services\Transiciones\MotorTransicionesOperacionales;
use Illuminate\Support\Collection;

final class ServicioIntervencionesPlanificador
{
    private const TIPO_PAUSAR = 'maniobra.pausar_supervision';

    private const TIPO_REANUDAR = 'maniobra.reanudar_supervision';

    private const TIPO_REPRIORIZAR = 'maniobra.repriorizar';

    private const TIPO_EXPIRAR_RESERVAS = 'reservas.expirar_vencidas';

    public function __construct(
        private readonly MotorTransicionesOperacionales $motorTransiciones,
        private readonly ServicioReservasTareasMovimiento $reservas,
    ) {}

    /** @return array<string, mixed> */
    public function accionesAutorizadas(ManiobraOperacional $maniobra): array
    {
        $maniobra->loadMissing([
            'pasos.reservaActiva',
            'custodiasTemporales' => fn ($consulta) => $consulta->where(
                'estado',
                EstadoCustodiaTemporal::Activa->value,
            ),
            'discrepancias' => fn ($consulta) => $consulta->where(
                'estado',
                EstadoDiscrepanciaManiobra::Abierta->value,
            ),
        ]);

        $bloqueoBase = $this->bloqueoPreinicio($maniobra, $maniobra->pasos);
        $restricciones = [
            'pausar' => $maniobra->estado === EstadoManiobraOperacional::Pendiente
                ? $bloqueoBase
                : 'estado_no_pendiente',
            'reanudar' => $maniobra->estado === EstadoManiobraOperacional::PausadaSupervision
                ? $bloqueoBase
                : 'no_pausada_por_supervision',
            'repriorizar' => in_array($maniobra->estado, [
                EstadoManiobraOperacional::Pendiente,
                EstadoManiobraOperacional::PausadaSupervision,
            ], true)
                ? $bloqueoBase
                : 'estado_no_repriorizable',
            'resolver_discrepancia' => $maniobra->estado === EstadoManiobraOperacional::PausadaDiscrepancia
                && $maniobra->discrepancias->isNotEmpty()
                    ? null
                    : 'sin_discrepancia_abierta',
        ];

        return [
            'version_requerida' => $maniobra->version,
            'permitidas' => collect($restricciones)
                ->filter(fn (?string $bloqueo): bool => $bloqueo === null)
                ->keys()
                ->values()
                ->all(),
            'restricciones' => $restricciones,
        ];
    }

    public function pausar(
        ManiobraOperacional $maniobra,
        User $supervisor,
        int $versionEsperada,
        string $motivo,
        string $operacionId,
    ): ManiobraOperacional {
        return $this->motorTransiciones->ejecutar(
            $this->comando(
                self::TIPO_PAUSAR,
                $maniobra,
                $supervisor,
                $versionEsperada,
                $motivo,
                $operacionId,
            ),
            function () use (
                $maniobra,
                $supervisor,
                $versionEsperada,
                $motivo,
                $operacionId,
            ): ManiobraOperacional {
                if ($this->transicionAplicada(self::TIPO_PAUSAR, $operacionId)) {
                    return ManiobraOperacional::query()->findOrFail($maniobra->id);
                }

                $actual = $this->bloquearManiobra($maniobra);
                $this->validarVersion($actual, $versionEsperada);
                if ($actual->estado !== EstadoManiobraOperacional::Pendiente) {
                    throw new ConflictoOperacion(
                        'Solo se puede pausar una maniobra pendiente que todavía no comenzó.',
                    );
                }

                $pasos = $this->bloquearPasos($actual);
                $this->validarPreinicio($actual, $pasos);
                $pasoActual = $this->pasoActual($actual, $pasos);
                if (! $pasoActual || $pasoActual->estado !== EstadoTareaMovimiento::Pendiente) {
                    throw new ConflictoOperacion(
                        'La maniobra no posee un paso pendiente seguro para pausar.',
                    );
                }

                $ahora = now();
                $pasoActual->update([
                    'estado' => EstadoTareaMovimiento::Bloqueada,
                    'version' => $pasoActual->version + 1,
                ]);
                $actual->update([
                    'estado' => EstadoManiobraOperacional::PausadaSupervision,
                    'pausada_at' => $ahora,
                    'contexto' => [
                        ...($actual->contexto ?? []),
                        'supervision' => [
                            ...$this->contextoSupervision($actual),
                            'pausada' => true,
                            'motivo_pausa' => trim($motivo),
                            'pausada_por_user_id' => $supervisor->id,
                            'pausada_at' => $ahora->toIso8601String(),
                        ],
                    ],
                    'version' => $actual->version + 1,
                ]);

                return $actual->refresh();
            },
        );
    }

    public function reanudar(
        ManiobraOperacional $maniobra,
        User $supervisor,
        int $versionEsperada,
        string $motivo,
        string $operacionId,
    ): ManiobraOperacional {
        return $this->motorTransiciones->ejecutar(
            $this->comando(
                self::TIPO_REANUDAR,
                $maniobra,
                $supervisor,
                $versionEsperada,
                $motivo,
                $operacionId,
            ),
            function () use (
                $maniobra,
                $supervisor,
                $versionEsperada,
                $motivo,
                $operacionId,
            ): ManiobraOperacional {
                if ($this->transicionAplicada(self::TIPO_REANUDAR, $operacionId)) {
                    return ManiobraOperacional::query()->findOrFail($maniobra->id);
                }

                $actual = $this->bloquearManiobra($maniobra);
                $this->validarVersion($actual, $versionEsperada);
                if ($actual->estado !== EstadoManiobraOperacional::PausadaSupervision) {
                    throw new ConflictoOperacion(
                        'La maniobra no está pausada por una intervención de supervisión.',
                    );
                }

                $pasos = $this->bloquearPasos($actual);
                $this->validarPreinicio($actual, $pasos);
                $pasoActual = $this->pasoActual($actual, $pasos);
                if (! $pasoActual || $pasoActual->estado !== EstadoTareaMovimiento::Bloqueada) {
                    throw new ConflictoOperacion(
                        'El paso de la maniobra cambió y ya no puede reanudarse de forma segura.',
                    );
                }

                $ahora = now();
                $pasoActual->update([
                    'estado' => EstadoTareaMovimiento::Pendiente,
                    'version' => $pasoActual->version + 1,
                ]);
                $actual->update([
                    'estado' => EstadoManiobraOperacional::Pendiente,
                    'pausada_at' => null,
                    'contexto' => [
                        ...($actual->contexto ?? []),
                        'supervision' => [
                            ...$this->contextoSupervision($actual),
                            'pausada' => false,
                            'motivo_reanudacion' => trim($motivo),
                            'reanudada_por_user_id' => $supervisor->id,
                            'reanudada_at' => $ahora->toIso8601String(),
                        ],
                    ],
                    'version' => $actual->version + 1,
                ]);

                return $actual->refresh();
            },
        );
    }

    public function repriorizar(
        ManiobraOperacional $maniobra,
        User $supervisor,
        PrioridadOperacional $prioridad,
        int $versionEsperada,
        string $motivo,
        string $operacionId,
    ): ManiobraOperacional {
        return $this->motorTransiciones->ejecutar(
            $this->comando(
                self::TIPO_REPRIORIZAR,
                $maniobra,
                $supervisor,
                $versionEsperada,
                $motivo,
                $operacionId,
                ['prioridad' => $prioridad->value],
            ),
            function () use (
                $maniobra,
                $supervisor,
                $prioridad,
                $versionEsperada,
                $motivo,
                $operacionId,
            ): ManiobraOperacional {
                if ($this->transicionAplicada(self::TIPO_REPRIORIZAR, $operacionId)) {
                    return ManiobraOperacional::query()->findOrFail($maniobra->id);
                }

                $actual = $this->bloquearManiobra($maniobra);
                $this->validarVersion($actual, $versionEsperada);
                if (! in_array($actual->estado, [
                    EstadoManiobraOperacional::Pendiente,
                    EstadoManiobraOperacional::PausadaSupervision,
                ], true)) {
                    throw new ConflictoOperacion(
                        'Solo se pueden repriorizar maniobras pendientes y no iniciadas.',
                    );
                }

                $pasos = $this->bloquearPasos($actual);
                $this->validarPreinicio($actual, $pasos);
                if ($actual->prioridad === $prioridad) {
                    throw new ConflictoOperacion('La maniobra ya posee la prioridad solicitada.');
                }

                foreach ($pasos->filter(fn (TareaMovimiento $paso): bool => ! $paso->estado->esFinal()) as $paso) {
                    $paso->update([
                        'prioridad' => $prioridad,
                        'version' => $paso->version + 1,
                    ]);
                }

                $ahora = now();
                $actual->update([
                    'prioridad' => $prioridad,
                    'contexto' => [
                        ...($actual->contexto ?? []),
                        'supervision' => [
                            ...$this->contextoSupervision($actual),
                            'prioridad_anterior' => $actual->prioridad->value,
                            'prioridad_actual' => $prioridad->value,
                            'motivo_repriorizacion' => trim($motivo),
                            'repriorizada_por_user_id' => $supervisor->id,
                            'repriorizada_at' => $ahora->toIso8601String(),
                        ],
                    ],
                    'version' => $actual->version + 1,
                ]);

                return $actual->refresh();
            },
        );
    }

    /** @return array{reservas_expiradas:int,temporada_id:string} */
    public function expirarReservasVencidas(
        User $supervisor,
        Temporada $temporada,
        int $limite,
        string $motivo,
        string $operacionId,
    ): array {
        return $this->motorTransiciones->ejecutar(
            new ComandoTransicionOperacional(
                dominio: DominioTransicionOperacional::Planificador,
                tipo: self::TIPO_EXPIRAR_RESERVAS,
                usuario: $supervisor,
                payload: [
                    'temporada_id' => $temporada->id,
                    'limite' => $limite,
                    'motivo' => trim($motivo),
                ],
                operacionId: $operacionId,
                sujetoTipo: Temporada::class,
                sujetoId: (string) $temporada->id,
                referencia: $temporada->codigo,
            ),
            function () use ($temporada, $limite, $operacionId): array {
                $aplicada = $this->transicionAplicada(
                    self::TIPO_EXPIRAR_RESERVAS,
                    $operacionId,
                );
                if ($aplicada) {
                    return $aplicada->resultado['datos'] ?? [
                        'reservas_expiradas' => 0,
                        'temporada_id' => $temporada->id,
                    ];
                }

                return [
                    'reservas_expiradas' => $this->reservas->expirarVencidas(
                        $limite,
                        $temporada->id,
                    ),
                    'temporada_id' => $temporada->id,
                ];
            },
        );
    }

    /**
     * @param  array<string, mixed>  $adicional
     */
    private function comando(
        string $tipo,
        ManiobraOperacional $maniobra,
        User $supervisor,
        int $versionEsperada,
        string $motivo,
        string $operacionId,
        array $adicional = [],
    ): ComandoTransicionOperacional {
        return new ComandoTransicionOperacional(
            dominio: DominioTransicionOperacional::Planificador,
            tipo: $tipo,
            usuario: $supervisor,
            payload: [
                'maniobra_id' => $maniobra->id,
                'version_maniobra' => $versionEsperada,
                'motivo' => trim($motivo),
                ...$adicional,
            ],
            operacionId: $operacionId,
            sujetoTipo: ManiobraOperacional::class,
            sujetoId: (string) $maniobra->id,
            referencia: $maniobra->titulo,
        );
    }

    private function bloquearManiobra(ManiobraOperacional $maniobra): ManiobraOperacional
    {
        $actual = ManiobraOperacional::query()
            ->with('planOperacional.temporada')
            ->lockForUpdate()
            ->findOrFail($maniobra->id);

        if (! $actual->planOperacional?->temporada?->activa) {
            throw new ConflictoOperacion(
                'La maniobra no pertenece a la temporada operacional activa.',
            );
        }

        return $actual;
    }

    /** @return Collection<int, TareaMovimiento> */
    private function bloquearPasos(ManiobraOperacional $maniobra): Collection
    {
        return $maniobra->pasos()
            ->orderBy('secuencia_maniobra')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  Collection<int, TareaMovimiento>  $pasos
     */
    private function validarPreinicio(
        ManiobraOperacional $maniobra,
        Collection $pasos,
    ): void {
        $bloqueo = $this->bloqueoPreinicioPersistido($maniobra, $pasos);
        if ($bloqueo !== null) {
            throw new ConflictoOperacion(match ($bloqueo) {
                'prefijo_fisico_iniciado' => 'La realidad física ya comenzó y su prefijo no puede replanificarse.',
                'custodia_temporal_activa' => 'La maniobra mantiene pallets bajo custodia temporal.',
                'tarea_asumida' => 'La maniobra ya fue asumida por un camarero o tablet.',
                'reserva_activa' => 'La maniobra mantiene una reserva activa.',
                default => 'La maniobra ya no admite esta intervención.',
            });
        }
    }

    /**
     * @param  Collection<int, TareaMovimiento>  $pasos
     */
    private function bloqueoPreinicio(
        ManiobraOperacional $maniobra,
        Collection $pasos,
    ): ?string {
        if ($pasos->contains(fn (TareaMovimiento $paso): bool => in_array(
            $paso->estado,
            [EstadoTareaMovimiento::EnProceso, EstadoTareaMovimiento::Completada],
            true,
        ))) {
            return 'prefijo_fisico_iniciado';
        }
        if ($maniobra->custodiasTemporales->isNotEmpty()) {
            return 'custodia_temporal_activa';
        }
        if ($pasos->contains(
            fn (TareaMovimiento $paso): bool => $paso->estado === EstadoTareaMovimiento::Asumida,
        )) {
            return 'tarea_asumida';
        }
        if ($pasos->contains(
            fn (TareaMovimiento $paso): bool => $paso->reservaActiva !== null,
        )) {
            return 'reserva_activa';
        }

        return null;
    }

    /**
     * @param  Collection<int, TareaMovimiento>  $pasos
     */
    private function bloqueoPreinicioPersistido(
        ManiobraOperacional $maniobra,
        Collection $pasos,
    ): ?string {
        if ($pasos->contains(fn (TareaMovimiento $paso): bool => in_array(
            $paso->estado,
            [EstadoTareaMovimiento::EnProceso, EstadoTareaMovimiento::Completada],
            true,
        ))) {
            return 'prefijo_fisico_iniciado';
        }
        if ($maniobra->custodiasTemporales()
            ->where('estado', EstadoCustodiaTemporal::Activa->value)
            ->lockForUpdate()
            ->exists()) {
            return 'custodia_temporal_activa';
        }
        if ($pasos->contains(
            fn (TareaMovimiento $paso): bool => $paso->estado === EstadoTareaMovimiento::Asumida,
        )) {
            return 'tarea_asumida';
        }
        if (ReservaTareaMovimiento::query()
            ->whereIn('bloqueo_tarea_id', $pasos->pluck('id'))
            ->where('estado', EstadoReservaTareaMovimiento::Activa->value)
            ->lockForUpdate()
            ->exists()) {
            return 'reserva_activa';
        }

        return null;
    }

    /**
     * @param  Collection<int, TareaMovimiento>  $pasos
     */
    private function pasoActual(
        ManiobraOperacional $maniobra,
        Collection $pasos,
    ): ?TareaMovimiento {
        return $pasos->firstWhere('secuencia_maniobra', $maniobra->secuencia_actual)
            ?? $pasos->first(fn (TareaMovimiento $paso): bool => ! $paso->estado->esFinal());
    }

    private function validarVersion(
        ManiobraOperacional $maniobra,
        int $versionEsperada,
    ): void {
        if ($maniobra->version !== $versionEsperada) {
            throw new ConflictoOperacion(
                'La maniobra cambió mientras se revisaba. Actualiza el detalle antes de intervenir.',
            );
        }
    }

    private function transicionAplicada(
        string $tipo,
        string $operacionId,
    ): ?TransicionOperacional {
        return TransicionOperacional::query()
            ->where('dominio', DominioTransicionOperacional::Planificador->value)
            ->where('tipo', $tipo)
            ->where('operacion_id', $operacionId)
            ->where('estado', EstadoTransicionOperacional::Aplicada->value)
            ->first();
    }

    /** @return array<string, mixed> */
    private function contextoSupervision(ManiobraOperacional $maniobra): array
    {
        $contexto = $maniobra->contexto['supervision'] ?? [];

        return is_array($contexto) ? $contexto : [];
    }
}
