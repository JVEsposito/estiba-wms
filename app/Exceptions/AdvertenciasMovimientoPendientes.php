<?php

namespace App\Exceptions;

use RuntimeException;

class AdvertenciasMovimientoPendientes extends RuntimeException
{
    /**
     * @param  array<int, array<string, mixed>>  $advertencias
     */
    public function __construct(public readonly array $advertencias)
    {
        parent::__construct('La operación requiere confirmación antes de continuar.');
    }
}
