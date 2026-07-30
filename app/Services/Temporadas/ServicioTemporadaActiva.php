<?php

namespace App\Services\Temporadas;

use App\Models\Temporada;
use DomainException;

class ServicioTemporadaActiva
{
    public function buscar(bool $bloquear = false): ?Temporada
    {
        $consulta = Temporada::query()
            ->where('activa', true)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->first();
    }

    public function obtener(bool $bloquear = false): Temporada
    {
        return $this->buscar($bloquear)
            ?? throw new DomainException('No existe una temporada global activa. Un administrador debe activarla desde Accesos.');
    }
}
