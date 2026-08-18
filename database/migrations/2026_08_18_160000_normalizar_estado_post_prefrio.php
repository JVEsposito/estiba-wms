<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $foliosAprobados = static fn () => DB::table('folios')
            ->where('activo', true)
            ->whereIn('tipo_bulto', ['pallet', 'saldo'])
            ->where('estado_operacional', 'pendiente_prefrio')
            ->where('condicion_termica', 'prefrio_aprobado')
            ->where('habilitacion_almacenamiento', 'habilitado');

        $ubicacionActual = static function ($consulta): void {
            $consulta
                ->selectRaw('1')
                ->from('ubicaciones_actuales')
                ->whereColumn('ubicaciones_actuales.folio_id', 'folios.id');
        };

        $foliosAprobados()
            ->whereExists($ubicacionActual)
            ->update([
                'estado_operacional' => 'disponible',
                'updated_at' => now(),
            ]);

        $foliosAprobados()
            ->whereNotExists($ubicacionActual)
            ->update([
                'estado_operacional' => 'pendiente_ubicacion',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // El estado anterior era contradictorio con un Prefrío ya aprobado.
    }
};
