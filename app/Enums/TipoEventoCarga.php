<?php

namespace App\Enums;

enum TipoEventoCarga: string
{
    case Creada = 'creada';
    case Actualizada = 'actualizada';
    case FolioAsignado = 'folio_asignado';
    case FolioDesasignado = 'folio_desasignado';
    case Publicada = 'publicada';
    case Cancelada = 'cancelada';
}
