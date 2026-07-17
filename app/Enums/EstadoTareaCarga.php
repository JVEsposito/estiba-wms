<?php

namespace App\Enums;

enum EstadoTareaCarga: string
{
    case Pendiente = 'pendiente';
    case EnProceso = 'en_proceso';
    case Completada = 'completada';
    case Cancelada = 'cancelada';
}
