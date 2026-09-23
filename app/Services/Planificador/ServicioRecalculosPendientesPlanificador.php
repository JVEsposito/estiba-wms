<?php

namespace App\Services\Planificador;

use App\Enums\EstadoTareaMovimiento;
use App\Jobs\EjecutarRecalculoPendientePlanificador;
use App\Models\Carga;
use App\Models\Movimiento;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\RetencionOperacionalFolio;
use App\Models\User;
use App\Services\Camaras\ServicioDesocupacionProgramada;
use App\Services\Camaras\ServicioOportunidadReordenamiento;
use App\Services\Cargas\ServicioPlanConcentracionCarga;
use App\Services\Retenciones\ServicioPlanSegregacionRetenidos;
use App\Services\Validacion\ServicioPrioridadBufferRepaletizaje;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ServicioRecalculosPendientesPlanificador
{
    public const CARGA = 'concentracion_carga';

    public const UBICACION = 'concentracion_movimiento';

    public const SEGREGACION = 'segregacion_movimiento';

    public const REORDENAMIENTO = 'reordenamiento_movimiento';

    public const DESOCUPACION = 'desocupacion_movimiento';

    public const BUFFER_REPA = 'prioridad_buffer_repa';

    private const TABLA = 'recalculos_pendientes_planificador';

    public function solicitar(string $tipo, string $fuenteId): void
    {
        DB::transaction(function () use ($tipo, $fuenteId): void {
            $ahora = now();
            DB::table(self::TABLA)->insertOrIgnore([
                'tipo' => $tipo,
                'fuente_id' => $fuenteId,
                'version_solicitada' => 0,
                'version_calculada' => 0,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            DB::table(self::TABLA)
                ->where('tipo', $tipo)
                ->where('fuente_id', $fuenteId)
                ->increment('version_solicitada', 1, [
                    'solicitado_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

            // El registro pendiente se confirma con la mutación física. Un fallo
            // al publicar el job no cambia la respuesta al operador: el watchdog
            // vuelve a enviar los pendientes desde la tabla.
            DB::afterCommit(fn () => $this->despachar($tipo, $fuenteId));
        });
    }

    public function despachar(string $tipo, string $fuenteId): void
    {
        if (config('queue.default') === 'sync') {
            return;
        }

        try {
            EjecutarRecalculoPendientePlanificador::dispatch($tipo, $fuenteId);
        } catch (Throwable $error) {
            report($error);
        }
    }

    public function recuperar(int $limite = 200): int
    {
        if (config('queue.default') === 'sync') {
            return 0;
        }

        $pendientes = DB::table(self::TABLA)
            ->whereColumn('version_solicitada', '>', 'version_calculada')
            ->orderBy('ultimo_reenvio_at')
            ->orderBy('id')
            ->limit($limite)
            ->get(['id', 'tipo', 'fuente_id']);

        foreach ($pendientes as $pendiente) {
            $this->despachar($pendiente->tipo, $pendiente->fuente_id);
            DB::table(self::TABLA)->where('id', $pendiente->id)
                ->update(['ultimo_reenvio_at' => now()]);
        }

        return $pendientes->count();
    }

    /** @return array{pendientes: int, atrasados: int, fallidos: int, mas_antiguo_segundos: ?int} */
    public function salud(): array
    {
        $pendientes = DB::table(self::TABLA)
            ->whereColumn('version_solicitada', '>', 'version_calculada');
        $primero = (clone $pendientes)->min('solicitado_at');

        return [
            'pendientes' => (clone $pendientes)->count(),
            'atrasados' => (clone $pendientes)->where('solicitado_at', '<', now()->subMinutes(5))->count(),
            'fallidos' => (clone $pendientes)->whereNotNull('fallo_at')->count(),
            'mas_antiguo_segundos' => $primero ? (int) max(0, Carbon::parse($primero)->diffInSeconds(now())) : null,
        ];
    }

    public function ejecutar(string $tipo, string $fuenteId): void
    {
        $registro = DB::table(self::TABLA)
            ->where('tipo', $tipo)
            ->where('fuente_id', $fuenteId)
            ->first();
        if (! $registro || $registro->version_solicitada <= $registro->version_calculada) {
            return;
        }

        try {
            $this->recalcular($tipo, $fuenteId);
            DB::table(self::TABLA)->where('id', $registro->id)->update([
                'version_calculada' => $registro->version_solicitada,
                'calculado_at' => now(),
                'fallo_at' => null,
                'ultimo_error' => null,
                'updated_at' => now(),
            ]);
        } catch (Throwable $error) {
            DB::table(self::TABLA)->where('id', $registro->id)->update([
                'fallo_at' => now(),
                'ultimo_error' => mb_substr($error->getMessage(), 0, 500),
                'updated_at' => now(),
            ]);
            throw $error;
        }
    }

    private function recalcular(string $tipo, string $fuenteId): void
    {
        if ($tipo === self::BUFFER_REPA) {
            app(ServicioPrioridadBufferRepaletizaje::class)->recalcular($fuenteId);

            return;
        }

        if ($tipo === self::CARGA) {
            $carga = Carga::query()->findOrFail($fuenteId);
            $usuarioId = $carga->actualizada_por_user_id
                ?? $carga->publicada_por_user_id
                ?? $carga->creada_por_user_id;
            $usuario = $usuarioId ? User::query()->findOrFail($usuarioId) : null;
            if ($usuario) {
                app(ServicioPlanConcentracionCarga::class)->sincronizar($carga, $usuario);
            }

            return;
        }

        $movimiento = Movimiento::query()->findOrFail($fuenteId);
        $usuario = User::query()->findOrFail($movimiento->user_id);

        match ($tipo) {
            self::UBICACION => app(ServicioPlanConcentracionCarga::class)
                ->sincronizarTrasMovimiento($movimiento, $usuario),
            self::SEGREGACION => $this->sincronizarSegregacion($movimiento, $usuario),
            self::REORDENAMIENTO => app(ServicioOportunidadReordenamiento::class)
                ->sincronizarTrasMovimiento($movimiento, $usuario),
            self::DESOCUPACION => app(ServicioDesocupacionProgramada::class)
                ->sincronizarTrasMovimiento($movimiento, $usuario),
            default => throw new \LogicException('Tipo de recálculo del planificador desconocido.'),
        };
    }

    private function sincronizarSegregacion(Movimiento $movimiento, User $usuario): void
    {
        $ids = RetencionOperacionalFolio::query()
            ->where('bloqueo_folio_id', $movimiento->folio_id)
            ->pluck('id');
        $plan = $movimiento->planOperacional()->first();
        if ($plan?->referencia_tipo === ServicioPlanSegregacionRetenidos::REFERENCIA
            && $plan->referencia_id) {
            $ids->push($plan->referencia_id);
        }

        $posicionIds = array_values(array_filter([
            $movimiento->posicion_origen_id,
            $movimiento->posicion_destino_id,
        ]));
        $posiciones = Posicion::query()->whereIn('id', $posicionIds)->get();
        foreach ($posiciones as $posicion) {
            $ids = $ids->merge(
                RetencionOperacionalFolio::query()
                    ->whereNotNull('bloqueo_folio_id')
                    ->whereHas('folio.ubicacionActual.posicion', fn ($consulta) => $consulta
                        ->where('camara_id', $posicion->camara_id)
                        ->where('banda', $posicion->banda)
                        ->where('nivel', $posicion->nivel))
                    ->pluck('id'),
            );
        }
        if ($posicionIds !== []) {
            $ids = $ids->merge(
                PlanOperacional::query()
                    ->where('referencia_tipo', ServicioPlanSegregacionRetenidos::REFERENCIA)
                    ->whereHas('tareas', fn ($consulta) => $consulta
                        ->whereIn('estado', [
                            EstadoTareaMovimiento::Bloqueada->value,
                            EstadoTareaMovimiento::Pendiente->value,
                            EstadoTareaMovimiento::Asumida->value,
                            EstadoTareaMovimiento::EnProceso->value,
                        ])
                        ->where(function ($extremos) use ($posicionIds): void {
                            $extremos->whereIn('posicion_origen_id', $posicionIds)
                                ->orWhereIn('posicion_destino_id', $posicionIds);
                        }))
                    ->pluck('referencia_id'),
            );
        }

        $retenciones = RetencionOperacionalFolio::query()
            ->whereIn('id', $ids->unique()->values())
            ->get();
        foreach ($retenciones as $retencion) {
            app(ServicioPlanSegregacionRetenidos::class)->sincronizar($retencion, $usuario);
        }
    }
}
