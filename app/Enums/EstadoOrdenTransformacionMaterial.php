<?php

namespace App\Enums;

enum EstadoOrdenTransformacionMaterial: string
{
    case Borrador = 'borrador';
    case Planificada = 'planificada';
    case EnProceso = 'en_proceso';
    case PendienteCierre = 'pendiente_cierre';
    case Cerrada = 'cerrada';
    case Cancelada = 'cancelada';
}
