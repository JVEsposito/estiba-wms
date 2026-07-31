<?php

namespace App\Observers;

use App\Enums\TipoAlmacenMaterial;
use App\Models\Posicion;
use App\Models\SaldoMaterialAlmacen;
use DomainException;

class SaldoMaterialAlmacenObserver
{
    public function saving(SaldoMaterialAlmacen $saldo): void
    {
        $actual = round((float) $saldo->cantidad_actual, 3);
        $reservada = round((float) $saldo->cantidad_reservada, 3);

        if ($actual < -0.0001) {
            throw new DomainException('El saldo de almacén no puede ser negativo.');
        }

        if ($reservada < -0.0001 || $reservada > $actual + 0.0001) {
            throw new DomainException(
                'La reserva del almacén debe estar entre cero y su cantidad actual.',
            );
        }

        $almacen = $saldo->almacen()->first();

        if (! $almacen) {
            return;
        }

        if ($almacen->tipo === TipoAlmacenMaterial::Virtual) {
            if ($saldo->camara_id || $saldo->posicion_id) {
                throw new DomainException(
                    'Un almacén virtual no puede tener cámara ni posición física.',
                );
            }

            return;
        }

        if ($saldo->posicion_id) {
            $camaraId = Posicion::query()
                ->whereKey($saldo->posicion_id)
                ->value('camara_id');

            if (! $camaraId || $camaraId !== $saldo->camara_id) {
                throw new DomainException(
                    'La posición del saldo no pertenece a su cámara física.',
                );
            }
        }
    }
}
