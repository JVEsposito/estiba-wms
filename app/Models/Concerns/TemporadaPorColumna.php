<?php

namespace App\Models\Concerns;

/** Implementación de PerteneceATemporada para modelos con columna `temporada_id`. */
trait TemporadaPorColumna
{
    public function temporadaOperacionalId(): ?string
    {
        $temporadaId = $this->getAttribute('temporada_id');

        return $temporadaId === null ? null : (string) $temporadaId;
    }
}
