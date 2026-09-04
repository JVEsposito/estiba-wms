<?php

namespace App\Enums;

enum TipoPasoManiobra: string
{
    case MovimientoPermanente = 'movimiento_permanente';
    case ExtraccionTemporal = 'extraccion_temporal';
    case RetornoBanda = 'retorno_banda';
    case EntregaAnden = 'entrega_anden';
}
