<?php

namespace App\Enums;

enum EstadoAuditoriaIntegridadOperacional: string
{
    case EnEjecucion = 'en_ejecucion';
    case Completada = 'completada';
    case Fallida = 'fallida';
}
