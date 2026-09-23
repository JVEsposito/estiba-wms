<?php

namespace App\Observers;

use App\Enums\TipoPlanOperacional;
use App\Models\TareaMovimiento;
use App\Services\Planificador\ServicioRecalculosPendientesPlanificador;

class RecalcularPrioridadBufferRepaletizajeObserver
{
    public function __construct(
        private readonly ServicioRecalculosPendientesPlanificador $recalculos,
    ) {}

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

        $this->recalculos->solicitar(ServicioRecalculosPendientesPlanificador::BUFFER_REPA, $plan->temporada_id);
    }
}
