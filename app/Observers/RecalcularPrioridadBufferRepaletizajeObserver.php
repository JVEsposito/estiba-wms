<?php

namespace App\Observers;

use App\Enums\TipoPlanOperacional;
use App\Models\TareaMovimiento;
use App\Services\Validacion\ServicioPrioridadBufferRepaletizaje;

class RecalcularPrioridadBufferRepaletizajeObserver
{
    public function updated(TareaMovimiento $tarea): void
    {
        if (! $tarea->wasChanged('estado')) {
            return;
        }

        $plan = $tarea->planOperacional()
            ->first(['id', 'temporada_id', 'tipo']);

        if (! $plan || $plan->tipo !== TipoPlanOperacional::RecepcionRepaletizaje) {
            return;
        }

        app(ServicioPrioridadBufferRepaletizaje::class)
            ->recalcular($plan->temporada_id);
    }
}
