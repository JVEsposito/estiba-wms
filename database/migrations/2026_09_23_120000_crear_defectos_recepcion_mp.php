<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defectos_recepcion_mp', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->foreignUuid('recepcion_romana_id')->constrained('recepciones_romana')->restrictOnDelete();
            $table->foreignUuid('validacion_mp_id')->constrained('validaciones_mp')->restrictOnDelete();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('numero_recepcion_snapshot', 24);
            $table->string('numero_guia_snapshot', 80);
            $table->string('cliente_nombre_snapshot', 150);
            $table->string('categoria', 40);
            $table->string('tipo_envase', 20)->nullable();
            $table->unsignedInteger('cantidad_afectada')->nullable();
            $table->text('descripcion');
            $table->foreignId('registrado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos')->restrictOnDelete();
            $table->timestamp('registrado_at');
            $table->timestamps();
            $table->index(['temporada_id', 'registrado_at'], 'defectos_mp_temporada_fecha_idx');
            $table->index(['recepcion_romana_id', 'registrado_at'], 'defectos_mp_recepcion_fecha_idx');
        });

        Schema::create('evidencias_defecto_recepcion_mp', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('defecto_recepcion_mp_id')->constrained('defectos_recepcion_mp')->restrictOnDelete();
            $table->string('tipo', 12);
            $table->unsignedTinyInteger('posicion');
            $table->string('ruta', 255);
            $table->string('mime', 60);
            $table->unsignedInteger('tamano_bytes');
            $table->char('sha256', 64);
            $table->timestamps();
            $table->unique(['defecto_recepcion_mp_id', 'tipo', 'posicion'], 'evidencia_defecto_mp_orden_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencias_defecto_recepcion_mp');
        Schema::dropIfExists('defectos_recepcion_mp');
    }
};
