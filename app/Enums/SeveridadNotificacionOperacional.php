<?php

namespace App\Enums;

enum SeveridadNotificacionOperacional: string
{
    case Informativa = 'informativa';
    case Advertencia = 'advertencia';
    case Critica = 'critica';
    case Exito = 'exito';
}
