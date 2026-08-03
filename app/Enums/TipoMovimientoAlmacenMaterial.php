<?php

namespace App\Enums;

enum TipoMovimientoAlmacenMaterial: string
{
    case Entrega = 'entrega';
    case Transferencia = 'transferencia';
    case Devolucion = 'devolucion';
    case Consumo = 'consumo';
    case Ajuste = 'ajuste';
}
