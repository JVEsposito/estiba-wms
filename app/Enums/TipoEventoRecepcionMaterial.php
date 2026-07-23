<?php

namespace App\Enums;

enum TipoEventoRecepcionMaterial: string
{
    case Creada = 'creada';
    case Actualizada = 'actualizada';
    case Confirmada = 'confirmada';
    case Anulada = 'anulada';
}
