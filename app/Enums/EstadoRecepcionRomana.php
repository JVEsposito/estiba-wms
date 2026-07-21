<?php

namespace App\Enums;

enum EstadoRecepcionRomana: string
{
    case EnBasculaIngreso = 'en_bascula_ingreso';
    case EnBasculaSalida = 'en_bascula_salida';
    case Cerrado = 'cerrado';

    public function esEditable(): bool
    {
        return $this === self::EnBasculaIngreso;
    }
}
