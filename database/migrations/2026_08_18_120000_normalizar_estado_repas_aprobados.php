<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('folios')
            ->where('activo', true)
            ->whereIn('tipo_bulto', ['pallet', 'saldo'])
            ->where('origen_sistema', 'repaletizaje')
            ->where('estado_operacional', 'pendiente_prefrio')
            ->where('condicion_termica', 'prefrio_aprobado')
            ->where('habilitacion_almacenamiento', 'habilitado')
            ->update([
                'estado_operacional' => 'disponible',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // El estado anterior era una herencia inválida y no se restaura.
    }
};
