<?php

namespace App\Enums;

enum EstadoReservaTareaMovimiento: string
{
    case Activa = 'activa';
    case Liberada = 'liberada';
    case Expirada = 'expirada';
    case Completada = 'completada';

    public function esFinal(): bool
    {
        return $this !== self::Activa;
    }
}
