<?php

namespace App\Models\Contracts;

use App\Http\Middleware\AsegurarTemporadaActivaDelRegistro;
use App\Services\Temporadas\GuardiaTemporadaActiva;

/**
 * Registro operacional que pertenece a una temporada global.
 *
 * Todo cambio sobre un registro que implemente este contrato debe ocurrir en la
 * temporada activa. El control lo aplica, de forma central, el middleware
 * {@see AsegurarTemporadaActivaDelRegistro} sobre las rutas
 * que reciben el registro por ID; los servicios que reciben el registro por
 * otro camino llaman a {@see GuardiaTemporadaActiva}.
 */
interface PerteneceATemporada
{
    /**
     * Temporada del registro. `null` deja el registro fuera del control, por
     * ejemplo un folio de Materiales, que tiene su propio régimen de temporadas.
     */
    public function temporadaOperacionalId(): ?string;
}
