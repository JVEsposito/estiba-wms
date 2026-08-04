<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procesos_prefrio_folios', function (Blueprint $table): void {
            $table->dropUnique('proceso_prefrio_posicion_unique');
            $table->index(
                ['proceso_prefrio_id', 'posicion_tunel_prefrio_id'],
                'prefrio_proceso_posicion_idx',
            );
        });
    }

    public function down(): void
    {
        $existenPosicionesCompartidas = DB::table('procesos_prefrio_folios')
            ->select('proceso_prefrio_id', 'posicion_tunel_prefrio_id')
            ->groupBy('proceso_prefrio_id', 'posicion_tunel_prefrio_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($existenPosicionesCompartidas) {
            throw new RuntimeException(
                'No se puede restaurar la posición única mientras existan posiciones de Prefrío compartidas.',
            );
        }

        Schema::table('procesos_prefrio_folios', function (Blueprint $table): void {
            $table->dropIndex('prefrio_proceso_posicion_idx');
            $table->unique(
                ['proceso_prefrio_id', 'posicion_tunel_prefrio_id'],
                'proceso_prefrio_posicion_unique',
            );
        });
    }
};
