<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validaciones_pallet', function (Blueprint $table) {
            $table->unsignedTinyInteger('linea_proceso')->nullable()->after('cantidad_cajas');
            $table->char('turno', 1)->nullable()->after('linea_proceso');
            $table->index(
                ['temporada_id', 'generado_dispositivo_at', 'linea_proceso', 'turno'],
                'validacion_registro_jornada_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('validaciones_pallet', function (Blueprint $table) {
            $table->dropIndex('validacion_registro_jornada_idx');
            $table->dropColumn(['linea_proceso', 'turno']);
        });
    }
};
