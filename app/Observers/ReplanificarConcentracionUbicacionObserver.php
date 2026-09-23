<?php

namespace App\Observers;

use App\Models\UbicacionActual;
use App\Services\Planificador\ServicioRecalculosPendientesPlanificador;

class ReplanificarConcentracionUbicacionObserver
{
    public function __construct(
        private readonly ServicioRecalculosPendientesPlanificador $recalculos,
    ) {}

    public function created(UbicacionActual $ubicacion): void
    {
        $this->sincronizar($ubicacion);
    }

    public function updated(UbicacionActual $ubicacion): void
    {
        if (! $ubicacion->wasChanged(['camara_id', 'posicion_id', 'movimiento_id'])) {
            return;
        }

        $this->sincronizar($ubicacion);
    }

    private function sincronizar(UbicacionActual $ubicacion): void
    {
        $movimiento = $ubicacion->movimiento()->first();
        if (! $movimiento) {
            return;
        }

        $this->recalculos->solicitar(ServicioRecalculosPendientesPlanificador::UBICACION, $movimiento->id);
    }
}
