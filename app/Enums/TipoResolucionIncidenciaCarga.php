<?php

namespace App\Enums;

enum TipoResolucionIncidenciaCarga: string
{
    case DespachoParcial = 'despacho_parcial';
    case Reemplazo = 'reemplazo';
    case Reparado = 'reparado';
}
