<?php

namespace App\Services\Cargas;

use App\Models\Carga;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RevisionCargaOperacional
{
    /** @param Collection<int, Carga> $cargas */
    public function calcular(Collection $cargas): string
    {
        $ids = $cargas->modelKeys();
        $asignaciones = $ids === []
            ? collect()
            : DB::table('carga_folios as asignacion')
                ->join(
                    'reservas_carga_folio as reserva',
                    'reserva.carga_folio_id',
                    '=',
                    'asignacion.id',
                )
                ->leftJoin(
                    'ubicaciones_actuales as ubicacion',
                    'ubicacion.folio_id',
                    '=',
                    'asignacion.folio_id',
                )
                ->leftJoin(
                    'posiciones as posicion',
                    'posicion.id',
                    '=',
                    'ubicacion.posicion_id',
                )
                ->leftJoin(
                    'camaras as camara',
                    'camara.id',
                    '=',
                    'posicion.camara_id',
                )
                ->whereIn('asignacion.carga_id', $ids)
                ->orderBy('asignacion.carga_id')
                ->orderBy('asignacion.id')
                ->get([
                    'asignacion.carga_id',
                    'asignacion.id',
                    'asignacion.folio_id',
                    'asignacion.estado',
                    'asignacion.anden_id',
                    'asignacion.updated_at',
                    'reserva.folio_id as folio_reservado_id',
                    'ubicacion.posicion_id',
                    'camara.id as camara_id',
                    'camara.version_plano',
                ]);

        $huella = json_encode([
            'cargas' => $cargas
                ->map(fn (Carga $carga): array => $carga->getAttributes())
                ->values()
                ->all(),
            // La versión del plano invalida también movimientos de folios que
            // bloquean la extracción, aunque no pertenezcan a la carga.
            'asignaciones' => $asignaciones->map(fn (object $fila): array => (array) $fila)->all(),
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $huella);
    }
}
