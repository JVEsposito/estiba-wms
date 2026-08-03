<?php

namespace App\Observers;

use App\Models\ReservaMaterial;
use App\Services\Materiales\ContextoSaldoReservaMaterial;
use LogicException;

class ReservaMaterialAlmacenObserver
{
    public function __construct(
        private readonly ContextoSaldoReservaMaterial $contexto,
    ) {}

    public function saving(ReservaMaterial $reserva): void
    {
        if ($reserva->saldo_material_almacen_id) {
            return;
        }

        $saldo = $this->contexto->actual();

        if (! $saldo) {
            return;
        }

        if ($saldo->folio_id !== $reserva->folio_id) {
            throw new LogicException(
                'La reserva intentó asociarse a un saldo perteneciente a otro folio.',
            );
        }

        $reserva->saldo_material_almacen_id = $saldo->id;
    }
}
