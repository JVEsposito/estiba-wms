<?php

namespace App\Enums;

enum TipoMovimientoInventarioMaterial: string
{
    case Ingreso = 'ingreso';
    case IngresoRecepcion = 'ingreso_recepcion';
    case AnulacionRecepcion = 'anulacion_recepcion';
    case Despacho = 'despacho';
    case Ajuste = 'ajuste';
    case Devolucion = 'devolucion';
    case CorreccionItemSalida = 'correccion_item_salida';
    case CorreccionItemEntrada = 'correccion_item_entrada';
}
