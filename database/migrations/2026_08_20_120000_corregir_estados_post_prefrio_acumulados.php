<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $foliosAprobados = static fn (array $estados) => DB::table('folios')
            ->where('activo', true)
            ->whereIn('tipo_bulto', ['pallet', 'saldo'])
            ->whereIn('estado_operacional', $estados)
            ->where('condicion_termica', 'prefrio_aprobado')
            ->where('habilitacion_almacenamiento', 'habilitado');

        $ubicacionActual = static function ($consulta): void {
            $consulta
                ->selectRaw('1')
                ->from('ubicaciones_actuales')
                ->whereColumn('ubicaciones_actuales.folio_id', 'folios.id');
        };

        $foliosAprobados(['pendiente_prefrio', 'pendiente_ubicacion'])
            ->whereExists($ubicacionActual)
            ->update([
                'estado_operacional' => 'disponible',
                'updated_at' => now(),
            ]);

        $foliosAprobados(['pendiente_prefrio', 'disponible'])
            ->whereNotExists($ubicacionActual)
            ->update([
                'estado_operacional' => 'pendiente_ubicacion',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // La migración restablece la invariante entre aprobación, estado y ubicación.
    }
};
