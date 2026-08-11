<?php

namespace App\Enums;

enum ResultadoInspeccionSag: string
{
    case Pendiente = 'pendiente';
    case SinResolucion = 'sin_resolucion';
    case Aprobado = 'aprobado';
    case Segregado = 'segregado';
    case Rechazado = 'rechazado';
}
