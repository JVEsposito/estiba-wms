<?php

namespace App\Enums;

enum EstadoGuiaDespachoEnvase: string
{
    case Borrador = 'borrador';
    case Confirmada = 'confirmada';
    case Cancelada = 'cancelada';
    case Anulada = 'anulada';
}
