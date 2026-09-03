<?php

namespace App\Observers;

use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\TipoPlanOperacional;
use App\Models\TareaMovimiento;

class CerrarRecepcionTunelObserver
{
    public function updated(TareaMovimiento $tarea): void
    {
        if (! $tarea->wasChanged('estado')
            || $tarea->estado !== EstadoTareaMovimiento::Completada) {
            return;
        }

        $plan = $tarea->planOperacional()->lockForUpdate()->first();
        if (! $plan
            || $plan->tipo !== TipoPlanOperacional::RecepcionTunel
            || $plan->estado->esFinal()) {
            return;
        }

        $faltan = TareaMovimiento::query()
            ->where('plan_operacional_id', $plan->id)
            ->where('estado', '!=', EstadoTareaMovimiento::Completada->value)
            ->exists();

        if ($faltan) {
            return;
        }

        $plan->update([
            'estado' => EstadoPlanOperacional::Completado,
            'completado_por_user_id' => $tarea->responsable_user_id,
            'completado_at' => $tarea->completada_at ?? now(),
            'version' => $plan->version + 1,
        ]);
    }
}
