<?php

namespace App\Observers;

use App\Models\Carga;
use App\Services\Planificador\ServicioRecalculosPendientesPlanificador;

class ReplanificarConcentracionCargaObserver
{
    public function __construct(
        private readonly ServicioRecalculosPendientesPlanificador $recalculos,
    ) {}

    public function updated(Carga $carga): void
    {
        if (! $carga->wasChanged([
            'estado',
            'camara_objetivo_id',
            'version',
            'publicada_at',
            'cancelada_at',
            'cerrada_at',
        ])) {
            return;
        }

        $this->recalculos->solicitar(ServicioRecalculosPendientesPlanificador::CARGA, $carga->id);
    }
}
