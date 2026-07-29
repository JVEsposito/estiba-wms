<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recetas_materiales', function (Blueprint $table): void {
            $table->index(
                ['temporada_id', 'activa', 'created_at'],
                'recetas_materiales_temporada_activa_fecha_idx',
            );
        });

        Schema::table('ordenes_transformacion_materiales', function (Blueprint $table): void {
            $table->index(
                ['temporada_id', 'created_at'],
                'ordenes_transformacion_temporada_fecha_idx',
            );
            $table->index(
                ['temporada_id', 'estado', 'created_at'],
                'ordenes_transformacion_temporada_estado_fecha_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_transformacion_materiales', function (Blueprint $table): void {
            $table->dropIndex('ordenes_transformacion_temporada_fecha_idx');
            $table->dropIndex('ordenes_transformacion_temporada_estado_fecha_idx');
        });

        Schema::table('recetas_materiales', function (Blueprint $table): void {
            $table->dropIndex('recetas_materiales_temporada_activa_fecha_idx');
        });
    }
};
