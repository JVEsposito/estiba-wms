<?php

namespace App\Http\Resources;

use App\Models\EntregaFrutaProceso;
use App\Services\MateriaPrima\ServicioFrutaProceso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FrutaProcesoLoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $entregas = $this->relationLoaded('entregasProceso')
            ? $this->entregasProceso->sortByDesc(fn (EntregaFrutaProceso $entrega): string => implode('|', [
                $entrega->entregado_at?->format('Y-m-d H:i:s.u') ?? '',
                $entrega->created_at?->format('Y-m-d H:i:s.u') ?? '',
                $entrega->id,
            ]))->values()
            : collect();
        $vigentes = $entregas->whereNull('anulado_at');
        $ultimaVigente = $vigentes->first();
        $entregados = (int) $vigentes->sum('cantidad_envases');
        $total = (int) $this->cantidad_envases_primarios;
        $disponibles = max(0, $total - $entregados);
        $servicio = app(ServicioFrutaProceso::class);

        return [
            'id' => $this->id,
            'numero_lote' => $this->numero_lote,
            'estado' => $this->estado->value,
            'version' => $this->version,
            'cliente' => $this->cliente ? [
                'id' => $this->cliente->id,
                'codigo' => $this->cliente->codigo,
                'nombre' => $this->cliente->nombre,
            ] : null,
            'recepcion' => $this->recepcion ? [
                'id' => $this->recepcion->id,
                'numero_recepcion' => $this->recepcion->numero_recepcion,
                'numero_guia_despacho' => $this->recepcion->numero_guia_despacho,
            ] : null,
            'producto' => [
                'especie' => $this->especie_snapshot,
                'variedad' => $this->variedad_snapshot,
                'calibre' => $this->calibre_snapshot,
                'csg' => $this->csg_snapshot,
                'predio' => $this->predio,
                'cuartel' => $this->cuartel,
                'tipo' => $this->tipo_producto->value,
            ],
            'camara' => $this->asignacionCamara?->camara ? [
                'id' => $this->asignacionCamara->camara->id,
                'codigo' => $this->asignacionCamara->camara->codigo,
                'nombre' => $this->asignacionCamara->camara->nombre,
            ] : null,
            'envase_primario' => $this->envase_primario->value,
            'progreso' => [
                'total' => $total,
                'entregados' => $entregados,
                'disponibles' => $disponibles,
                'porcentaje' => $total > 0 ? round(($entregados / $total) * 100, 1) : 0,
            ],
            'entregas' => $entregas->map(
                fn (EntregaFrutaProceso $entrega): array => [
                    'id' => $entrega->id,
                    'cantidad_envases' => $entrega->cantidad_envases,
                    'saldo_anterior' => $entrega->saldo_anterior,
                    'saldo_posterior' => $entrega->saldo_posterior,
                    'linea_proceso' => $entrega->linea_proceso,
                    'turno' => $entrega->turno,
                    'numero_orden' => $entrega->numero_orden,
                    'observacion' => $entrega->observacion,
                    'entregado_por' => $entrega->entregadoPor ? [
                        'id' => $entrega->entregadoPor->id,
                        'nombre' => $entrega->entregadoPor->name,
                    ] : null,
                    'dispositivo' => $entrega->dispositivo ? [
                        'id' => $entrega->dispositivo->id,
                        'codigo' => $entrega->dispositivo->codigo,
                        'nombre' => $entrega->dispositivo->nombre,
                    ] : null,
                    'entregado_at' => $entrega->entregado_at?->toAtomString(),
                    'anulado' => $entrega->anulado_at !== null,
                    'anulado_por' => $entrega->anuladoPor?->name,
                    'anulado_at' => $entrega->anulado_at?->toAtomString(),
                    'motivo_anulacion' => $entrega->motivo_anulacion,
                    'puede_anular' => $request->user()
                        ? $servicio->puedeAnular(
                            $entrega,
                            $this->resource,
                            $request->user(),
                            $ultimaVigente?->id,
                        )
                        : false,
                ],
            )->values(),
            'ultima_entrega_at' => $ultimaVigente?->entregado_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
