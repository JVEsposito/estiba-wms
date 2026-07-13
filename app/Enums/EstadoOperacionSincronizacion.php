<?php

namespace App\Enums;

enum EstadoOperacionSincronizacion: string
{
    case Pendiente = 'pendiente';
    case Procesando = 'procesando';
    case Aceptada = 'aceptada';
    case Rechazada = 'rechazada';
    case Conflicto = 'conflicto';
}
