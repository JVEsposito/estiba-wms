<?php

namespace App\Services\Cargas;

use App\Enums\EstadoCargaFolio;
use App\Models\CargaFolio;
use Illuminate\Support\Collection;

class CalculadorConcentracionCarga
{
    public const UMBRAL_PORCENTAJE = 80;

    /**
     * Considera concentrados los folios que ya están en andén y el grupo físico
     * conectado más grande dentro de una misma cámara y nivel. Dos posiciones
     * están conectadas si son correlativas en una banda o pertenecen a bandas
     * consecutivas con la misma profundidad o una profundidad adyacente.
     *
     * @param  Collection<int, CargaFolio>  $asignaciones
     * @return array<string, mixed>
     */
    public function calcular(Collection $asignaciones): array
    {
        $total = $asignaciones->count();
        $enAnden = $asignaciones
            ->where('estado', EstadoCargaFolio::EnAnden)
            ->count();
        $conIncidencia = $asignaciones
            ->where('estado', EstadoCargaFolio::ConIncidencia)
            ->count();
        $ubicadas = $asignaciones
            ->filter(fn (CargaFolio $asignacion): bool => $asignacion
                ->folio
                ?->ubicacionActual
                ?->posicion !== null)
            ->values();

        $grupoPrincipal = $this->grupoPrincipal($ubicadas);
        $concentrados = min($total, $enAnden + $grupoPrincipal['cantidad']);
        $porcentaje = $total === 0
            ? 0
            : (int) round(($concentrados / $total) * 100);

        return [
            'porcentaje' => $porcentaje,
            'umbral_porcentaje' => self::UMBRAL_PORCENTAJE,
            'cumple_umbral' => $total > 0 && $porcentaje >= self::UMBRAL_PORCENTAJE,
            'concentrados' => $concentrados,
            'faltantes' => max(0, $total - $concentrados),
            'total' => $total,
            'en_anden' => $enAnden,
            'con_incidencia' => $conIncidencia,
            'pendientes' => max(0, $total - $enAnden - $conIncidencia),
            'grupo_principal' => $grupoPrincipal['ubicacion'],
        ];
    }

    /**
     * @param  Collection<int, CargaFolio>  $asignaciones
     * @return array{cantidad: int, ubicacion: array<string, mixed>|null}
     */
    private function grupoPrincipal(Collection $asignaciones): array
    {
        $mejor = ['cantidad' => 0, 'ubicacion' => null];

        $asignaciones
            ->groupBy(function (CargaFolio $asignacion): string {
                $posicion = $asignacion->folio->ubicacionActual->posicion;

                return "{$posicion->camara_id}:{$posicion->nivel}";
            })
            ->each(function (Collection $grupo) use (&$mejor): void {
                $componentes = $this->componentes($grupo);

                foreach ($componentes as $componente) {
                    if ($componente->count() <= $mejor['cantidad']) {
                        continue;
                    }

                    /** @var CargaFolio $primera */
                    $primera = $componente->first();
                    $posiciones = $componente
                        ->map(fn (CargaFolio $asignacion) => $asignacion
                            ->folio
                            ->ubicacionActual
                            ->posicion);
                    $posicion = $primera->folio->ubicacionActual->posicion;
                    $camara = $posicion->camara;

                    $mejor = [
                        'cantidad' => $componente->count(),
                        'ubicacion' => [
                            'camara' => [
                                'id' => $camara->id,
                                'codigo' => $camara->codigo,
                                'nombre' => $camara->nombre,
                            ],
                            'nivel' => (int) $posicion->nivel,
                            'banda_desde' => (int) $posiciones->min('banda'),
                            'banda_hasta' => (int) $posiciones->max('banda'),
                            'posicion_desde' => (int) $posiciones->min('posicion'),
                            'posicion_hasta' => (int) $posiciones->max('posicion'),
                        ],
                    ];
                }
            });

        return $mejor;
    }

    /**
     * @param  Collection<int, CargaFolio>  $grupo
     * @return array<int, Collection<int, CargaFolio>>
     */
    private function componentes(Collection $grupo): array
    {
        $pendientes = $grupo->keyBy('id');
        $componentes = [];

        while ($pendientes->isNotEmpty()) {
            /** @var CargaFolio $inicio */
            $inicio = $pendientes->first();
            $pendientes->forget($inicio->id);
            $cola = collect([$inicio]);
            $componente = collect();

            while ($cola->isNotEmpty()) {
                /** @var CargaFolio $actual */
                $actual = $cola->shift();
                $componente->push($actual);

                $vecinas = $pendientes
                    ->filter(fn (CargaFolio $candidata): bool => $this->sonVecinas($actual, $candidata))
                    ->values();

                foreach ($vecinas as $vecina) {
                    $pendientes->forget($vecina->id);
                    $cola->push($vecina);
                }
            }

            $componentes[] = $componente;
        }

        return $componentes;
    }

    private function sonVecinas(CargaFolio $una, CargaFolio $otra): bool
    {
        $primera = $una->folio->ubicacionActual->posicion;
        $segunda = $otra->folio->ubicacionActual->posicion;
        $diferenciaBanda = abs((int) $primera->banda - (int) $segunda->banda);
        $diferenciaPosicion = abs((int) $primera->posicion - (int) $segunda->posicion);

        return ($diferenciaBanda === 0 && $diferenciaPosicion === 1)
            || ($diferenciaBanda === 1 && $diferenciaPosicion <= 1);
    }
}
