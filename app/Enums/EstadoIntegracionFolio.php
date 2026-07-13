<?php

namespace App\Enums;

enum EstadoIntegracionFolio: string
{
    case NoVinculado = 'no_vinculado';
    case Pendiente = 'pendiente';
    case Sincronizado = 'sincronizado';
    case Error = 'error';
}
