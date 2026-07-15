<?php

namespace App\Enums;

enum EstadoReservaMaterial: string
{
    case Activa = 'activa';
    case Consumida = 'consumida';
    case Liberada = 'liberada';
}
