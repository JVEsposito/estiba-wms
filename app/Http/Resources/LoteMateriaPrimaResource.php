<?php

namespace App\Http\Resources;

use App\Models\EventoLoteMateriaPrima;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoteMateriaPrimaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_lote' => $this->numero_lote,
            'estado' => $this->estado->value,
            'version' => $this->version,
            'temporada' => $this->temporada ? [
                'id' => $this->temporada->id,
                'codigo' => $this->temporada->codigo,
                'nombre' => $this->temporada->nombre,
            ] : null,
            'cliente' => $this->cliente ? [
                'id' => $this->cliente->id,
                'codigo' => $this->cliente->codigo,
                'nombre' => $this->cliente->nombre,
            ] : null,
            'recepcion' => $this->recepcion ? [
                'id' => $this->recepcion->id,
                'numero_recepcion' => $this->recepcion->numero_recepcion,
                'numero_guia_despacho' => $this->recepcion->numero_guia_despacho,
                'peso_neto' => (float) $this->recepcion->peso_neto,
                'tipo_envase_calculo_neto' => $this->recepcion->tipo_envase_calculo_neto,
                'peso_neto_por_envase' => (float) $this->recepcion->peso_neto_por_envase,
            ] : null,
            'segmento' => $this->segmento ? [
                'id' => $this->segmento->id,
                'secuencia' => $this->segmento->secuencia,
                'estado' => $this->segmento->estado,
                'motivos' => $this->segmento->motivos,
                'envases' => $this->segmento->relationLoaded('envases')
                    ? $this->segmento->envases->map(fn ($envase): array => [
                        'tipo_envase' => $envase->tipo_envase->value,
                        'cantidad' => $envase->cantidad,
                    ])->values()
                    : [],
            ] : null,
            'trazabilidad' => [
                'csg_id' => $this->csg_validacion_id,
                'csg' => $this->csg_snapshot,
                'sdp' => $this->sdp,
                'ggn' => $this->ggn,
                'fecha_cosecha' => $this->fecha_cosecha?->toDateString(),
                'predio' => $this->predio,
                'especie_id' => $this->especie_validacion_id,
                'especie' => $this->especie_snapshot,
                'variedad_id' => $this->variedad_validacion_id,
                'variedad' => $this->variedad_snapshot,
                'calibre_id' => $this->calibre_validacion_id,
                'calibre' => $this->calibre_snapshot,
                'cuartel' => $this->cuartel,
                'tipo_producto' => $this->tipo_producto->value,
            ],
            'envases' => [
                'primario' => $this->envase_primario->value,
                'cantidad_primarios' => $this->cantidad_envases_primarios,
                'secundario' => $this->envase_secundario?->value,
                'cantidad_secundarios' => $this->cantidad_envases_secundarios,
            ],
            'pesos' => [
                'kilos_brutos' => (float) $this->kilos_brutos,
                'kilos_netos_calculados' => (float) $this->kilos_netos_calculados,
                'kilos_netos_confirmados' => (float) $this->kilos_netos_confirmados,
                'corregido_por_digitador' => abs(
                    (float) $this->kilos_netos_calculados
                    - (float) $this->kilos_netos_confirmados,
                ) > 0.0001,
            ],
            'requiere_hidrocooler' => $this->requiere_hidrocooler,
            'hidrocooler' => $this->whenLoaded('hidrocooler', fn () => $this->hidrocooler ? [
                'id' => $this->hidrocooler->id,
                'estado' => $this->hidrocooler->estado->value,
                'equipo' => $this->hidrocooler->equipo,
                'inicio_at' => $this->hidrocooler->inicio_at?->toAtomString(),
                'termino_at' => $this->hidrocooler->termino_at?->toAtomString(),
                'duracion_minutos' => $this->hidrocooler->duracion_minutos,
                'temperatura_c' => $this->hidrocooler->temperatura_c !== null
                    ? (float) $this->hidrocooler->temperatura_c
                    : null,
                'observacion' => $this->hidrocooler->observacion,
                'iniciado_por' => $this->hidrocooler->iniciadoPor?->name,
                'completado_por' => $this->hidrocooler->completadoPor?->name,
            ] : null),
            'asignacion_camara' => $this->whenLoaded(
                'asignacionCamara',
                fn () => $this->asignacionCamara ? [
                    'id' => $this->asignacionCamara->id,
                    'camara' => $this->asignacionCamara->camara ? [
                        'id' => $this->asignacionCamara->camara->id,
                        'codigo' => $this->asignacionCamara->camara->codigo,
                        'nombre' => $this->asignacionCamara->camara->nombre,
                    ] : null,
                    'asignado_por' => $this->asignacionCamara->asignadoPor?->name,
                    'asignado_at' => $this->asignacionCamara->asignado_at?->toAtomString(),
                    'observacion' => $this->asignacionCamara->observacion,
                ] : null,
            ),
            'observacion' => $this->observacion,
            'creado_por' => $this->creadoPor?->name,
            'actualizado_por' => $this->actualizadoPor?->name,
            'confirmado_por' => $this->confirmadoPor?->name,
            'confirmado_at' => $this->confirmado_at?->toAtomString(),
            'anulado_por' => $this->anuladoPor?->name,
            'anulado_at' => $this->anulado_at?->toAtomString(),
            'motivo_anulacion' => $this->motivo_anulacion,
            'eventos' => $this->whenLoaded('eventos', fn () => $this->eventos
                ->sortByDesc('ocurrido_at')
                ->map(fn (EventoLoteMateriaPrima $evento): array => [
                    'id' => $evento->id,
                    'tipo' => $evento->tipo,
                    'estado_anterior' => $evento->estado_anterior,
                    'estado_nuevo' => $evento->estado_nuevo,
                    'usuario' => $evento->usuario?->name,
                    'ocurrido_at' => $evento->ocurrido_at?->toAtomString(),
                    'datos' => $evento->datos,
                ])->values()),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
