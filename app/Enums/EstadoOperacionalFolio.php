<?php

namespace App\Enums;

enum EstadoOperacionalFolio: string
{
    case PendientePrefrio = 'pendiente_prefrio';
    case PendienteUbicacion = 'pendiente_ubicacion';
    case Disponible = 'disponible';
    case Bloqueado = 'bloqueado';
    case Anulado = 'anulado';
    case RetiradoDefinitivo = 'retirado_definitivo';
    case Despachado = 'despachado';
}
