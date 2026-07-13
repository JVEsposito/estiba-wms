<?php

namespace App\Enums;

enum RolUsuario: string
{
    case Administrador = 'administrador';
    case Supervisor = 'supervisor';
    case Operador = 'operador';
    case Consulta = 'consulta';
}
