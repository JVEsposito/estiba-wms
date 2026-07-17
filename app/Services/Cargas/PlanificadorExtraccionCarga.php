<?php

namespace App\Services\Cargas;

use App\Enums\EstadoCargaFolio;
use App\Models\Carga;
use App\Models\CargaFolio;
use App\Models\UbicacionActual;
use Illuminate\Support\Collection;

class PlanificadorExtraccionCarga
{
    /**
     * Ordena los folios desde la entrada hacia el fondo. P01 representa el
     * fondo de una banda, por lo que una posición mayor bloquea a una menor.
     * La simulación retira cada folio sugerido antes de calcular el siguiente.
     *
     * @return array<string, mixed>
     */
    public function planificar(Carga $carga): array
    {
        $asignaciones = CargaFolio::query()
            ->where('carga_id', $carga->id)
            ->whereIn('estado', [
                EstadoCargaFolio::Pendiente->value,
                EstadoCargaFolio::ConIncidencia->value,
            ])
            ->whereHas('reservaActiva')
            ->with('folio.ubicacionActual.posicion.camara:id,codigo,nombre,version_plano')
            ->orderBy('asignado_at')
            ->get();

        $camaras = $asignaciones
            ->map(fn (CargaFolio $asignacion): ?string => $asignacion
                ->folio
                ?->ubicacionActual
                ?->posicion
                ?->camara_id)
            ->filter()
            ->unique()
            ->values();

        $ocupaciones = UbicacionActual::query()
            ->whereHas(
                'posicion',
                fn ($consulta) => $consulta->whereIn('camara_id', $camaras),
            )
            ->with([
                'folio:id,numero_folio',
                'posicion.camara:id,codigo,nombre,version_plano',
            ])
            ->get()
            ->keyBy('folio_id');

        $pendientes = $asignaciones
            ->where('estado', EstadoCargaFolio::Pendiente)
            ->filter(fn (CargaFolio $asignacion): bool => $this->tieneUbicacion($asignacion))
            ->keyBy('id');
        $incidencias = $asignaciones
            ->where('estado', EstadoCargaFolio::ConIncidencia)
            ->values();
        $ruta = collect();
        $orden = 1;

        while ($pendientes->isNotEmpty()) {
            $accesibles = $pendientes
                ->filter(fn (CargaFolio $asignacion): bool => $this
                    ->bloqueadores($asignacion, $ocupaciones)
                    ->isEmpty())
                ->values();

            if ($accesibles->isEmpty()) {
                break;
            }

            /** @var CargaFolio $siguiente */
            $siguiente = $accesibles
                ->sort(fn (CargaFolio $una, CargaFolio $otra): int => $this->comparar($una, $otra))
                ->first();
            $ruta->push($this->transformar(
                $siguiente,
                $orden,
                $orden === 1 ? 'sugerido' : 'disponible',
                collect(),
            ));
            $ocupaciones->forget($siguiente->folio_id);
            $pendientes->forget($siguiente->id);
            $orden++;
        }

        $bloqueados = $pendientes
            ->values()
            ->sort(fn (CargaFolio $una, CargaFolio $otra): int => $this->comparar($una, $otra))
            ->map(fn (CargaFolio $asignacion): array => $this->transformar(
                $asignacion,
                null,
                'bloqueado',
                $this->bloqueadores($asignacion, $ocupaciones),
            ));
        $sinUbicacion = $asignaciones
            ->where('estado', EstadoCargaFolio::Pendiente)
            ->reject(fn (CargaFolio $asignacion): bool => $this->tieneUbicacion($asignacion))
            ->map(fn (CargaFolio $asignacion): array => $this->transformar(
                $asignacion,
                null,
                'sin_ubicacion',
                collect(),
            ));
        $conIncidencia = $incidencias->map(fn (CargaFolio $asignacion): array => $this->transformar(
            $asignacion,
            null,
            'incidencia',
            collect(),
        ));
        $items = $ruta
            ->concat($bloqueados)
            ->concat($sinUbicacion)
            ->concat($conIncidencia)
            ->values();

        return [
            'carga_id' => $carga->id,
            'carga_codigo' => $carga->codigo,
            'generado_at' => now()->toAtomString(),
            'resumen' => [
                'pendientes' => $asignaciones->where('estado', EstadoCargaFolio::Pendiente)->count(),
                'planificables' => $ruta->count(),
                'bloqueados' => $bloqueados->count(),
                'sin_ubicacion' => $sinUbicacion->count(),
                'con_incidencia' => $incidencias->count(),
            ],
            'siguiente' => $ruta->first(),
            'items' => $items,
        ];
    }

