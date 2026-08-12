<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();
        $asignaciones = DB::table('procesos_prefrio_folios as asignacion')
            ->join('procesos_prefrio as proceso', 'proceso.id', '=', 'asignacion.proceso_prefrio_id')
            ->join('folios as folio', 'folio.id', '=', 'asignacion.folio_id')
            ->where('proceso.estado', 'aprobado')
            ->where('asignacion.estado', 'aprobado')
            ->where('folio.activo', true)
            ->where('folio.estado_operacional', 'pendiente_prefrio')
            ->where('folio.condicion_termica', 'pendiente_prefrio')
            ->where('folio.habilitacion_almacenamiento', 'no_habilitado')
            ->orderByDesc('proceso.finalizado_at')
            ->select([
                'folio.id as folio_id',
                'proceso.id as proceso_id',
            ])
            ->get()
            ->unique('folio_id');

        foreach ($asignaciones as $asignacion) {
            $actualizados = DB::table('folios')
                ->where('id', $asignacion->folio_id)
                ->where('activo', true)
                ->where('estado_operacional', 'pendiente_prefrio')
                ->where('condicion_termica', 'pendiente_prefrio')
                ->where('habilitacion_almacenamiento', 'no_habilitado')
                ->update([
                    'condicion_termica' => 'prefrio_aprobado',
                    'habilitacion_almacenamiento' => 'habilitado',
                    'fuente_habilitacion_almacenamiento' => 'prefrio_aprobado',
                    'habilitado_almacenamiento_at' => $ahora,
                    'habilitado_almacenamiento_por_user_id' => null,
                    'retencion_termica_motivo' => null,
                    'updated_at' => $ahora,
                ]);

            if ($actualizados !== 1) {
                continue;
            }

            DB::table('historial_habilitaciones_almacenamiento')->insert([
                'id' => (string) Str::uuid(),
                'folio_id' => $asignacion->folio_id,
                'estado_resultante' => 'habilitado',
                'condicion_termica' => 'prefrio_aprobado',
                'fuente' => 'prefrio_aprobado',
                'proceso_origen' => 'prefrio',
                'referencia_origen' => $asignacion->proceso_id,
                'user_id' => null,
                'dispositivo_id' => null,
                'ocurrido_at' => $ahora,
                'motivo' => 'Regularización automática de folio omitido en el registro de prefrío.',
                'observacion' => null,
                'created_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        // La regularización representa el resultado histórico real y no se revierte.
    }
};
