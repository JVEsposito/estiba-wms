<?php

namespace App\Services\Camaras;

use App\Enums\EstadoPlanOperacional;
use App\Enums\PrioridadOperacional;
use App\Enums\TipoPlanOperacional;
use App\Exceptions\ConflictoOperacion;
use App\Models\PlanOperacional;

class InterbloqueoEvacuacionEmergencia
{
    public const REFERENCIA = 'camara_emergencia';

    public function validarNuevaLabor(
        PlanOperacional $plan,
        PrioridadOperacional $prioridad,
        ?string $camaraOrigenId,
        ?string $camaraDestinoId,
    ): void {
        if ($plan->tipo === TipoPlanOperacional::EvacuacionEmergencia) {
            return;
        }

        $camaras = array_values(array_unique(array_filter([
            $camaraOrigenId,
            $camaraDestinoId,
        ])));
        if ($camaras === []) {
            return;
        }

        $emergencias = PlanOperacional::query()
            ->where('tipo', TipoPlanOperacional::EvacuacionEmergencia->value)
            ->where('referencia_tipo', self::REFERENCIA)
            ->whereIn('referencia_id', $camaras)
            ->whereNotIn('estado', [
                EstadoPlanOperacional::Completado->value,
                EstadoPlanOperacional::Cancelado->value,
            ])
            ->lockForUpdate()
            ->get(['referencia_id', 'contexto'])
            ->filter(fn (PlanOperacional $emergencia): bool => ($emergencia->contexto['ingreso_bloqueado'] ?? false) === true)
            ->pluck('referencia_id')
            ->flip();

        if ($camaraDestinoId && $emergencias->has($camaraDestinoId)) {
            throw new ConflictoOperacion(
                'La cámara de destino se encuentra bloqueada por una emergencia activa.',
            );
        }

        $prioridadEfectiva = max($prioridad->peso(), $plan->prioridad->peso());
        if ($camaraOrigenId
            && $emergencias->has($camaraOrigenId)
            && $prioridadEfectiva < PrioridadOperacional::Critica->peso()) {
            throw new ConflictoOperacion(
                'La emergencia activa suspendió nuevas labores normales en la cámara de origen.',
            );
        }
    }
}
