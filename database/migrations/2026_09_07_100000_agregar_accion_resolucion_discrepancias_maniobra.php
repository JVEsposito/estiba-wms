<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discrepancias_maniobra', function (Blueprint $table): void {
            $table->string('accion_resolucion', 40)
                ->nullable()
                ->after('resuelta_at');
        });
    }

    public function down(): void
    {
        if (DB::table('discrepancias_maniobra')
            ->whereNotNull('accion_resolucion')
            ->exists()) {
            throw new RuntimeException(
                'No se puede eliminar la acción de resolución después de resolver discrepancias.',
            );
        }

        Schema::table('discrepancias_maniobra', function (Blueprint $table): void {
            $table->dropColumn('accion_resolucion');
        });
    }
};
