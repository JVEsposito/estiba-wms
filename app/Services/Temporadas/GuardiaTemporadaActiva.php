<?php

namespace App\Services\Temporadas;

use App\Exceptions\RegistroFueraDeTemporadaActiva;
use App\Models\Contracts\PerteneceATemporada;
use App\Models\Temporada;

/**
 * Regla única: solo se modifica información de la temporada activa.
 *
 * Materiales queda fuera porque traspasa su inventario entre temporadas con
 * su propia migración auditada.
 */
class GuardiaTemporadaActiva
{
    public function __construct(private readonly ServicioTemporadaActiva $temporadas) {}

    public function asegurar(PerteneceATemporada $registro): void
    {
        $temporadaId = $registro->temporadaOperacionalId();

        if ($temporadaId === null) {
            return;
        }

        $activa = $this->temporadas->buscar();

        if ($activa !== null && $activa->id === $temporadaId) {
            return;
        }

        $codigoRegistro = Temporada::query()->whereKey($temporadaId)->value('codigo') ?? 'desconocida';

        throw new RegistroFueraDeTemporadaActiva(
            $activa === null
                ? "Este registro pertenece a la temporada {$codigoRegistro} y no existe una temporada activa. Un administrador debe activarla desde Accesos."
                : "Este registro pertenece a la temporada {$codigoRegistro}, que no está activa. Solo se puede modificar información de la temporada {$activa->codigo}.",
            $codigoRegistro,
            $activa?->codigo,
        );
    }
}
