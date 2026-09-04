<?php

namespace App\Enums;

enum TipoLoteInspeccionSag: string
{
    case MuestreoUsda = 'muestreo_usda';
    case InspeccionOrigen = 'inspeccion_origen';
    case InspeccionLinea = 'inspeccion_linea';
    case Fumigacion = 'fumigacion';
    case CambioMercado = 'cambio_mercado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MuestreoUsda => 'Muestreo USDA',
            self::InspeccionOrigen => 'Inspección Origen',
            self::InspeccionLinea => 'Inspección en línea',
            self::Fumigacion => 'Fumigación',
            self::CambioMercado => 'Cambio de mercado',
        };
    }

    public function aprobacionPredeterminada(): ?TipoAprobacionSag
    {
        return match ($this) {
            self::MuestreoUsda => TipoAprobacionSag::AprobadoUsda,
            self::InspeccionOrigen,
            self::InspeccionLinea => TipoAprobacionSag::AprobadoOrigen,
            self::Fumigacion => TipoAprobacionSag::AprobadoFumigacion,
            self::CambioMercado => null,
        };
    }

    public function apruebaAutomaticamente(): bool
    {
        return $this === self::InspeccionLinea;
    }

    public function requierePreparacionFisica(): bool
    {
        return in_array($this, [self::MuestreoUsda, self::InspeccionOrigen], true);
    }
}