    private function tieneUbicacion(CargaFolio $asignacion): bool
    {
        return $asignacion->folio?->ubicacionActual?->posicion?->camara !== null;
    }

    /** @return Collection<int, UbicacionActual> */
    private function bloqueadores(CargaFolio $asignacion, Collection $ocupaciones): Collection
    {
        $posicion = $asignacion->folio->ubicacionActual->posicion;

        return $ocupaciones
            ->filter(function (UbicacionActual $ubicacion) use ($asignacion, $posicion): bool {
                $otra = $ubicacion->posicion;

                return $ubicacion->folio_id !== $asignacion->folio_id
                    && $otra->camara_id === $posicion->camara_id
                    && $otra->nivel === $posicion->nivel
                    && $otra->banda === $posicion->banda
                    && $otra->posicion > $posicion->posicion;
            })
            ->sortByDesc(fn (UbicacionActual $ubicacion): int => $ubicacion->posicion->posicion)
            ->values();
    }

    private function comparar(CargaFolio $una, CargaFolio $otra): int
    {
        $primera = $una->folio->ubicacionActual->posicion;
        $segunda = $otra->folio->ubicacionActual->posicion;

        return [
            $primera->camara->codigo,
            $primera->nivel,
            $primera->banda,
            -$primera->posicion,
            $una->folio->numero_folio,
        ] <=> [
            $segunda->camara->codigo,
            $segunda->nivel,
            $segunda->banda,
            -$segunda->posicion,
            $otra->folio->numero_folio,
        ];
    }

    /**
     * @param  Collection<int, UbicacionActual>  $bloqueadores
     * @return array<string, mixed>
     */
    private function transformar(
        CargaFolio $asignacion,
        ?int $orden,
        string $estadoRuta,
        Collection $bloqueadores,
    ): array {
        $folio = $asignacion->folio;
        $posicion = $folio?->ubicacionActual?->posicion;
        $camara = $posicion?->camara;

        return [
            'orden' => $orden,
            'estado_ruta' => $estadoRuta,
            'asignacion_id' => $asignacion->id,
            'folio' => [
                'id' => $folio?->id,
                'numero_folio' => $folio?->numero_folio,
                'tipo_bulto' => $folio?->tipo_bulto?->value,
            ],
            'ubicacion' => $posicion && $camara ? [
                'camara' => [
                    'id' => $camara->id,
                    'codigo' => $camara->codigo,
                    'nombre' => $camara->nombre,
                    'version_plano' => $camara->version_plano,
                ],
                'posicion' => [
                    'id' => $posicion->id,
                    'banda' => $posicion->banda,
                    'posicion' => $posicion->posicion,
                    'nivel' => $posicion->nivel,
                    'etiqueta' => $posicion->etiqueta,
                ],
            ] : null,
            'bloqueadores' => $bloqueadores->map(fn (UbicacionActual $ubicacion): array => [
                'folio_id' => $ubicacion->folio_id,
                'numero_folio' => $ubicacion->folio?->numero_folio,
                'posicion_id' => $ubicacion->posicion_id,
                'etiqueta' => $ubicacion->posicion?->etiqueta,
            ])->values(),
        ];
    }
}
