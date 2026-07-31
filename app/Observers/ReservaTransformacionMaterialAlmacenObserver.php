<?php

namespace App\Observers;

use App\Models\ReservaTransformacionMaterial;
use App\Services\Materiales\ContextoSaldoReservaMaterial;
use LogicException;

class ReservaTransformacionMaterialAlmacenObserver
{
    public function __construct(
        private readonly ContextoSaldoReservaMaterial $contexto,
    ) {}

    public function saving(ReservaTransformacionMaterial $reserva): void
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
                'La reserva de transformación intentó usar el saldo de otro folio.',
            );
        }

        $reserva->saldo_material_almacen_id = $saldo->id;
    }
}
