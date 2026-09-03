<?php

namespace App\Enums;

enum EstadoPlanOperacional: string
{
    case Programado = 'programado';
    case EnEjecucion = 'en_ejecucion';
    case Pausado = 'pausado';
    case Completado = 'completado';
    case Cancelado = 'cancelado';

    public function esFinal(): bool
    {
        return in_array($this, [self::Completado, self::Cancelado], true);
    }
}
