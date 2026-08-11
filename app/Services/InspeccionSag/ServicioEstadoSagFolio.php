<?php

namespace App\Services\InspeccionSag;

use App\Enums\ResultadoInspeccionSag;
use App\Models\Folio;

class ServicioEstadoSagFolio
{
    /** @return array<string, mixed> */
    public function resumir(Folio $folio): array
    {
        $folio->loadMissing([
            'autorizacionesSagActivas',
            'inspeccionesSag.lote',
            'inspeccionesSag.resultados.destino',
        ]);

        $autorizaciones = $folio->autorizacionesSagActivas
            ->sortBy(fn ($autorizacion): string => implode('|', [
                $autorizacion->tipo_aprobacion->value,
                $autorizacion->destino_snapshot['codigo'] ?? '',
            ]));
        $codigos = $autorizaciones->pluck('tipo_aprobacion.value')->unique()->values();
        $destinos = $autorizaciones
            ->map(fn ($autorizacion): string => (string) ($autorizacion->destino_snapshot['nombre']
                ?? $autorizacion->destino_snapshot['codigo']
                ?? 'Destino'))
            ->unique()
            ->values();
        $enInspeccion = $folio->inspeccionesSag->contains(
            fn ($asignacion): bool => $asignacion->lote?->estado?->esActivo() === true,
        );

        $ultima = $folio->inspeccionesSag->sortByDesc('created_at')->first();
        $ultimoRechazado = $ultima !== null
            && $ultima->resultados->isNotEmpty()
            && $ultima->resultados->every(
                fn ($resultado): bool => $resultado->resultado === ResultadoInspeccionSag::Rechazado,
            );

        return [
            'estado' => $codigos->isNotEmpty()
                ? $codigos->implode(' · ')
                : ($ultimoRechazado ? 'Rechazado' : 'SI'),
            'codigos' => $codigos->all(),
            'destinos' => $destinos->all(),
            'en_inspeccion' => $enInspeccion,
            'etiqueta' => trim(($enInspeccion ? 'En inspección · ' : '').(
                $codigos->isNotEmpty() ? $codigos->implode(' · ') : ($ultimoRechazado ? 'Rechazado' : 'SI')
            )),
        ];
    }
}
