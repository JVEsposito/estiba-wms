<?php

namespace App\Enums;

enum EstadoBandaOperacional: string
{
    case Libre = 'libre';
    case Parcial = 'parcial';
    case Completa = 'completa';
    case Bloqueada = 'bloqueada';
    case EnVaciado = 'en_vaciado';
}
