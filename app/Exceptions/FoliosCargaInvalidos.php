<?php

namespace App\Exceptions;

use DomainException;

class FoliosCargaInvalidos extends DomainException
{
    /**
     * @param  array<int, array{folio: string, codigo: string, mensaje: string}>  $errores
     */
    public function __construct(public readonly array $errores)
    {
        parent::__construct('Uno o más folios no pueden asignarse a la carga.');
    }
}
