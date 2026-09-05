<?php

namespace App\Observers;

use App\Models\Movimiento;
use App\Models\User;
use App\Services\Camaras\ServicioOportunidadReordenamiento;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ReplanificarOportunidadReordenamientoObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ServicioOportunidadReordenamiento $planificador,
    ) {}

    public function created(Movimiento $movimiento): void
    {
        $usuario = User::query()->find($movimiento->user_id);
        if (! $usuario) {
            return;
        }

        $this->planificador->sincronizarTrasMovimiento($movimiento, $usuario);
    }
}
