<?php

namespace App\Services\Cargas;

use App\Services\Revisiones\RevisionBandejasOperacionales;

class RevisionCargaOperacional
{
    public function __construct(
        private readonly RevisionBandejasOperacionales $revisiones,
    ) {}

    public function calcular(): string
    {
        return $this->revisiones->obtener(RevisionBandejasOperacionales::CARGAS);
    }
}
