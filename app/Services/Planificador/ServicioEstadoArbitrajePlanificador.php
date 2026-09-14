<?php

namespace App\Services\Planificador;

use App\Jobs\RecalcularArbitrajePlanificador;
use App\Models\CicloArbitrajeManiobras;
use App\Models\EstadoArbitrajePlanificador;
use App\Models\Temporada;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ServicioEstadoArbitrajePlanificador
{
    public function solicitar(Temporada|string $temporada, string $motivo): void
    {
        $temporadaId = $temporada instanceof Temporada ? $temporada->id : $temporada;
        if (! $this->modoActivo()
            && ! EstadoArbitrajePlanificador::query()->whereKey($temporadaId)->exists()) {
            return;
        }

        $this->obtenerOCrear($temporadaId);

        EstadoArbitrajePlanificador::query()
            ->whereKey($temporadaId)
            ->increment('version_solicitada', 1, [
                'solicitado_at' => now(),
                'ultimo_motivo' => mb_substr($motivo, 0, 80),
            ]);

        $this->despachar($temporadaId);
    }

    public function asegurarProgramado(Temporada $temporada, bool $forzar = false): bool
    {
        if (! $this->modoActivo()) {
            return false;
        }

        $estado = EstadoArbitrajePlanificador::query()->find($temporada->id);
        if (! $estado || $forzar) {
            $this->solicitar($temporada, $forzar ? 'recalculo_manual' : 'inicio_planificador');

            return true;
        }

        if ($estado->version_solicitada > $estado->version_calculada) {
            $this->despachar($temporada->id);

            return true;
        }

        $refresco = max(60, (int) config('planificador.arbitraje_refresco_segundos', 240));
        if (! $estado->calculado_at || $estado->calculado_at->lte(now()->subSeconds($refresco))) {
            $this->solicitar($temporada, 'watchdog_vigencia');

            return true;
        }

        return false;
    }

    public function iniciarCalculo(string $temporadaId): int
    {
        return DB::transaction(function () use ($temporadaId): int {
            $estado = $this->obtenerOCrear($temporadaId, bloquear: true);
            $esperaMs = $estado->solicitado_at
                ? max(0, (int) $estado->solicitado_at->diffInMilliseconds(now()))
                : 0;
            $estado->update([
                'iniciado_at' => now(),
                'espera_ultimo_calculo_ms' => $esperaMs,
                'fallo_at' => null,
                'ultimo_error' => null,
            ]);

            return $estado->version_solicitada;
        }, attempts: 3);
    }

    public function completarCalculo(
        string $temporadaId,
        int $versionCalculada,
        CicloArbitrajeManiobras $ciclo,
        int $duracionMs,
    ): void {
        DB::transaction(function () use (
            $temporadaId,
            $versionCalculada,
            $ciclo,
            $duracionMs,
        ): void {
            $estado = $this->obtenerOCrear($temporadaId, bloquear: true);
            $estado->update([
                'ultimo_ciclo_id' => $ciclo->id,
                'version_calculada' => max($estado->version_calculada, $versionCalculada),
                'calculos_exitosos' => $estado->calculos_exitosos + 1,
                'duracion_ultimo_calculo_ms' => max(0, $duracionMs),
                'calculado_at' => now(),
                'iniciado_at' => null,
                'fallo_at' => null,
                'ultimo_error' => null,
            ]);
        }, attempts: 3);
    }

    public function registrarFallo(
        string $temporadaId,
        Throwable $error,
        int $duracionMs,
    ): void {
        DB::transaction(function () use ($temporadaId, $error, $duracionMs): void {
            $estado = $this->obtenerOCrear($temporadaId, bloquear: true);
            $estado->update([
                'calculos_fallidos' => $estado->calculos_fallidos + 1,
                'duracion_ultimo_calculo_ms' => max(0, $duracionMs),
                'iniciado_at' => null,
                'fallo_at' => now(),
                'ultimo_error' => mb_substr($error->getMessage(), 0, 500),
            ]);
        }, attempts: 3);
    }

    public function requiereRecalculo(string $temporadaId): bool
    {
        $estado = EstadoArbitrajePlanificador::query()->find($temporadaId);

        return $estado !== null
            && $estado->version_solicitada > $estado->version_calculada;
    }

    /**
     * Consulta exclusivamente la proyección persistida. Este método no calcula,
     * no despacha trabajos y no adquiere bloqueos sobre maniobras.
     *
     * @return array{estado:string,vigente:bool,detalle:string,solicitado_at:?string,iniciado_at:?string,evaluado_at:?string,edad_segundos:?int,umbral_atraso_segundos:int,version_solicitada:int,version_calculada:int,calculos_exitosos:int,calculos_fallidos:int,espera_ultimo_calculo_ms:?int,duracion_ultimo_calculo_ms:?int,ultimo_error_at:?string,ciclo:?CicloArbitrajeManiobras}
     */
    public function consultar(Temporada $temporada): array
    {
        $umbral = max(30, (int) config('planificador.arbitraje_atrasado_segundos', 300));
        if (! $this->modoActivo()) {
            return $this->respuesta(
                estado: 'detenido',
                vigente: false,
                detalle: 'El planificador está detenido por configuración.',
                umbral: $umbral,
            );
        }

        $estado = EstadoArbitrajePlanificador::query()
            ->with('ultimoCiclo')
            ->find($temporada->id);
        $ciclo = $estado?->ultimoCiclo
            ?? CicloArbitrajeManiobras::query()
                ->where('temporada_id', $temporada->id)
                ->latest('created_at')
                ->latest('id')
                ->first();

        if (! $estado) {
            return $this->respuesta(
                estado: $ciclo ? 'atrasado' : 'pendiente',
                vigente: false,
                detalle: $ciclo
                    ? 'Existe un ciclo anterior, pero aún no ha sido confirmado por el recálculo desacoplado.'
                    : 'El primer cálculo de arbitraje está pendiente.',
                umbral: $umbral,
                ciclo: $ciclo,
            );
        }

        $pendiente = $estado->version_solicitada > $estado->version_calculada;
        $edad = $estado->calculado_at
            ? max(0, (int) $estado->calculado_at->diffInSeconds(now()))
            : null;
        $falloVigente = $pendiente
            && $estado->fallo_at !== null
            && ($estado->calculado_at === null || $estado->fallo_at->gt($estado->calculado_at));
        $calculando = $pendiente
            && $estado->iniciado_at !== null
            && ($estado->fallo_at === null || $estado->iniciado_at->gt($estado->fallo_at));
        $espera = $estado->solicitado_at
            ? max(0, (int) $estado->solicitado_at->diffInSeconds(now()))
            : 0;

        if ($falloVigente) {
            [$codigo, $detalle] = ['error', 'Falló el último recálculo; se conserva el ciclo confirmado anterior.'];
        } elseif ($calculando) {
            [$codigo, $detalle] = $espera > $umbral
                ? ['atrasado', 'El recálculo continúa, pero superó el tiempo operacional esperado.']
                : ['recalculando', 'El árbitro está evaluando los cambios operacionales.'];
        } elseif ($pendiente) {
            [$codigo, $detalle] = $espera > $umbral
                ? ['atrasado', 'Los cambios operacionales aún no han sido evaluados.']
                : ['pendiente', 'Hay cambios operacionales esperando recálculo.'];
        } elseif (! $ciclo) {
            [$codigo, $detalle] = ['pendiente', 'No existe todavía un ciclo de arbitraje confirmado.'];
        } elseif ($edad !== null && $edad > $umbral) {
            [$codigo, $detalle] = ['atrasado', 'La última evaluación superó el umbral de vigencia.'];
        } else {
            [$codigo, $detalle] = ['actual', 'El ciclo representa el último estado operacional evaluado.'];
        }

        return $this->respuesta(
            estado: $codigo,
            vigente: $codigo === 'actual',
            detalle: $detalle,
            umbral: $umbral,
            ciclo: $ciclo,
            estadoPersistido: $estado,
            edad: $edad,
        );
    }

    private function modoActivo(): bool
    {
        return in_array(config('planificador.mode'), ['shadow', 'guided'], true);
    }

    private function despachar(string $temporadaId): void
    {
        if (! $this->modoActivo() || config('queue.default') === 'sync') {
            return;
        }

        RecalcularArbitrajePlanificador::dispatch($temporadaId);
    }

    private function obtenerOCrear(
        string $temporadaId,
        bool $bloquear = false,
    ): EstadoArbitrajePlanificador {
        $consulta = EstadoArbitrajePlanificador::query()->whereKey($temporadaId);
        if ($bloquear) {
            $consulta->lockForUpdate();
        }
        $estado = $consulta->first();
        if ($estado) {
            return $estado;
        }

        try {
            return EstadoArbitrajePlanificador::create([
                'temporada_id' => $temporadaId,
                'version_solicitada' => 0,
                'version_calculada' => 0,
                'solicitado_at' => now(),
                'ultimo_motivo' => 'inicio_planificador',
            ]);
        } catch (UniqueConstraintViolationException) {
            $consulta = EstadoArbitrajePlanificador::query()->whereKey($temporadaId);
            if ($bloquear) {
                $consulta->lockForUpdate();
            }

            return $consulta->firstOrFail();
        }
    }

    /** @return array<string, mixed> */
    private function respuesta(
        string $estado,
        bool $vigente,
        string $detalle,
        int $umbral,
        ?CicloArbitrajeManiobras $ciclo = null,
        ?EstadoArbitrajePlanificador $estadoPersistido = null,
        ?int $edad = null,
    ): array {
        return [
            'estado' => $estado,
            'vigente' => $vigente,
            'detalle' => $detalle,
            'solicitado_at' => $estadoPersistido?->solicitado_at?->toIso8601String(),
            'iniciado_at' => $estadoPersistido?->iniciado_at?->toIso8601String(),
            'evaluado_at' => $estadoPersistido?->calculado_at?->toIso8601String(),
            'edad_segundos' => $edad,
            'umbral_atraso_segundos' => $umbral,
            'version_solicitada' => $estadoPersistido?->version_solicitada ?? 0,
            'version_calculada' => $estadoPersistido?->version_calculada ?? 0,
            'calculos_exitosos' => $estadoPersistido?->calculos_exitosos ?? 0,
            'calculos_fallidos' => $estadoPersistido?->calculos_fallidos ?? 0,
            'espera_ultimo_calculo_ms' => $estadoPersistido?->espera_ultimo_calculo_ms,
            'duracion_ultimo_calculo_ms' => $estadoPersistido?->duracion_ultimo_calculo_ms,
            'ultimo_error_at' => $estadoPersistido?->fallo_at?->toIso8601String(),
            'ciclo' => $ciclo,
        ];
    }
}
