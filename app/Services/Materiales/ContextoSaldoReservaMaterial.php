<?php

namespace App\Services\Materiales;

use App\Models\SaldoMaterialAlmacen;
use Closure;

class ContextoSaldoReservaMaterial
{
    private ?SaldoMaterialAlmacen $saldo = null;

    public function actual(): ?SaldoMaterialAlmacen
    {
        return $this->saldo;
    }

    public function ejecutar(SaldoMaterialAlmacen $saldo, Closure $operacion): mixed
    {
        $anterior = $this->saldo;
        $this->saldo = $saldo;

        try {
            return $operacion();
        } finally {
            $this->saldo = $anterior;
        }
    }
}
