<?php

namespace App\Enums;

enum CondicionTermicaFolio: string
{
    case PendientePrefrio = 'pendiente_prefrio';
    case EnProceso = 'en_proceso';
    case PrefrioAprobado = 'prefrio_aprobado';
    case RequiereReproceso = 'requiere_reproceso';
    case CondicionHeredada = 'condicion_heredada';
    case Retenido = 'retenido';
}
