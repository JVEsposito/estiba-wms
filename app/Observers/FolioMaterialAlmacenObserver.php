<?php

namespace App\Observers;

use App\Models\FolioMaterial;
use App\Services\Materiales\ServicioAlmacenMaterial;

class FolioMaterialAlmacenObserver
{
    public function __construct(
        private readonly ServicioAlmacenMaterial $almacenes,
    ) {}

    public function created(FolioMaterial $folio): void
    {
        $this->almacenes->inicializarFolio($folio);
    }

    public function updated(FolioMaterial $folio): void
    {
        if ($folio->wasChanged(['cantidad_actual', 'cantidad_reservada'])) {
            $this->almacenes->aplicarCambioLegado($folio);
        }
    }
}
