<?php

namespace App\Services\Temporadas;

use App\Models\Temporada;
use DomainException;

class ServicioTemporadaActiva
{
    public function obtener(bool $bloquear = false): Temporada
    {
        $consulta = Temporada::query()->where('activa', true);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->first()
            ?? throw new DomainException('No existe una temporada global activa. Un administrador debe activarla desde Accesos.');
    }
}
