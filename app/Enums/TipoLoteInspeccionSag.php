<?php

namespace App\Enums;

enum TipoLoteInspeccionSag: string
{
    case Segregacion = 'segregacion';
    case CambioMercado = 'cambio_mercado';
}
