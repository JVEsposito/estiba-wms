<?php

namespace App\Http\Resources;

use App\Enums\EstadoCarga;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosicionPlanoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ubicacion = $this->resource->relationLoaded('ubicacionActual')
            ? $this->ubicacionActual
            : null;
        $folio = $ubicacion?->folio;
        $asignacionCarga = $folio?->relationLoaded('asignacionCargaActual')
            ? $folio->asignacionCargaActual
            : null;
        $carga = $asignacionCarga?->relationLoaded('carga')
            ? $asignacionCarga->carga
            : null;

        if ($carga && ! in_array($carga->estado, EstadoCarga::visiblesEnOperacion(), true)) {
            $carga = null;
        }

        return [
            'id' => $this->id,
            'banda' => $this->banda,
            'posicion' => $this->posicion,
            'nivel' => $this->nivel,
            'etiqueta' => $this->etiqueta,
            'estado' => $this->estado->value,
            'ocupada' => $ubicacion !== null,
            'folio' => $folio ? [
                'id' => $folio->id,
                'numero_folio' => $folio->numero_folio,
                'tipo_bulto' => $folio->tipo_bulto->value,
                'estado_operacional' => $folio->estado_operacional->value,
                'condicion_sag' => $folio->condicionSag ? [
                    'id' => $folio->condicionSag->id,
                    'codigo' => $folio->condicionSag->codigo,
                    'nombre' => $folio->condicionSag->nombre,
                ] : null,
                'fecha_ingreso' => $folio->fecha_ingreso?->toAtomString(),
                'variedad' => $folio->variedad,
                'calibre' => $folio->calibre,
                'marca' => $folio->marca,
                'exportadora' => $folio->exportadora,
                'ubicado_at' => $ubicacion->ubicado_at?->toAtomString(),
                'carga_actual' => $carga ? [
                    'id' => $carga->id,
                    'codigo' => $carga->codigo,
                    'estado' => $carga->estado->value,
                    'prioridad' => $carga->prioridad->value,
                    'version' => $carga->version,
                ] : null,
            ] : null,
        ];
    }
}
