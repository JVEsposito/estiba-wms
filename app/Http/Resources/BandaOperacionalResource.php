<?php

namespace App\Http\Resources;

use App\Enums\ModoBandaOperacional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BandaOperacionalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $fisica = (int) ($this->capacidad_fisica_calculada ?? 0);
        $efectiva = (int) ($this->capacidad_efectiva_calculada ?? 0);
        $ocupadas = (int) ($this->ocupadas_calculadas ?? 0);
        $disponibles = max(0, $efectiva - $ocupadas);
        $estado = $this->estadoCalculado($efectiva, $ocupadas);

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'usos_permitidos' => $this->usos_permitidos,
            'modo' => $this->modo->value,
            'motivo_estado' => $this->motivo_estado,
            'estado' => $estado->value,
            'acepta_nuevos_ingresos' => $this->modo === ModoBandaOperacional::Operativa
                && $disponibles > 0,
            'capacidad' => [
                'fisica' => $fisica,
                'efectiva' => $efectiva,
                'ocupadas' => $ocupadas,
                'disponibles' => $disponibles,
                'porcentaje' => $efectiva > 0
                    ? round(($ocupadas / $efectiva) * 100, 1)
                    : 0.0,
            ],
            'afinidad' => $this->afinidad_calculada,
            'version' => $this->version,
            'actualizado_por' => $this->whenLoaded('actualizadoPor', fn (): ?array => $this->actualizadoPor ? [
                'id' => $this->actualizadoPor->id,
                'nombre' => $this->actualizadoPor->name,
            ] : null),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
