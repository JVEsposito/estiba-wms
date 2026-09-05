<?php

namespace App\Observers;

use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\TipoBulto;
use App\Enums\TipoPlanOperacional;
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
        $movimiento = $ubicacion->movimiento()
            ->with('tareaMovimiento.planOperacional')
            ->first();
        $tarea = $movimiento?->tareaMovimiento;
        $esFolioRetenido = $folio->habilitacion_almacenamiento
            === HabilitacionAlmacenamientoFolio::Retenido;
        $esSegregacionRetenido = $esFolioRetenido
            && $tarea?->planOperacional?->tipo
            === TipoPlanOperacional::SegregacionRetenido
            && ($tarea->contexto['folio_objetivo_retenido'] ?? false) === true;
        $esTareaPreviaEnCurso = $esFolioRetenido
            && $tarea?->estado === EstadoTareaMovimiento::EnProceso
            && $tarea->iniciada_at?->lte($folio->updated_at) === true;

        ($esSegregacionRetenido || $esTareaPreviaEnCurso)
            ? $this->habilitacion->validarIngresoCamaraRetenido($folio)
            : $this->habilitacion->validarIngresoCamara($folio);
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

        if ($folio->habilitacion_almacenamiento
            === HabilitacionAlmacenamientoFolio::Retenido) {
            return;
        }

        /** @var User|null $usuario */
        $usuario = auth()->user();
        $folio = $this->habilitacion->prepararFolioManual($folio, $usuario);
        $folio->update(['estado_operacional' => EstadoOperacionalFolio::Disponible]);
    }
}
