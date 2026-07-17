<?php

namespace App\Enums;

enum ResultadoValidacionPallet: string
{
    case Aprobado = 'aprobado';
    case Observado = 'observado';
    case Rechazado = 'rechazado';
}
