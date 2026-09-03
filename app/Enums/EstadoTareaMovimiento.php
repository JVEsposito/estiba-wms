<?php

namespace App\Enums;

enum EstadoTareaMovimiento: string
{
    case Pendiente = 'pendiente';
    case Asumida = 'asumida';
    case EnProceso = 'en_proceso';
    case Completada = 'completada';
    case Cancelada = 'cancelada';

    public function esFinal(): bool
    {
        return in_array($this, [self::Completada, self::Cancelada], true);
    }
}
