<?php

namespace App\Services\IntegridadOperacional;

use App\Enums\EstadoAuditoriaIntegridadOperacional;
use App\Enums\OrigenAuditoriaIntegridadOperacional;
use App\Enums\SeveridadHallazgoIntegridadOperacional;
use App\Exceptions\ConflictoOperacion;
use App\Models\AuditoriaIntegridadOperacional;
use App\Models\HallazgoIntegridadOperacional;
use App\Models\User;
use App\Services\IntegridadOperacional\Reglas\ReglaBalanceRepaletizaje;
use App\Services\IntegridadOperacional\Reglas\ReglaIntegridadOperacional;
use App\Services\IntegridadOperacional\Reglas\ReglaProyeccionPrefrio;
use App\Services\IntegridadOperacional\Reglas\ReglaReservaCarga;
use App\Services\IntegridadOperacional\Reglas\ReglaReservaMaterial;
use App\Services\IntegridadOperacional\Reglas\ReglaUbicacionFolio;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ServicioAuditoriaIntegridadOperacional
{
    private const CLAVE_BLOQUEO = 'integridad-operacional:auditoria';

    /** @var array<int, ReglaIntegridadOperacional> */
    private array $reglas;

    public function __construct(
        ReglaProyeccionPrefrio $reglaProyeccionPrefrio,
        ReglaUbicacionFolio $reglaUbicacionFolio,
        ReglaReservaCarga $reglaReservaCarga,
        ReglaReservaMaterial $reglaReservaMaterial,
        ReglaBalanceRepaletizaje $reglaBalanceRepaletizaje,
    ) {
        $this->reglas = [
            $reglaProyeccionPrefrio,
            $reglaUbicacionFolio,
            $reglaReservaCarga,
            $reglaReservaMaterial,
            $reglaBalanceRepaletizaje,
        ];
    }

    public function ejecutar(
        OrigenAuditoriaIntegridadOperacional $origen,
        ?User $actor = null,
    ): AuditoriaIntegridadOperacional {
        $bloqueo = Cache::lock(self::CLAVE_BLOQUEO, 900);

        if (! $bloqueo->get()) {
            throw new ConflictoOperacion(
                'Ya existe una auditoría de integridad operacional en ejecución.',
            );
        }

        $inicioMedicion = hrtime(true);
        $auditoria = AuditoriaIntegridadOperacional::create([
            'origen' => $origen,
            'estado' => EstadoAuditoriaIntegridadOperacional::EnEjecucion,
            'iniciada_por_user_id' => $actor?->id,
            'iniciada_at' => now(),
        ]);

        try {
            [$hallazgos, $metricasReglas] = $this->evaluarReglas();
            $this->persistirResultado(
                $auditoria,
                $hallazgos,
                $metricasReglas,
                $this->duracionMilisegundos($inicioMedicion),
            );

            return $auditoria->refresh();
        } catch (Throwable $error) {
            $auditoria->update([
                'estado' => EstadoAuditoriaIntegridadOperacional::Fallida,
                'finalizada_at' => now(),
                'duracion_ms' => $this->duracionMilisegundos($inicioMedicion),
                'error' => mb_substr($error->getMessage(), 0, 5000),
            ]);

            throw $error;
        } finally {
            $this->liberar($bloqueo);
        }
    }

    /**
     * @return array<int, array{codigo: string, nombre: string, modulo: string}>
     */
    public function catalogoReglas(): array
    {
        return array_map(
            fn (ReglaIntegridadOperacional $regla): array => [
                'codigo' => $regla->codigo(),
                'nombre' => $regla->nombre(),
                'modulo' => $regla->modulo(),
            ],
            $this->reglas,
        );
    }

    /**
     * @return array{
     *     0: Collection<string, HallazgoIntegridadDetectado>,
     *     1: array<int, array{codigo: string, nombre: string, modulo: string, duracion_ms: int, hallazgos: int}>
     * }
     */
    private function evaluarReglas(): array
    {
        /** @var Collection<string, HallazgoIntegridadDetectado> $hallazgos */
        $hallazgos = collect();
        $metricas = [];

        foreach ($this->reglas as $regla) {
            $inicio = hrtime(true);
            $detectados = collect($regla->evaluar());

            foreach ($detectados as $hallazgo) {
                $hallazgos->put($hallazgo->huella(), $hallazgo);
            }

            $metricas[] = [
                'codigo' => $regla->codigo(),
                'nombre' => $regla->nombre(),
                'modulo' => $regla->modulo(),
                'duracion_ms' => $this->duracionMilisegundos($inicio),
                'hallazgos' => $detectados->count(),
            ];
        }

        return [$hallazgos, $metricas];
    }

    /**
     * @param  Collection<string, HallazgoIntegridadDetectado>  $detectados
     * @param  array<int, array{codigo: string, nombre: string, modulo: string, duracion_ms: int, hallazgos: int}>  $metricasReglas
     */
    private function persistirResultado(
        AuditoriaIntegridadOperacional $auditoria,
        Collection $detectados,
        array $metricasReglas,
        int $duracionMs,
    ): void {
        DB::transaction(function () use (
            $auditoria,
            $detectados,
            $metricasReglas,
            $duracionMs,
        ): void {
            $ahora = now();
            $nuevos = 0;

            foreach ($detectados as $huella => $detectado) {
                $hallazgo = HallazgoIntegridadOperacional::query()
                    ->where('huella', $huella)
                    ->lockForUpdate()
                    ->first();
                $esNuevoOReabierto = ! $hallazgo || ! $hallazgo->activo;

                if (! $hallazgo) {
                    $hallazgo = new HallazgoIntegridadOperacional([
                        'huella' => $huella,
                        'primera_auditoria_id' => $auditoria->id,
                        'detectado_primera_vez_at' => $ahora,
                        'ocurrencias' => 0,
                    ]);
                }

                $hallazgo->fill([
                    'regla_codigo' => $detectado->reglaCodigo,
                    'severidad' => $detectado->severidad,
                    'modulo' => $detectado->modulo,
                    'entidad_tipo' => $detectado->entidadTipo,
                    'entidad_id' => $detectado->entidadId,
                    'referencia' => $detectado->referencia,
                    'titulo' => $detectado->titulo,
                    'detalle' => $detectado->detalle,
                    'contexto' => $detectado->contexto,
                    'activo' => true,
                    'ocurrencias' => $hallazgo->ocurrencias + 1,
                    'ultima_auditoria_id' => $auditoria->id,
                    'detectado_ultima_vez_at' => $ahora,
                    'resuelto_at' => null,
                ]);
                $hallazgo->save();

                if ($esNuevoOReabierto) {
                    $nuevos++;
                }
            }

            $porResolver = HallazgoIntegridadOperacional::query()
                ->where('activo', true);
            if ($detectados->isNotEmpty()) {
                $porResolver->whereNotIn('huella', $detectados->keys()->all());
            }
            $resueltos = $porResolver->count();
            $porResolver->update([
                'activo' => false,
                'resuelto_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            $conteos = HallazgoIntegridadOperacional::query()
                ->where('activo', true)
                ->selectRaw('severidad, COUNT(*) as total')
                ->groupBy('severidad')
                ->pluck('total', 'severidad');

            $auditoria->update([
                'estado' => EstadoAuditoriaIntegridadOperacional::Completada,
                'finalizada_at' => $ahora,
                'duracion_ms' => $duracionMs,
                'hallazgos_activos' => (int) $conteos->sum(),
                'hallazgos_criticos' => (int) ($conteos[SeveridadHallazgoIntegridadOperacional::Critico->value] ?? 0),
                'hallazgos_advertencia' => (int) ($conteos[SeveridadHallazgoIntegridadOperacional::Advertencia->value] ?? 0),
                'hallazgos_informativos' => (int) ($conteos[SeveridadHallazgoIntegridadOperacional::Informativo->value] ?? 0),
                'hallazgos_nuevos' => $nuevos,
                'hallazgos_resueltos' => $resueltos,
                'reglas_ejecutadas' => $metricasReglas,
                'error' => null,
            ]);
        }, attempts: 3);
    }

    private function duracionMilisegundos(int $inicio): int
    {
        return max(0, (int) round((hrtime(true) - $inicio) / 1_000_000));
    }

    private function liberar(Lock $bloqueo): void
    {
        try {
            $bloqueo->release();
        } catch (Throwable) {
            // La expiración del bloqueo no invalida una auditoría ya persistida.
        }
    }
}
