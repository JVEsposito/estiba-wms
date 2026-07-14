<?php

namespace App\Enums;

enum PrioridadCarga: string
{
    case Normal = 'normal';
    case Alta = 'alta';
    case Urgente = 'urgente';
}
