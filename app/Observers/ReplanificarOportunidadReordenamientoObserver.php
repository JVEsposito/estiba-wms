<?php

namespace App\Observers;

use App\Models\Movimiento;
use App\Services\Planificador\ServicioRecalculosPendientesPlanificador;

class ReplanificarOportunidadReordenamientoObserver
{
    public function __construct(
        private readonly ServicioRecalculosPendientesPlanificador $recalculos,
    ) {}

    public function created(Movimiento $movimiento): void
    {
        $this->recalculos->solicitar(ServicioRecalculosPendientesPlanificador::REORDENAMIENTO, $movimiento->id);
    }
}
