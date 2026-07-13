<?php

namespace App\Enums;

enum EstadoSesionEstiba: string
{
    case Abierta = 'abierta';
    case Cerrada = 'cerrada';
    case CierreForzado = 'cierre_forzado';
}
