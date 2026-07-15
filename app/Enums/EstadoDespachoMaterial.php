<?php

namespace App\Enums;

enum EstadoDespachoMaterial: string
{
    case Pendiente = 'pendiente';
    case Parcial = 'parcial';
    case Completado = 'completado';
    case Cancelado = 'cancelado';
}
