<?php

namespace App\Enums;

enum TipoIncidenciaCarga: string
{
    case CajaAplastada = 'caja_aplastada';
    case ZunchoRoto = 'zuncho_roto';
    case PalletMojado = 'pallet_mojado';
    case PalletInestable = 'pallet_inestable';
    case FolioIlegible = 'folio_ilegible';
    case DiferenciaUbicacion = 'diferencia_ubicacion';
    case FolioNoEncontrado = 'folio_no_encontrado';
    case RetencionCalidad = 'retencion_calidad';
    case SectorInaccesible = 'sector_inaccesible';
    case Otro = 'otro';
}
