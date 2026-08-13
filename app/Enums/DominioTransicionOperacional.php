<?php

namespace App\Enums;

enum DominioTransicionOperacional: string
{
    case Validacion = 'validacion';
    case Prefrio = 'prefrio';
    case Estiba = 'estiba';
    case Cargas = 'cargas';
    case Despacho = 'despacho';
    case Repaletizaje = 'repaletizaje';
    case Sag = 'sag';
    case Administracion = 'administracion';
    case Materiales = 'materiales';
    case Romana = 'romana';
}
