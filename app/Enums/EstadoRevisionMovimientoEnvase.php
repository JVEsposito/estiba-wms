<?php

namespace App\Enums;

enum EstadoRevisionMovimientoEnvase: string
{
    case Pendiente = 'pendiente';
    case Revisado = 'revisado';
    case Observado = 'observado';
}
