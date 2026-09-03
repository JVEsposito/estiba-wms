<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TareaMovimientoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => $this->whenLoaded('planOperacional', fn (): array => [
                'id' => $this->planOperacional->id,
                'tipo' => $this->planOperacional->tipo->value,
                'estado' => $this->planOperacional->estado->value,
                'prioridad' => $this->planOperacional->prioridad->value,
                'titulo' => $this->planOperacional->titulo,
                'version' => $this->planOperacional->version,
            ]),
            'secuencia' => $this->secuencia,
            'tipo_movimiento' => $this->tipo_movimiento->value,
            'estado' => $this->estado->value,
            'prioridad' => $this->prioridad->value,
            'folio' => $this->whenLoaded('folio', fn (): array => [
                'id' => $this->folio->id,
                'numero_folio' => $this->folio->numero_folio,
                'tipo_bulto' => $this->folio->tipo_bulto->value,
            ]),
            'origen' => $this->extremo('Origen'),
            'destino' => $this->extremo('Destino'),
            'responsable' => $this->whenLoaded('responsable', fn (): ?array => $this->responsable ? [
                'id' => $this->responsable->id,
                'nombre' => $this->responsable->name,
            ] : null),
            'dispositivo' => $this->whenLoaded('dispositivo', fn (): ?array => $this->dispositivo ? [
                'id' => $this->dispositivo->id,
                'codigo' => $this->dispositivo->codigo,
                'nombre' => $this->dispositivo->nombre,
            ] : null),
            'reserva' => $this->whenLoaded('reservaActiva', function (): ?array {
                $reserva = $this->reservaActiva;

                return $reserva ? [
                    'id' => $reserva->id,
                    'estado' => $reserva->estado->value,
                    'destino_reservado' => $reserva->bloqueo_posicion_id !== null,
                    'reservada_at' => $reserva->reservada_at?->toAtomString(),
                    'renovada_at' => $reserva->renovada_at?->toAtomString(),
                    'vence_at' => $reserva->vence_at?->toAtomString(),
                    'segundos_restantes' => max(0, (int) now()->diffInSeconds(
                        $reserva->vence_at,
                        false,
                    )),
                    'version' => $reserva->version,
                ] : null;
            }),
            'instruccion' => $this->instruccion,
            'contexto' => $this->contexto ?? [],
            'asumida_at' => $this->asumida_at?->toAtomString(),
            'iniciada_at' => $this->iniciada_at?->toAtomString(),
            'completada_at' => $this->completada_at?->toAtomString(),
            'cancelada_at' => $this->cancelada_at?->toAtomString(),
            'version' => $this->version,
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function extremo(string $nombre): ?array
    {
        $camara = $this->{"camara{$nombre}"};
        $posicion = $this->{"posicion{$nombre}"};

        if (! $camara) {
            return null;
        }

        return [
            'camara' => [
                'id' => $camara->id,
                'nombre' => $camara->nombre,
            ],
            'posicion' => $posicion ? [
                'id' => $posicion->id,
                'etiqueta' => $posicion->etiqueta,
                'banda' => $posicion->banda,
                'posicion' => $posicion->posicion,
                'nivel' => $posicion->nivel,
            ] : null,
        ];
    }
}
