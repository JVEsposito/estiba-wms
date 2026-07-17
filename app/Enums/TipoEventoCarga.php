<?php

namespace App\Enums;

enum TipoEventoCarga: string
{
    case Creada = 'creada';
    case Actualizada = 'actualizada';
    case FolioAsignado = 'folio_asignado';
    case FolioDesasignado = 'folio_desasignado';
    case Publicada = 'publicada';
    case TareasGeneradas = 'tareas_generadas';
    case FolioMovido = 'folio_movido';
    case IncidenciaReportada = 'incidencia_reportada';
    case IncidenciaResuelta = 'incidencia_resuelta';
    case FolioReemplazado = 'folio_reemplazado';
    case FolioEnviadoAnden = 'folio_enviado_anden';
    case CierreDespacho = 'cierre_despacho';
    case Cancelada = 'cancelada';
}
