<?php

namespace App\Enums;

enum TipoCambioTransicionOperacional: string
{
    case Creacion = 'creacion';
    case Actualizacion = 'actualizacion';
    case Eliminacion = 'eliminacion';
}
