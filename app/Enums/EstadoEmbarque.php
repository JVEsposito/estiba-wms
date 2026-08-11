<?php

namespace App\Enums;

enum EstadoEmbarque: string
{
    case Tentativo = 'tentativo';
    case Confirmado = 'confirmado';
    case Cancelado = 'cancelado';
}
