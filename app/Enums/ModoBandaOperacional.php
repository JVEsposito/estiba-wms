<?php

namespace App\Enums;

enum ModoBandaOperacional: string
{
    case Operativa = 'operativa';
    case Bloqueada = 'bloqueada';
    case EnVaciado = 'en_vaciado';
}
