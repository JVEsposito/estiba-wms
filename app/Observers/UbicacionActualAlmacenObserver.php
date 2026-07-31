<?php

namespace App\Observers;

use App\Models\FolioMaterial;
use App\Models\UbicacionActual;
use App\Services\Materiales\ServicioAlmacenMaterial;

class UbicacionActualAlmacenObserver
{
    public function __construct(
        private readonly ServicioAlmacenMaterial $almacenes,
    ) {}

    public function saved(UbicacionActual $ubicacion): void
    {
        $this->sincronizar($ubicacion);
    }

    public function deleted(UbicacionActual $ubicacion): void
    {
        $this->sincronizar($ubicacion);
    }

    private function sincronizar(UbicacionActual $ubicacion): void
    {
        $folio = FolioMaterial::query()->find($ubicacion->folio_id);

        if ($folio) {
            $this->almacenes->sincronizarUbicacion($folio);
        }
    }
}
