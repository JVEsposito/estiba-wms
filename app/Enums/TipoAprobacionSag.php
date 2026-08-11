<?php

namespace App\Enums;

enum TipoAprobacionSag: string
{
    case AprobadoOrigen = 'AO';
    case AprobadoUsda = 'AU';
    case AprobadoFumigacion = 'AF';

    public function etiqueta(): string
    {
        return match ($this) {
            self::AprobadoOrigen => 'Aprobado Origen',
            self::AprobadoUsda => 'Aprobado USDA',
            self::AprobadoFumigacion => 'Aprobado Fumigación',
        };
    }
}
