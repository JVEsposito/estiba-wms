<?php

namespace App\Enums;

enum RolUsuario: string
{
    case Administrador = 'administrador';
    case SupervisorFrio = 'supervisor_frio';
    case SupervisorMateriales = 'supervisor_materiales';
    case Despachador = 'despachador';
    case CamareroFrio = 'camarero_frio';
    case CamareroMateriales = 'camarero_materiales';
    case Validador = 'validador';
    case Consulta = 'consulta';
}
