<?php

namespace App\Observers;

use App\Models\Carga;
use App\Models\User;
use App\Services\Cargas\ServicioPlanConcentracionCarga;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ReplanificarConcentracionCargaObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ServicioPlanConcentracionCarga $planificador,
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

        $usuarioId = $carga->actualizada_por_user_id
            ?? $carga->publicada_por_user_id
            ?? $carga->creada_por_user_id;
        $usuario = $usuarioId ? User::query()->find($usuarioId) : null;

        if (! $usuario) {
            return;
        }

        $this->planificador->sincronizar($carga, $usuario);
    }
}
