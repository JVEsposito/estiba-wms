<?php

namespace App\Enums;

enum EstadoManiobraOperacional: string
{
    case Pendiente = 'pendiente';
    case EnEjecucion = 'en_ejecucion';
    case PausadaDiscrepancia = 'pausada_discrepancia';
    case Completada = 'completada';
    case Cancelada = 'cancelada';

    public function esFinal(): bool
    {
        return in_array($this, [self::Completada, self::Cancelada], true);
    }
}
