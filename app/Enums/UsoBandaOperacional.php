<?php

namespace App\Enums;

enum UsoBandaOperacional: string
{
    case TransitoProductoTerminado = 'transito_pt';
    case Inspeccion = 'inspeccion';
    case Retenidos = 'retenidos';

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_map(
            fn (self $uso): string => $uso->value,
            self::cases(),
        );
    }
}
