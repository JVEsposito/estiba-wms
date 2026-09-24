<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro de cada paquete de archivo generado para una temporada cerrada.
        Schema::create('archivos_temporada', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('estado', 20)->index();
            $table->string('disco', 60);
            $table->string('ruta', 255)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->json('manifiesto')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->foreignId('solicitado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('generado_at')->nullable();
            $table->timestamp('verificado_at')->nullable();
            $table->timestamps();
        });

        // Claves de las filas incluidas mientras se genera un archivo; se vacía al terminar.
        Schema::create('archivo_temporada_claves', function (Blueprint $table) {
            $table->uuid('archivo_id');
            $table->string('tabla', 64);
            $table->string('clave', 64);
            $table->string('tipo', 12);
            $table->primary(['archivo_id', 'tabla', 'clave']);
            $table->index(['archivo_id', 'tabla', 'tipo', 'clave'], 'archivo_claves_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivo_temporada_claves');
        Schema::dropIfExists('archivos_temporada');
    }
};
