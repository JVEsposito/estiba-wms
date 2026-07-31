<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versiones_recetas_materiales', function (Blueprint $table) {
            $table->decimal('unidades_por_folio_salida', 14, 3)
                ->nullable()
                ->after('cantidad_base_salida');
        });
    }

    public function down(): void
    {
        Schema::table('versiones_recetas_materiales', function (Blueprint $table) {
            $table->dropColumn('unidades_por_folio_salida');
        });
    }
};
