<?php

namespace App\Services\Validacion;

use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\PrioridadOperacional;
use App\Enums\TipoPlanOperacional;
use App\Models\PlanOperacional;
use App\Models\TareaMovimiento;
use Illuminate\Support\Facades\DB;

class ServicioPrioridadBufferRepaletizaje
{
    /**
     * Recalcula la prioridad compartida del trabajo que todavía ocupa REPA.
     *
     * @return array{pallets_pendientes: int, prioridad: PrioridadOperacional, umbral_alta: int, maximo: int}
     */
    public function recalcular(string $temporadaId): array
    {
        return DB::transaction(function () use ($temporadaId): array {
            $maximo = max(1, (int) config('planificador.repa_buffer_max_pallets', 10));
            $umbralAlta = min(
                $maximo,
                max(1, (int) config('planificador.repa_buffer_high_from_pallets', 8)),
            );

            $tareasBuffer = TareaMovimiento::query()
                ->whereHas('planOperacional', fn ($consulta) => $consulta
                    ->where('temporada_id', $temporadaId)
                    ->where('tipo', TipoPlanOperacional::RecepcionRepaletizaje->value)
                    ->whereNotIn('estado', [
                        EstadoPlanOperacional::Completado->value,
                        EstadoPlanOperacional::Cancelado->value,
                    ]))
                ->whereIn('estado', [
                    EstadoTareaMovimiento::Pendiente->value,
                    EstadoTareaMovimiento::Asumida->value,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $palletsPendientes = $tareasBuffer
                ->pluck('folio_id')
                ->filter()
                ->unique()
                ->count();
            $prioridad = match (true) {
                $palletsPendientes >= $maximo => PrioridadOperacional::Urgente,
                $palletsPendientes >= $umbralAlta => PrioridadOperacional::Alta,
                default => PrioridadOperacional::Normal,
            };

            $planes = PlanOperacional::query()
                ->whereIn('id', $tareasBuffer->pluck('plan_operacional_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($planes as $plan) {
                $contexto = [
                    ...($plan->contexto ?? []),
                    'buffer_repa_pallets_pendientes' => $palletsPendientes,
                    'buffer_repa_umbral_alta' => $umbralAlta,
                    'buffer_repa_maximo' => $maximo,
                    'buffer_repa_prioridad' => $prioridad->value,
                ];

                if ($plan->prioridad !== $prioridad || $plan->contexto !== $contexto) {
                    $plan->update([
                        'prioridad' => $prioridad,
                        'contexto' => $contexto,
                        'version' => $plan->version + 1,
                    ]);
                }
            }

            $tareasBuffer
                ->filter(fn (TareaMovimiento $tarea): bool => $tarea->estado === EstadoTareaMovimiento::Pendiente)
                ->each(function (TareaMovimiento $tarea) use ($prioridad): void {
                    if ($tarea->prioridad !== $prioridad) {
                        $tarea->update([
                            'prioridad' => $prioridad,
                            'version' => $tarea->version + 1,
                        ]);
                    }
                });

            return [
                'pallets_pendientes' => $palletsPendientes,
                'prioridad' => $prioridad,
                'umbral_alta' => $umbralAlta,
                'maximo' => $maximo,
            ];
        }, attempts: 3);
    }
}
