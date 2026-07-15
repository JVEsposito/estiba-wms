<?php

namespace App\Enums;

enum TipoMovimientoInventarioMaterial: string
{
    case Ingreso = 'ingreso';
    case Despacho = 'despacho';
    case Ajuste = 'ajuste';
    case Devolucion = 'devolucion';
}
