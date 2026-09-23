<?php

namespace App\Observers;

use App\Models\Movimiento;
use App\Services\Planificador\ServicioRecalculosPendientesPlanificador;

class ReplanificarDesocupacionMovimientoObserver
{
    public function __construct(
        private readonly ServicioRecalculosPendientesPlanificador $recalculos,
    ) {}

    public function created(Movimiento $movimiento): void
    {
        $this->recalculos->solicitar(ServicioRecalculosPendientesPlanificador::DESOCUPACION, $movimiento->id);
    }
}
