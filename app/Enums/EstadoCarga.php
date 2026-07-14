<?php

namespace App\Enums;

enum EstadoCarga: string
{
    case Borrador = 'borrador';
    case Pendiente = 'pendiente';
    case EnSeparacion = 'en_separacion';
    case Separada = 'separada';
    case SeparacionCompleta = 'separacion_completa';
    case Despachada = 'despachada';
    case Cancelada = 'cancelada';

    /**
     * @return array<int, self>
     */
    public static function visiblesEnOperacion(): array
    {
        return [
            self::Pendiente,
            self::EnSeparacion,
            self::Separada,
            self::SeparacionCompleta,
        ];
    }
}
