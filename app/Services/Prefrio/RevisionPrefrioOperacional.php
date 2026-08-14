<?php

namespace App\Services\Prefrio;

use App\Enums\EstadoProcesoPrefrio;
use App\Models\Folio;
use App\Models\ProcesoPrefrio;
use App\Models\TunelPrefrio;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RevisionPrefrioOperacional
{
    /** @param Collection<int, TunelPrefrio> $tuneles */
    public function tuneles(Collection $tuneles): string
    {
        $ids = $tuneles->modelKeys();
        $procesosActivos = $ids === []
            ? collect()
            : DB::table('procesos_prefrio')
                ->whereIn('tunel_prefrio_id', $ids)
                ->whereIn('estado', $this->estadosActivos())
                ->orderBy('tunel_prefrio_id')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get([
                    'id',
                    'tunel_prefrio_id',
                    'estado',
                    'version',
                    'iniciado_at',
                    'updated_at',
                ]);

        return $this->huella([
            'tuneles' => $tuneles
                ->map(fn (TunelPrefrio $tunel): array => $tunel->getAttributes())
                ->values()
                ->all(),
            'procesos_activos' => $procesosActivos
                ->map(fn (object $fila): array => (array) $fila)
                ->all(),
        ]);
    }

    /**
     * @param  Collection<int, ProcesoPrefrio>  $procesos
     * @param  array<string, int>  $paginacion
     */
    public function procesos(Collection $procesos, array $paginacion = []): string
    {
        $ids = $procesos->modelKeys();
        $tuneles = $procesos
            ->pluck('tunel_prefrio_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $configuracionTuneles = $tuneles === []
            ? collect()
            : DB::table('tuneles_prefrio')
                ->whereIn('id', $tuneles)
                ->orderBy('id')
                ->get(['id', 'version_configuracion', 'updated_at']);
        $folios = $ids === []
            ? collect()
            : DB::table('procesos_prefrio_folios as asignacion')
                ->join('folios as folio', 'folio.id', '=', 'asignacion.folio_id')
                ->join(
                    'posiciones_tunel_prefrio as posicion',
                    'posicion.id',
                    '=',
                    'asignacion.posicion_tunel_prefrio_id',
                )
                ->whereIn('asignacion.proceso_prefrio_id', $ids)
                ->orderBy('asignacion.proceso_prefrio_id')
                ->orderBy('asignacion.created_at')
                ->orderBy('asignacion.id')
                ->get([
                    'asignacion.id',
                    'asignacion.proceso_prefrio_id',
                    'asignacion.folio_id',
                    'asignacion.estado',
                    'asignacion.posicion_tunel_prefrio_id',
                    'asignacion.updated_at as asignacion_updated_at',
                    'folio.updated_at as folio_updated_at',
                    'posicion.updated_at as posicion_updated_at',
                ]);

        return $this->huella([
            'paginacion' => $paginacion,
            'procesos' => $procesos
                ->map(fn (ProcesoPrefrio $proceso): array => $proceso->getAttributes())
                ->values()
                ->all(),
            'tuneles' => $configuracionTuneles
                ->map(fn (object $fila): array => (array) $fila)
                ->all(),
            'folios' => $folios
                ->map(fn (object $fila): array => (array) $fila)
                ->all(),
        ]);
    }

    /** @param Collection<int, Folio> $folios */
    public function foliosElegibles(Collection $folios): string
    {
        return $this->huella($folios
            ->map(fn (Folio $folio): array => $folio->getAttributes())
            ->values()
            ->all());
    }

    /** @return array<int, string> */
    private function estadosActivos(): array
    {
        return collect(EstadoProcesoPrefrio::cases())
            ->filter->esActivo()
            ->map->value
            ->all();
    }

    private function huella(array $datos): string
    {
        return hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));
    }
}
