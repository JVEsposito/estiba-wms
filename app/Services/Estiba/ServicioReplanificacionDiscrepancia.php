<?php

namespace App\Services\Estiba;

use App\Models\Camara;
use App\Models\Carga;
use App\Models\DiscrepanciaManiobra;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\RetencionOperacionalFolio;
use App\Models\TareaMovimiento;
use App\Models\User;
use App\Services\Camaras\InterbloqueoEvacuacionEmergencia;
use App\Services\Camaras\ServicioDesocupacionProgramada;
use App\Services\Camaras\ServicioOportunidadReordenamiento;
use App\Services\Cargas\ServicioPlanConcentracionCarga;
use App\Services\Retenciones\ServicioPlanSegregacionRetenidos;
use DomainException;

class ServicioReplanificacionDiscrepancia
{
    /**
     * Sólo los objetivos rolling que pueden reconstruirse desde la realidad
     * confirmada admiten descartar su sufijo reversible.
     */
    public function admite(PlanOperacional $plan): bool
    {
        return in_array($plan->referencia_tipo, [
            'carga_concentracion',
            ServicioPlanSegregacionRetenidos::REFERENCIA,
            ServicioOportunidadReordenamiento::REFERENCIA,
            ServicioDesocupacionProgramada::REFERENCIA,
            InterbloqueoEvacuacionEmergencia::REFERENCIA,
        ], true) && filled($plan->referencia_id);
    }

    public function replanificar(
        PlanOperacional $plan,
        User $supervisor,
        DiscrepanciaManiobra $discrepancia,
    ): ?PlanOperacional {
        $plan = PlanOperacional::query()->findOrFail($plan->id);
        if (! $this->admite($plan)) {
            throw new DomainException(
                'El objetivo de esta maniobra no posee un planificador rolling para reconstruir su sufijo.',
            );
        }

        $solicitadaAt = now();
        $recalculado = match ($plan->referencia_tipo) {
            'carga_concentracion' => app(ServicioPlanConcentracionCarga::class)->sincronizar(
                Carga::query()->findOrFail($plan->referencia_id),
                $supervisor,
            ),
            ServicioPlanSegregacionRetenidos::REFERENCIA => app(ServicioPlanSegregacionRetenidos::class)->sincronizar(
                RetencionOperacionalFolio::query()->findOrFail($plan->referencia_id),
                $supervisor,
            ),
            ServicioOportunidadReordenamiento::REFERENCIA => $this->replanificarReordenamiento(
                $plan,
                $supervisor,
            ),
            ServicioDesocupacionProgramada::REFERENCIA => app(ServicioDesocupacionProgramada::class)->sincronizar(
                Camara::query()->findOrFail($plan->referencia_id),
                $supervisor,
            ),
            InterbloqueoEvacuacionEmergencia::REFERENCIA => app(ServicioDesocupacionProgramada::class)->sincronizarPlan(
                Camara::query()->findOrFail($plan->referencia_id),
                $plan,
                $supervisor,
            ),
            default => null,
        };

        $plan = PlanOperacional::query()->lockForUpdate()->findOrFail($plan->id);
        $plan->update([
            'contexto' => [
                ...($plan->contexto ?? []),
                'replanificacion_discrepancia' => [
                    'discrepancia_id' => $discrepancia->id,
                    'solicitada_por_user_id' => $supervisor->id,
                    'solicitada_at' => $solicitadaAt->toAtomString(),
                    'estado' => 'recalculada',
                    'completada_at' => now()->toAtomString(),
                ],
            ],
            'version' => $plan->version + 1,
        ]);

        return $recalculado?->refresh() ?? $plan->refresh();
    }

    private function replanificarReordenamiento(
        PlanOperacional $plan,
        User $supervisor,
    ): ?PlanOperacional {
        $camara = Camara::query()->findOrFail($plan->referencia_id);
        $posicionIds = TareaMovimiento::query()
            ->where('plan_operacional_id', $plan->id)
            ->get(['posicion_origen_id', 'posicion_destino_id'])
            ->flatMap(fn (TareaMovimiento $tarea): array => [
                $tarea->posicion_origen_id,
                $tarea->posicion_destino_id,
            ])
            ->filter()
            ->unique()
            ->values();
        $bandas = collect($plan->contexto['bandas_analizadas'] ?? [])
            ->merge(
                Posicion::query()
                    ->whereIn('id', $posicionIds)
                    ->where('camara_id', $camara->id)
                    ->pluck('banda'),
            )
            ->map(fn (mixed $banda): int => (int) $banda)
            ->filter(fn (int $banda): bool => $banda >= 1
                && $banda <= $camara->cantidad_bandas)
            ->unique()
            ->sort()
            ->values();
        if ($bandas->isEmpty()) {
            $bandas = collect(range(1, $camara->cantidad_bandas));
        }

        return app(ServicioOportunidadReordenamiento::class)->sincronizarCamara(
            $camara,
            $supervisor,
            $bandas->all(),
        );
    }
}
