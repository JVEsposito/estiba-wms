<?php

namespace App\Enums;

enum EstadoLoteTransformacionMaterial: string
{
    case Abierto = 'abierto';
    case Cerrado = 'cerrado';
    case Anulado = 'anulado';
}
