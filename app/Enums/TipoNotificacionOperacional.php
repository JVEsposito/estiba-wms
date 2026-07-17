<?php

namespace App\Enums;

enum TipoNotificacionOperacional: string
{
    case CargaPublicada = 'carga_publicada';
    case PrioridadCargaCambiada = 'prioridad_carga_cambiada';
    case IncidenciaCargaReportada = 'incidencia_carga_reportada';
    case IncidenciaCargaResuelta = 'incidencia_carga_resuelta';
}
