<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ubicaciones_actuales', function (Blueprint $table) {
            $table->index('posicion_id', 'ubicaciones_actuales_posicion_id_index');
            $table->dropUnique('ubicaciones_actuales_posicion_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ubicaciones_actuales', function (Blueprint $table) {
            $table->unique('posicion_id', 'ubicaciones_actuales_posicion_id_unique');
            $table->dropIndex('ubicaciones_actuales_posicion_id_index');
        });
    }
};
