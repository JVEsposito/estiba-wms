<?php

namespace App\Services\IntegridadOperacional\Reglas;

use App\Services\IntegridadOperacional\HallazgoIntegridadDetectado;

interface ReglaIntegridadOperacional
{
    public function codigo(): string;

    public function nombre(): string;

    public function modulo(): string;

    /**
     * @return iterable<int, HallazgoIntegridadDetectado>
     */
    public function evaluar(): iterable;
}
