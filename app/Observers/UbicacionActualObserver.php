<?php

namespace App\Observers;

use App\Enums\EstadoOperacionalFolio;
use App\Enums\TipoBulto;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Services\Folios\ServicioHabilitacionAlmacenamiento;

class UbicacionActualObserver
{
    public function __construct(
        private readonly ServicioHabilitacionAlmacenamiento $habilitacion,
    ) {}

    public function creating(UbicacionActual $ubicacion): void
    {
        $folio = $ubicacion->folio()->firstOrFail();
        $this->habilitacion->validarIngresoCamara($folio);
    }

    public function created(UbicacionActual $ubicacion): void
    {
        $folio = $ubicacion->folio()->firstOrFail();

        if ($folio->tipo_bulto === TipoBulto::Material) {
            if ($folio->estado_operacional === EstadoOperacionalFolio::PendienteUbicacion) {
                $folio->update(['estado_operacional' => EstadoOperacionalFolio::Disponible]);
            }

            return;
        }

        /** @var User|null $usuario */
        $usuario = auth()->user();
        $folio = $this->habilitacion->prepararFolioManual($folio, $usuario);
        $folio->update(['estado_operacional' => EstadoOperacionalFolio::Disponible]);
    }
}
