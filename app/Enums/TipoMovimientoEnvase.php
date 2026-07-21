<?php

namespace App\Enums;

enum TipoMovimientoEnvase: string
{
    case RecepcionFruta = 'recepcion_fruta';
    case RecepcionArriendo = 'recepcion_arriendo';
    case RecepcionCompra = 'recepcion_compra';
    case DespachoCliente = 'despacho_cliente';
    case ReversionDespacho = 'reversion_despacho';
}
