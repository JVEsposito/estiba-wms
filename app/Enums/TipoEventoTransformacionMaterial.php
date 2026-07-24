<?php

namespace App\Enums;

enum TipoEventoTransformacionMaterial: string
{
    case Creada = 'creada';
    case Planificada = 'planificada';
    case Iniciada = 'iniciada';
    case LoteAbierto = 'lote_abierto';
    case LoteCerrado = 'lote_cerrado';
    case Cerrada = 'cerrada';
    case Cancelada = 'cancelada';
}
