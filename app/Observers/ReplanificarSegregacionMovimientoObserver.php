<?php

namespace App\Observers;

use App\Enums\EstadoTareaMovimiento;
use App\Models\Movimiento;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\RetencionOperacionalFolio;
use App\Models\User;
use App\Services\Retenciones\ServicioPlanSegregacionRetenidos;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ReplanificarSegregacionMovimientoObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ServicioPlanSegregacionRetenidos $planificador,
    ) {}

    public function created(Movimiento $movimiento): void
    {
        $usuario = User::query()->find($movimiento->user_id);
        if (! $usuario) {
            return;
        }

        $ids = RetencionOperacionalFolio::query()
            ->where('bloqueo_folio_id', $movimiento->folio_id)
            ->pluck('id');
        $plan = $movimiento->planOperacional()->first();
        if ($plan?->referencia_tipo === ServicioPlanSegregacionRetenidos::REFERENCIA
            && $plan->referencia_id) {
            $ids->push($plan->referencia_id);
        }

        $posicionIds = array_values(array_filter([
            $movimiento->posicion_origen_id,
            $movimiento->posicion_destino_id,
        ]));
        $posiciones = Posicion::query()->whereIn('id', $posicionIds)->get();
        foreach ($posiciones as $posicion) {
            $ids = $ids->merge(
                RetencionOperacionalFolio::query()
                    ->whereNotNull('bloqueo_folio_id')
                    ->whereHas('folio.ubicacionActual.posicion', fn ($consulta) => $consulta
                        ->where('camara_id', $posicion->camara_id)
                        ->where('banda', $posicion->banda)
                        ->where('nivel', $posicion->nivel))
                    ->pluck('id'),
            );
        }
        if ($posicionIds !== []) {
            $ids = $ids->merge(
                PlanOperacional::query()
                    ->where('referencia_tipo', ServicioPlanSegregacionRetenidos::REFERENCIA)
                    ->whereHas('tareas', fn ($consulta) => $consulta
                        ->whereIn('estado', [
                            EstadoTareaMovimiento::Bloqueada->value,
                            EstadoTareaMovimiento::Pendiente->value,
                            EstadoTareaMovimiento::Asumida->value,
                            EstadoTareaMovimiento::EnProceso->value,
                        ])
                        ->where(function ($extremos) use ($posicionIds): void {
                            $extremos->whereIn('posicion_origen_id', $posicionIds)
                                ->orWhereIn('posicion_destino_id', $posicionIds);
                        }))
                    ->pluck('referencia_id'),
            );
        }

        $retenciones = RetencionOperacionalFolio::query()
            ->whereIn('id', $ids->unique()->values())
            ->get();
        foreach ($retenciones as $retencion) {
            $this->planificador->sincronizar($retencion, $usuario);
        }
    }
}
