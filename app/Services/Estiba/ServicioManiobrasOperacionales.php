<?php

namespace App\Services\Estiba;

use App\Enums\EstadoCustodiaTemporal;
use App\Enums\EstadoDiscrepanciaManiobra;
use App\Enums\EstadoManiobraOperacional;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPasoManiobra;
use App\Exceptions\ConflictoOperacion;
use App\Models\Carga;
use App\Models\CustodiaTemporalManiobra;
use App\Models\DiscrepanciaManiobra;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\ManiobraOperacional;
use App\Models\Movimiento;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\ReservaBandaManiobra;
use App\Models\TareaMovimiento;
use App\Models\User;
use App\Services\Cargas\ServicioPlanConcentracionCarga;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioManiobrasOperacionales
{
    private const MAX_MANIOBRAS_SIMULTANEAS = 3;

    public function __construct(
        private readonly ServicioReservasTareasMovimiento $reservas,
    ) {}

    /**
     * Persiste una solución física completa. Los pasos posteriores permanecen
     * bloqueados hasta que el movimiento anterior se confirme.
     *
     * @param  array{
     *   candidate_key:string,
     *   titulo:string,
     *   motivo?:string|null,
     *   beneficio_estimado?:int,
     *   riesgo_operacional?:int,
     *   contexto?:array<string,mixed>,
     *   bloqueos_banda?:array<int,array{camara_id:string,banda:int,nivel:int}>,
     *   pasos:array<int,array<string,mixed>>
     * }  $datos
     */
    public function crearCerrada(
        PlanOperacional $plan,
        User $usuario,
        array $datos,
    ): ManiobraOperacional {
        return DB::transaction(function () use ($plan, $usuario, $datos): ManiobraOperacional {
            $plan = PlanOperacional::query()->lockForUpdate()->findOrFail($plan->id);
            $pasos = array_values($datos['pasos'] ?? []);
            $this->validarCierre($pasos);
            if (! User::query()->whereKey($usuario->id)->where('activo', true)->exists()) {
                throw new DomainException('El usuario que origina la maniobra no se encuentra activo.');
            }

            $clave = trim((string) ($datos['candidate_key'] ?? ''));
            if ($clave === '' || mb_strlen($clave) > 190) {
                throw new DomainException('La maniobra requiere una clave candidata válida.');
            }
            if (ManiobraOperacional::query()
                ->where('plan_operacional_id', $plan->id)
                ->where('candidate_key', $clave)
                ->whereIn('estado', [
                    EstadoManiobraOperacional::Pendiente->value,
                    EstadoManiobraOperacional::EnEjecucion->value,
                    EstadoManiobraOperacional::PausadaDiscrepancia->value,
                ])
                ->exists()) {
                throw new ConflictoOperacion('La maniobra candidata ya fue publicada.');
            }

            $folioIds = collect($pasos)->pluck('folio_id')->unique()->values();
            $folios = Folio::query()
                ->whereIn('id', $folioIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($folios->count() !== $folioIds->count()
                || $folios->contains(fn (Folio $folio): bool => $folio->temporada_id !== $plan->temporada_id
                    || ! $folio->activo
                    || $folio->tipo_bulto !== TipoBulto::Pallet)) {
                throw new DomainException('La maniobra solo admite pallets completos activos de la temporada.');
            }

            $conflicto = TareaMovimiento::query()
                ->whereIn('folio_id', $folioIds)
                ->whereIn('estado', $this->estadosActivosTarea())
                ->lockForUpdate()
                ->exists();
            if ($conflicto) {
                throw new ConflictoOperacion('Un pallet de la maniobra ya posee otra labor activa.');
            }

            $maniobra = ManiobraOperacional::create([
                'plan_operacional_id' => $plan->id,
                'creado_por_user_id' => $usuario->id,
                'estado' => EstadoManiobraOperacional::Pendiente,
                'prioridad' => $plan->prioridad,
                'candidate_key' => $clave,
                'titulo' => Str::limit(trim((string) ($datos['titulo'] ?? $plan->titulo)), 180, ''),
                'motivo' => filled($datos['motivo'] ?? null)
                    ? trim((string) $datos['motivo'])
                    : null,
                'secuencia_actual' => 1,
                'costo_movimientos' => count($pasos),
                'beneficio_estimado' => (int) ($datos['beneficio_estimado'] ?? 0),
                'riesgo_operacional' => max(0, (int) ($datos['riesgo_operacional'] ?? 0)),
                'contexto' => ($datos['contexto'] ?? []) ?: null,
            ]);
            $maniobra->objetivos()->attach($plan->id, [
                'es_principal' => true,
                'beneficio_estimado' => (int) ($datos['beneficio_estimado'] ?? 0),
                'contexto' => json_encode([
                    'candidate_key' => $clave,
                    'tipo_objetivo' => $plan->tipo->value,
                ], JSON_THROW_ON_ERROR),
            ]);

            $secuenciaPlan = (int) TareaMovimiento::query()
                ->where('plan_operacional_id', $plan->id)
                ->lockForUpdate()
                ->max('secuencia');
            foreach ($pasos as $indice => $paso) {
                $tipo = $paso['tipo_movimiento'] ?? null;
                $tipoPaso = $paso['tipo_paso_maniobra'] ?? null;
                if (! $tipo instanceof TipoMovimiento || ! $tipoPaso instanceof TipoPasoManiobra) {
                    throw new DomainException('Cada paso requiere un tipo físico y un tipo de maniobra válidos.');
                }

                TareaMovimiento::create([
                    'plan_operacional_id' => $plan->id,
                    'maniobra_operacional_id' => $maniobra->id,
                    'secuencia' => ++$secuenciaPlan,
                    'secuencia_maniobra' => $indice + 1,
                    'tipo_movimiento' => $tipo,
                    'tipo_paso_maniobra' => $tipoPaso,
                    'estado' => $indice === 0
                        ? EstadoTareaMovimiento::Pendiente
                        : EstadoTareaMovimiento::Bloqueada,
                    'prioridad' => $paso['prioridad'] ?? $plan->prioridad,
                    'folio_id' => $paso['folio_id'],
                    'camara_origen_id' => $paso['camara_origen_id'] ?? null,
                    'posicion_origen_id' => $paso['posicion_origen_id'] ?? null,
                    'camara_destino_id' => $paso['camara_destino_id'] ?? null,
                    'posicion_destino_id' => $paso['posicion_destino_id'] ?? null,
                    'instruccion' => filled($paso['instruccion'] ?? null)
                        ? trim((string) $paso['instruccion'])
                        : null,
                    'contexto' => [
                        ...($paso['contexto'] ?? []),
                        'candidate_key' => $clave,
                        'maniobra_cerrada' => true,
                        'paso' => $indice + 1,
                        'pasos_totales' => count($pasos),
                    ],
                ]);
            }

            foreach ($datos['bloqueos_banda'] ?? [] as $bloqueo) {
                ReservaBandaManiobra::create([
                    'maniobra_operacional_id' => $maniobra->id,
                    'camara_id' => $bloqueo['camara_id'],
                    'banda' => (int) $bloqueo['banda'],
                    'nivel' => (int) $bloqueo['nivel'],
                    'reservada_at' => now(),
                ]);
            }

            $plan->update(['version' => $plan->version + 1]);

            return $this->cargar($maniobra);
        }, attempts: 3);
    }

    public function asumirPaso(
        TareaMovimiento $tarea,
        User $usuario,
        Dispositivo $dispositivo,
    ): TareaMovimiento {
        $maniobra = ManiobraOperacional::query()
            ->with('reservasBandas')
            ->lockForUpdate()
            ->findOrFail($tarea->maniobra_operacional_id);
        $tarea = TareaMovimiento::query()
            ->with('planOperacional')
            ->lockForUpdate()
            ->findOrFail($tarea->id);

        $this->validarPasoActualInterno($maniobra, $tarea);
        if ($maniobra->estado === EstadoManiobraOperacional::PausadaDiscrepancia) {
            throw new ConflictoOperacion('La maniobra está pausada por una discrepancia física.');
        }
        if ($maniobra->responsable_user_id !== null
            && ($maniobra->responsable_user_id !== $usuario->id
                || $maniobra->dispositivo_id !== $dispositivo->id)) {
            throw new ConflictoOperacion('La maniobra ya pertenece a otro camarero o tablet.');
        }
        if ($maniobra->estado !== EstadoManiobraOperacional::EnEjecucion
            && ManiobraOperacional::query()
                ->whereKeyNot($maniobra->id)
                ->where('estado', EstadoManiobraOperacional::EnEjecucion->value)
                ->lockForUpdate()
                ->count() >= self::MAX_MANIOBRAS_SIMULTANEAS) {
            throw new ConflictoOperacion(
                'Ya existen tres maniobras asumidas; la cuarta debe permanecer como alternativa.',
            );
        }

        $this->bloquearBandas($maniobra);
        $ahora = now();
        $maniobra->update([
            'estado' => EstadoManiobraOperacional::EnEjecucion,
            'responsable_user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'asumida_at' => $maniobra->asumida_at ?? $ahora,
            'iniciada_at' => $maniobra->iniciada_at ?? $ahora,
            'version' => $maniobra->version + 1,
        ]);
        $this->reservas->asumir($tarea, $usuario, $dispositivo);
        $this->materializarDestinoPrecalculado($tarea->refresh(), $usuario, $dispositivo);

        return $tarea->refresh();
    }

    public function liberarAntesDeIniciar(
        TareaMovimiento $tarea,
        User $usuario,
        Dispositivo $dispositivo,
    ): void {
        $maniobra = ManiobraOperacional::query()->lockForUpdate()->findOrFail(
            $tarea->maniobra_operacional_id,
        );
        $this->validarPasoActualInterno($maniobra, $tarea);
        if ($tarea->estado === EstadoTareaMovimiento::EnProceso
            || $maniobra->custodiasTemporales()
                ->where('estado', EstadoCustodiaTemporal::Activa->value)
                ->exists()) {
            throw new ConflictoOperacion(
                'La maniobra ya modificó la realidad física y no puede liberarse.',
            );
        }

        $this->reservas->liberar($tarea, $usuario, $dispositivo);
        $this->liberarBandas($maniobra, 'Maniobra devuelta a la bandeja antes de iniciar.');
        $poseePrefijoFisico = $maniobra->pasos()
            ->where('estado', EstadoTareaMovimiento::Completada->value)
            ->lockForUpdate()
            ->exists();
        $maniobra->update([
            'estado' => EstadoManiobraOperacional::Pendiente,
            'responsable_user_id' => null,
            'dispositivo_id' => null,
            'asumida_at' => $poseePrefijoFisico ? $maniobra->asumida_at : null,
            'iniciada_at' => $poseePrefijoFisico ? $maniobra->iniciada_at : null,
            'version' => $maniobra->version + 1,
        ]);
    }

    public function avanzarTrasMovimiento(TareaMovimiento $tarea, Movimiento $movimiento): void
    {
        if (! $tarea->maniobra_operacional_id) {
            return;
        }

        $maniobra = ManiobraOperacional::query()->lockForUpdate()->findOrFail(
            $tarea->maniobra_operacional_id,
        );
        $tarea = TareaMovimiento::query()->lockForUpdate()->findOrFail($tarea->id);
        $this->registrarCustodia($maniobra, $tarea, $movimiento);

        if ($maniobra->estado === EstadoManiobraOperacional::PausadaDiscrepancia) {
            return;
        }

        $siguiente = $maniobra->pasos()
            ->where('secuencia_maniobra', '>', $tarea->secuencia_maniobra)
            ->orderBy('secuencia_maniobra')
            ->lockForUpdate()
            ->first();
        if (! $siguiente) {
            if ($maniobra->custodiasTemporales()
                ->where('estado', EstadoCustodiaTemporal::Activa->value)
                ->lockForUpdate()
                ->exists()) {
                throw new DomainException(
                    'La maniobra no puede cerrar con pallets extraídos temporalmente.',
                );
            }

            $this->liberarBandas($maniobra, 'Maniobra física completada.');
            $maniobra->update([
                'estado' => EstadoManiobraOperacional::Completada,
                'completada_at' => now(),
                'secuencia_actual' => $tarea->secuencia_maniobra,
                'version' => $maniobra->version + 1,
            ]);
            $plan = $maniobra->planOperacional()->first();
            $cargaId = $plan?->referencia_tipo === 'carga_concentracion'
                ? $plan->referencia_id
                : null;
            if ($cargaId) {
                $usuarioId = $movimiento->user_id;
                DB::afterCommit(function () use ($cargaId, $usuarioId): void {
                    $carga = Carga::query()->find($cargaId);
                    $usuario = User::query()->find($usuarioId);
                    if ($carga && $usuario) {
                        app(ServicioPlanConcentracionCarga::class)->sincronizar($carga, $usuario);
                    }
                });
            }

            return;
        }

        $siguiente->update([
            'estado' => EstadoTareaMovimiento::Pendiente,
            'version' => $siguiente->version + 1,
        ]);
        $maniobra->update([
            'secuencia_actual' => $siguiente->secuencia_maniobra,
            'version' => $maniobra->version + 1,
        ]);

        $usuario = User::query()->findOrFail($maniobra->responsable_user_id);
        $dispositivo = Dispositivo::query()->findOrFail($maniobra->dispositivo_id);
        $this->reservas->asumir($siguiente->refresh(), $usuario, $dispositivo);
    }

    public function reportarDiscrepancia(
        TareaMovimiento $tarea,
        User $usuario,
        Dispositivo $dispositivo,
        string $tipo,
        ?string $detalle = null,
    ): DiscrepanciaManiobra {
        return DB::transaction(function () use (
            $tarea,
            $usuario,
            $dispositivo,
            $tipo,
            $detalle,
        ): DiscrepanciaManiobra {
            $tarea = TareaMovimiento::query()->lockForUpdate()->findOrFail($tarea->id);
            if (! $tarea->maniobra_operacional_id) {
                throw new DomainException('La tarea no pertenece a una maniobra física.');
            }
            $maniobra = ManiobraOperacional::query()->lockForUpdate()->findOrFail(
                $tarea->maniobra_operacional_id,
            );
            $this->validarPasoActualInterno($maniobra, $tarea);
            if ($maniobra->estado === EstadoManiobraOperacional::PausadaDiscrepancia) {
                throw new ConflictoOperacion('La maniobra ya se encuentra pausada.');
            }
            if ($maniobra->responsable_user_id !== $usuario->id
                || $maniobra->dispositivo_id !== $dispositivo->id) {
                throw new ConflictoOperacion('La maniobra pertenece a otro camarero o tablet.');
            }

            $discrepancia = DiscrepanciaManiobra::create([
                'maniobra_operacional_id' => $maniobra->id,
                'tarea_movimiento_id' => $tarea->id,
                'folio_id' => $tarea->folio_id,
                'tipo' => Str::limit(trim($tipo), 50, ''),
                'detalle' => filled($detalle) ? trim((string) $detalle) : null,
                'estado' => EstadoDiscrepanciaManiobra::Abierta,
                'reportada_por_user_id' => $usuario->id,
                'dispositivo_id' => $dispositivo->id,
                'reportada_at' => now(),
            ]);
            $maniobra->update([
                'estado' => EstadoManiobraOperacional::PausadaDiscrepancia,
                'pausada_at' => now(),
                'version' => $maniobra->version + 1,
            ]);

            if ($tarea->estado !== EstadoTareaMovimiento::EnProceso) {
                $this->reservas->liberarParaReplanificacion(
                    $tarea,
                    'Discrepancia física reportada.',
                );
                $tarea->update([
                    'estado' => EstadoTareaMovimiento::Bloqueada,
                    'version' => $tarea->version + 1,
                ]);
                $poseeCustodiaActiva = $maniobra->custodiasTemporales()
                    ->where('estado', EstadoCustodiaTemporal::Activa->value)
                    ->lockForUpdate()
                    ->exists();
                if (! $poseeCustodiaActiva) {
                    $this->liberarBandas(
                        $maniobra,
                        'Maniobra pausada antes de modificar la realidad física.',
                    );
                }
            }

            return $discrepancia;
        }, attempts: 3);
    }

    public function cancelarReversible(
        ManiobraOperacional $maniobra,
        User $usuario,
        string $motivo,
    ): bool {
        return DB::transaction(function () use ($maniobra, $usuario, $motivo): bool {
            $maniobra = ManiobraOperacional::query()->lockForUpdate()->findOrFail($maniobra->id);
            if ($maniobra->estado->esFinal()) {
                return true;
            }
            if ($maniobra->pasos()
                ->where('estado', EstadoTareaMovimiento::EnProceso->value)
                ->lockForUpdate()
                ->exists()
                || $maniobra->custodiasTemporales()
                    ->where('estado', EstadoCustodiaTemporal::Activa->value)
                    ->lockForUpdate()
                    ->exists()) {
                return false;
            }

            $pasos = $maniobra->pasos()
                ->whereIn('estado', [
                    EstadoTareaMovimiento::Bloqueada->value,
                    EstadoTareaMovimiento::Pendiente->value,
                    EstadoTareaMovimiento::Asumida->value,
                ])
                ->lockForUpdate()
                ->get();
            foreach ($pasos as $paso) {
                $this->reservas->liberarParaReplanificacion($paso, $motivo);
                $paso->update([
                    'estado' => EstadoTareaMovimiento::Cancelada,
                    'cancelada_at' => now(),
                    'cancelada_por_user_id' => $usuario->id,
                    'motivo_cancelacion' => Str::limit(trim($motivo), 255, ''),
                    'version' => $paso->version + 1,
                ]);
            }
            $this->liberarBandas($maniobra, $motivo);
            $maniobra->update([
                'estado' => EstadoManiobraOperacional::Cancelada,
                'cancelada_at' => now(),
                'motivo_cancelacion' => Str::limit(trim($motivo), 255, ''),
                'version' => $maniobra->version + 1,
            ]);

            return true;
        }, attempts: 3);
    }

    public function validarPasoActual(TareaMovimiento $tarea): void
    {
        if (! $tarea->maniobra_operacional_id) {
            return;
        }
        $maniobra = ManiobraOperacional::query()->findOrFail($tarea->maniobra_operacional_id);
        $this->validarPasoActualInterno($maniobra, $tarea);
    }

    /** @param array<int, array<string, mixed>> $pasos */
    private function validarCierre(array $pasos): void
    {
        if ($pasos === []) {
            throw new DomainException('La maniobra requiere al menos un paso físico.');
        }

        $temporales = [];
        foreach ($pasos as $indice => $paso) {
            $folioId = $paso['folio_id'] ?? null;
            $tipoMovimiento = $paso['tipo_movimiento'] ?? null;
            $tipoPaso = $paso['tipo_paso_maniobra'] ?? null;
            if (! is_string($folioId) || ! Str::isUuid($folioId)) {
                throw new DomainException('Cada paso debe identificar un pallet válido.');
            }
            if (! $tipoMovimiento instanceof TipoMovimiento
                || ! $tipoPaso instanceof TipoPasoManiobra
                || $tipoMovimiento === TipoMovimiento::Reversion) {
                throw new DomainException(
                    'Cada paso requiere un tipo físico y un tipo de maniobra válidos.',
                );
            }
            if ($tipoPaso === TipoPasoManiobra::ExtraccionTemporal) {
                if ($tipoMovimiento !== TipoMovimiento::Retiro
                    || empty($paso['camara_origen_id'])
                    || empty($paso['posicion_origen_id'])
                    || ! empty($paso['camara_destino_id'])
                    || ! empty($paso['posicion_destino_id'])) {
                    throw new DomainException(
                        'Una extracción temporal debe retirar el pallet desde un origen físico y sin destino permanente.',
                    );
                }
                if (isset($temporales[$folioId])) {
                    throw new DomainException('Un pallet no puede quedar dos veces en custodia temporal.');
                }
                $temporales[$folioId] = [
                    'indice' => $indice,
                    'camara_id' => $paso['camara_origen_id'],
                    'banda' => $paso['contexto']['banda_retorno'] ?? null,
                    'nivel' => $paso['contexto']['nivel_retorno'] ?? null,
                ];
            }
            if ($tipoPaso === TipoPasoManiobra::RetornoBanda) {
                $temporal = $temporales[$folioId] ?? null;
                if (! is_array($temporal) || $temporal['indice'] >= $indice) {
                    throw new DomainException('Todo retorno debe resolver una extracción temporal anterior.');
                }
                if ($tipoMovimiento !== TipoMovimiento::UbicacionInicial
                    || ! empty($paso['camara_origen_id'])
                    || ! empty($paso['posicion_origen_id'])
                    || empty($paso['camara_destino_id'])
                    || $paso['camara_destino_id'] !== $temporal['camara_id']
                    || ($paso['contexto']['banda_retorno'] ?? null) !== $temporal['banda']
                    || ($paso['contexto']['nivel_retorno'] ?? null) !== $temporal['nivel']) {
                    throw new DomainException(
                        'El retorno debe resolver la custodia en la misma banda y nivel protegidos.',
                    );
                }
                if ((int) ($paso['contexto']['profundidad_resultante'] ?? 0) < 1) {
                    throw new DomainException(
                        'El retorno debe declarar una profundidad resultante válida.',
                    );
                }
                unset($temporales[$folioId]);
            }
        }

        if ($temporales !== []) {
            throw new DomainException(
                'No se puede publicar una maniobra con pallets temporales sin retorno.',
            );
        }
    }

    private function registrarCustodia(
        ManiobraOperacional $maniobra,
        TareaMovimiento $tarea,
        Movimiento $movimiento,
    ): void {
        if ($tarea->tipo_paso_maniobra === TipoPasoManiobra::ExtraccionTemporal) {
            $origen = Posicion::query()->findOrFail($movimiento->posicion_origen_id);
            CustodiaTemporalManiobra::create([
                'maniobra_operacional_id' => $maniobra->id,
                'folio_id' => $tarea->folio_id,
                'tarea_extraccion_id' => $tarea->id,
                'camara_origen_id' => $origen->camara_id,
                'posicion_origen_id' => $origen->id,
                'banda_origen' => $origen->banda,
                'posicion_origen' => $origen->posicion,
                'nivel_origen' => $origen->nivel,
                'estado' => EstadoCustodiaTemporal::Activa,
                'bloqueo_folio_id' => $tarea->folio_id,
                'user_id' => $movimiento->user_id,
                'dispositivo_id' => $movimiento->dispositivo_id,
                'extraido_at' => $movimiento->recibido_servidor_at,
                'contexto' => [
                    'movimiento_extraccion_id' => $movimiento->id,
                    'sin_ubicacion_permanente' => true,
                ],
            ]);

            return;
        }

        if ($tarea->tipo_paso_maniobra !== TipoPasoManiobra::RetornoBanda) {
            return;
        }

        $custodia = CustodiaTemporalManiobra::query()
            ->where('maniobra_operacional_id', $maniobra->id)
            ->where('bloqueo_folio_id', $tarea->folio_id)
            ->lockForUpdate()
            ->first();
        if (! $custodia) {
            throw new DomainException('El retorno no posee una custodia temporal activa.');
        }
        $custodia->update([
            'tarea_resolucion_id' => $tarea->id,
            'estado' => EstadoCustodiaTemporal::ResueltaRetorno,
            'bloqueo_folio_id' => null,
            'resuelto_at' => $movimiento->recibido_servidor_at,
            'contexto' => [
                ...($custodia->contexto ?? []),
                'movimiento_resolucion_id' => $movimiento->id,
                'posicion_resultante_id' => $movimiento->posicion_destino_id,
            ],
        ]);
    }

    private function bloquearBandas(ManiobraOperacional $maniobra): void
    {
        foreach ($maniobra->reservasBandas as $reserva) {
            if ($reserva->clave_bloqueo) {
                continue;
            }
            try {
                $reserva->update([
                    'clave_bloqueo' => implode(':', [
                        $reserva->camara_id,
                        $reserva->banda,
                        $reserva->nivel,
                    ]),
                    'reservada_at' => now(),
                    'liberada_at' => null,
                    'motivo_liberacion' => null,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                throw new ConflictoOperacion(
                    'La banda requerida está comprometida por otra maniobra.',
                    previous: $exception,
                );
            }
        }
    }

    private function liberarBandas(ManiobraOperacional $maniobra, string $motivo): void
    {
        $maniobra->reservasBandas()
            ->whereNull('liberada_at')
            ->update([
                'clave_bloqueo' => null,
                'liberada_at' => now(),
                'motivo_liberacion' => Str::limit(trim($motivo), 255, ''),
            ]);
    }

    private function materializarDestinoPrecalculado(
        TareaMovimiento $tarea,
        User $usuario,
        Dispositivo $dispositivo,
    ): void {
        if (! $tarea->posicion_destino_id || $tarea->tipo_movimiento === TipoMovimiento::Retiro) {
            return;
        }
        $this->reservas->materializarDestino(
            $tarea,
            Posicion::query()->findOrFail($tarea->posicion_destino_id),
            $usuario,
            $dispositivo,
        );
    }

    private function validarPasoActualInterno(
        ManiobraOperacional $maniobra,
        TareaMovimiento $tarea,
    ): void {
        if ($maniobra->estado->esFinal()) {
            throw new ConflictoOperacion('La maniobra ya está finalizada.');
        }
        if ($tarea->maniobra_operacional_id !== $maniobra->id
            || $tarea->secuencia_maniobra !== $maniobra->secuencia_actual
            || $tarea->estado === EstadoTareaMovimiento::Bloqueada) {
            throw new ConflictoOperacion('El paso todavía no corresponde en la secuencia física.');
        }
    }

    private function cargar(ManiobraOperacional $maniobra): ManiobraOperacional
    {
        return $maniobra->load(['pasos', 'objetivos', 'reservasBandas']);
    }

    /** @return array<int, string> */
    private function estadosActivosTarea(): array
    {
        return [
            EstadoTareaMovimiento::Bloqueada->value,
            EstadoTareaMovimiento::Pendiente->value,
            EstadoTareaMovimiento::Asumida->value,
            EstadoTareaMovimiento::EnProceso->value,
        ];
    }
}
