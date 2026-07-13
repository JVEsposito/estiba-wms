<?php

namespace App\Enums;

enum EstadoPosicion: string
{
    case Activa = 'activa';
    case Bloqueada = 'bloqueada';
    case FueraDeServicio = 'fuera_servicio';
}
