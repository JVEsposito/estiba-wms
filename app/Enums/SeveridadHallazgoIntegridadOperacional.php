<?php

namespace App\Enums;

enum SeveridadHallazgoIntegridadOperacional: string
{
    case Critico = 'critico';
    case Advertencia = 'advertencia';
    case Informativo = 'informativo';
}
