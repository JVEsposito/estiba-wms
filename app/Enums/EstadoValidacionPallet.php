<?php

namespace App\Enums;

enum EstadoValidacionPallet: string
{
    case Aceptada = 'aceptada';
    case Conflicto = 'conflicto';
}
