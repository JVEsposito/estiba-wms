<?php

namespace App\Enums;

enum EstadoOperacionalFolio: string
{
    case Disponible = 'disponible';
    case Bloqueado = 'bloqueado';
    case Anulado = 'anulado';
    case RetiradoDefinitivo = 'retirado_definitivo';
    case Despachado = 'despachado';
}
