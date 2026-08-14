<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargas', function (Blueprint $table) {
            $table->string('modalidad_salida', 30)
                ->default('desde_camara')
                ->index()
                ->after('estado');
            $table->dateTime('cierre_registrado_at')
                ->nullable()
                ->after('cerrada_at');
        });
    }

    public function down(): void
    {
        Schema::table('cargas', function (Blueprint $table) {
            $table->dropIndex(['modalidad_salida']);
            $table->dropColumn([
                'modalidad_salida',
                'cierre_registrado_at',
            ]);
        });
    }
};
