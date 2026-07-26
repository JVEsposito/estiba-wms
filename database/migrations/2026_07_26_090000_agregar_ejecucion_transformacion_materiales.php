<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas_transformacion_materiales', function (Blueprint $table) {
            $table->decimal('cantidad_consumida', 14, 3)
                ->default(0)
                ->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('reservas_transformacion_materiales', function (Blueprint $table) {
            $table->dropColumn('cantidad_consumida');
        });
    }
};
