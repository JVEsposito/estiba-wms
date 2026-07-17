<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combinaciones_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->foreignUuid('articulo_validacion_id')->constrained('articulos_validacion')->restrictOnDelete();
            $table->foreignUuid('origen_validacion_id')->constrained('origenes_validacion')->restrictOnDelete();
            $table->string('codigo_externo', 100)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();

            $table->unique(
                ['temporada_id', 'articulo_validacion_id', 'origen_validacion_id'],
                'combinacion_validacion_unique',
            );
        });

        Schema::create('importaciones_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('nombre_archivo', 255);
            $table->string('tipo_archivo', 20);
            $table->char('checksum', 64)->index();
            $table->string('estado', 30)->default('borrador')->index();
            $table->json('resumen');
            $table->json('filas');
            $table->json('errores')->nullable();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('confirmado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones_validacion');
        Schema::dropIfExists('combinaciones_validacion');
    }
};
