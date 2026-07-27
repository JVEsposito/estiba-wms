<?php

namespace App\Enums;

enum TipoProductoMateriaPrima: string
{
    case MateriaPrima = 'materia_prima';
    case Comercial = 'comercial';
    case Precalibre = 'precalibre';
    case Descarte = 'descarte';
}
