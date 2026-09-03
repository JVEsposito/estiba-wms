<?php

namespace App\Observers;

use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\TipoPlanOperacional;
use App\Models\PlanOperacional;
use App\Models\TareaMovimiento;

class CerrarRecepcionTunelObserver
{
    public function updated(TareaMovimiento $tarea): void
    {
        if (! $tarea->wasChanged('estado')
            || $tarea->estado !== EstadoTareaMovimiento::Completada) {
            return;
        }

        $planes = collect();
        $planPropio = $tarea->planOperacional()->first();
        if ($planPropio?->tipo === TipoPlanOperacional::RecepcionTunel) {
            $planes->push($planPropio);
        }

        $frontera = collect([$tarea->id]);
        $visitadas = [];
        for ($profundidad = 0; $profundidad < 12 && $frontera->isNotEmpty(); $profundidad++) {
            $ids = $frontera
                ->filter(fn (string $id): bool => ! in_array($id, $visitadas, true))
                ->values();
            if ($ids->isEmpty()) {
                break;
            }
            $visitadas = [...$visitadas, ...$ids->all()];

            $predecesoras = TareaMovimiento::query()
                ->whereIn('reemplazada_por_tarea_id', $ids)
                ->with('planOperacional')
                ->get();
            $predecesoras->each(function (TareaMovimiento $reemplazada) use ($planes): void {
                if ($reemplazada->planOperacional?->tipo === TipoPlanOperacional::RecepcionTunel) {
                    $planes->push($reemplazada->planOperacional);
                }
            });
            $frontera = $predecesoras->pluck('id');
        }

        $planes
            ->unique('id')
            ->each(fn (PlanOperacional $plan): mixed => $this->evaluarPlan($plan, $tarea));
    }

    private function evaluarPlan(PlanOperacional $plan, TareaMovimiento $disparadora): void
    {
        $plan = PlanOperacional::query()->lockForUpdate()->find($plan->id);
        if (! $plan
            || $plan->tipo !== TipoPlanOperacional::RecepcionTunel
            || $plan->estado->esFinal()) {
            return;
        }

        $tareas = TareaMovimiento::query()
            ->where('plan_operacional_id', $plan->id)
            ->orderBy('secuencia')
            ->lockForUpdate()
            ->get();

        if ($tareas->isEmpty()
            || $tareas->contains(fn (TareaMovimiento $tarea): bool => ! $this->estaResuelta($tarea))) {
            return;
        }

        $plan->update([
            'estado' => EstadoPlanOperacional::Completado,
            'completado_por_user_id' => $disparadora->responsable_user_id,
            'completado_at' => $disparadora->completada_at ?? now(),
            'version' => $plan->version + 1,
        ]);
    }

    private function estaResuelta(TareaMovimiento $tarea): bool
    {
        $actual = $tarea;
        $visitadas = [];

        for ($profundidad = 0; $profundidad < 12; $profundidad++) {
            if ($actual->estado === EstadoTareaMovimiento::Completada) {
                return true;
            }
            if ($actual->estado !== EstadoTareaMovimiento::Cancelada
                || ! $actual->reemplazada_por_tarea_id
                || in_array($actual->id, $visitadas, true)) {
                return false;
            }

            $visitadas[] = $actual->id;
            $actual = TareaMovimiento::query()->find($actual->reemplazada_por_tarea_id);
            if (! $actual) {
                return false;
            }
        }

        return false;
    }
}
