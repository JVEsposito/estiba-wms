<?php

namespace App\Observers;

use App\Models\Movimiento;
use App\Models\User;
use App\Services\Camaras\ServicioDesocupacionProgramada;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ReplanificarDesocupacionMovimientoObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ServicioDesocupacionProgramada $planificador,
    ) {}

    public function created(Movimiento $movimiento): void
    {
        $usuario = User::query()->find($movimiento->user_id);
        if ($usuario) {
            $this->planificador->sincronizarTrasMovimiento($movimiento, $usuario);
        }
    }
}
