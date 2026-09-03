<?php

namespace App\Enums;

enum PrioridadOperacional: string
{
    case Normal = 'normal';
    case Alta = 'alta';
    case Urgente = 'urgente';
    case Critica = 'critica';

    public function peso(): int
    {
        return match ($this) {
            self::Normal => 10,
            self::Alta => 20,
            self::Urgente => 30,
            self::Critica => 40,
        };
    }
}
