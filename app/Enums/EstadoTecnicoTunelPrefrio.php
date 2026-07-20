<?php

namespace App\Enums;

enum EstadoTecnicoTunelPrefrio: string
{
    case Operativo = 'operativo';
    case FueraDeServicio = 'fuera_de_servicio';
    case Mantenimiento = 'mantenimiento';
}
