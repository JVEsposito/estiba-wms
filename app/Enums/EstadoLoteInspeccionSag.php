<?php

namespace App\Enums;

enum EstadoLoteInspeccionSag: string
{
    case Preparacion = 'preparacion';
    case EnInspeccion = 'en_inspeccion';
    case ResultadoParcial = 'resultado_parcial';
    case Finalizado = 'finalizado';
    case Cancelado = 'cancelado';

    public function esActivo(): bool
    {
        return ! in_array($this, [self::Finalizado, self::Cancelado], true);
    }
}
