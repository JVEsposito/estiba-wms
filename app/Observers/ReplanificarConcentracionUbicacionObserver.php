<?php

namespace App\Observers;

use App\Models\UbicacionActual;
use App\Models\User;
use App\Services\Cargas\ServicioPlanConcentracionCarga;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ReplanificarConcentracionUbicacionObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ServicioPlanConcentracionCarga $planificador,
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

        $usuario = User::query()->find($movimiento->user_id);
        if (! $usuario) {
            return;
        }

        $this->planificador->sincronizarTrasMovimiento($movimiento, $usuario);
    }
}
