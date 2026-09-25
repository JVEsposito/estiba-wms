<?php

namespace App\Exceptions;

use DomainException;

/**
 * Se intentó activar otra temporada mientras la vigente, o una temporada
 * anterior con fruta en cámaras, conserva registros sin cerrar.
 *
 * Se responde con 409 y el código `temporada_con_pendientes`.
 */
class TemporadaConPendientesDeCierre extends DomainException
{
    /**
     * @param  list<array{temporada: array{id: string, codigo: string}, categoria: string, etiqueta: string, frase: string, cantidad: int}>  $pendientes
     */
    public function __construct(string $mensaje, public readonly array $pendientes)
    {
        parent::__construct($mensaje);
    }
}
