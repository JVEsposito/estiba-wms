<?php

namespace App\Enums;

enum EstadoTransicionOperacional: string
{
    case Procesando = 'procesando';
    case Aplicada = 'aplicada';
    case Rechazada = 'rechazada';
    case Fallida = 'fallida';

    public function esFinal(): bool
    {
        return $this !== self::Procesando;
    }
}
