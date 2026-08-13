<?php

namespace App\Enums;

enum OrigenAuditoriaIntegridadOperacional: string
{
    case Manual = 'manual';
    case Programada = 'programada';
    case Consola = 'consola';
}
