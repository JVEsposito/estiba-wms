<?php

namespace App\Enums;

enum EstadoRecepcionMaterial: string
{
    case Borrador = 'borrador';
    case Confirmada = 'confirmada';
    case Anulada = 'anulada';
}
