<?php

namespace App\Enums;

enum TipoLoteInspeccionSag: string
{
    case MuestreoUsda = 'muestreo_usda';
    case InspeccionOrigen = 'inspeccion_origen';
    case Fumigacion = 'fumigacion';
    case CambioMercado = 'cambio_mercado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MuestreoUsda => 'Muestreo USDA',
            self::InspeccionOrigen => 'Inspección Origen',
            self::Fumigacion => 'Fumigación',
            self::CambioMercado => 'Cambio de mercado',
        };
    }

    public function aprobacionPredeterminada(): ?TipoAprobacionSag
    {
        return match ($this) {
            self::MuestreoUsda => TipoAprobacionSag::AprobadoUsda,
            self::InspeccionOrigen => TipoAprobacionSag::AprobadoOrigen,
            self::Fumigacion => TipoAprobacionSag::AprobadoFumigacion,
            self::CambioMercado => null,
        };
    }
}
