<?php

namespace App\Exceptions;

use DomainException;

/**
 * Se intentó modificar un registro de una temporada que no está activa.
 *
 * Se responde con 409 y el código `temporada_no_activa`. Hereda de
 * DomainException para que los servicios que ya capturan reglas de negocio lo
 * sigan tratando como tal.
 */
class RegistroFueraDeTemporadaActiva extends DomainException
{
    public function __construct(
        string $mensaje,
        public readonly ?string $temporadaRegistro = null,
        public readonly ?string $temporadaActiva = null,
    ) {
        parent::__construct($mensaje);
    }
}
