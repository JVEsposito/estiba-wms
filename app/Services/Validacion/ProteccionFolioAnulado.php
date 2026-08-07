<?php

namespace App\Services\Validacion;

use App\Enums\EstadoOperacionalFolio;
use App\Models\Folio;
use DomainException;

class ProteccionFolioAnulado
{
    public function asegurarOperableId(?string $folioId): void
    {
        if (! $folioId) {
            return;
        }

        $folio = Folio::query()->find($folioId);

        if ($folio && $this->esAnuladoPorValidacion($folio)) {
            throw new DomainException(
                "El folio {$folio->numero_folio} fue anulado en Validación y no puede utilizarse en ninguna operación.",
            );
        }
    }

    public function asegurarMutable(Folio $folio): void
    {
        $estadoOriginal = (string) $folio->getRawOriginal('estado_operacional');
        $activoOriginal = (bool) $folio->getRawOriginal('activo');
        $datosOriginales = $this->decodificarDatosExternos(
            $folio->getRawOriginal('datos_externos'),
        );

        if ($estadoOriginal === EstadoOperacionalFolio::Anulado->value
            && ! $activoOriginal
            && filled($datosOriginales['anulacion_validacion_id'] ?? null)) {
            throw new DomainException(
                "El folio {$folio->numero_folio} fue anulado en Validación y es inmutable.",
            );
        }
    }

    public function esAnuladoPorValidacion(Folio $folio): bool
    {
        return $folio->estado_operacional === EstadoOperacionalFolio::Anulado
            && ! $folio->activo
            && filled($folio->datos_externos['anulacion_validacion_id'] ?? null);
    }

    /** @return array<string, mixed> */
    private function decodificarDatosExternos(mixed $valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }

        if (! is_string($valor) || trim($valor) === '') {
            return [];
        }

        $decodificado = json_decode($valor, true);

        return is_array($decodificado) ? $decodificado : [];
    }
}
