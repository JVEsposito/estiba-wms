<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validaciones_mp', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('recepcion_romana_id')->unique()->constrained('recepciones_romana')->restrictOnDelete();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->uuid('operacion_toma_id')->unique();
            $table->uuid('operacion_confirmacion_id')->nullable()->unique();
            $table->string('estado', 20)->index();
            $table->foreignId('validador_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos')->restrictOnDelete();
            $table->boolean('tarjas_verificadas')->nullable();
            $table->boolean('requiere_segregacion')->default(false);
            $table->timestamp('tomada_at');
            $table->timestamp('validada_at')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });

        Schema::create('segmentos_validacion_mp', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('validacion_mp_id')->constrained('validaciones_mp')->restrictOnDelete();
            $table->unsignedSmallInteger('secuencia');
            $table->json('motivos');
            $table->foreignUuid('csg_validacion_id')->nullable()->constrained('csg_validacion')->restrictOnDelete();
            $table->string('csg_snapshot', 50)->nullable();
            $table->string('cuartel', 100)->nullable();
            $table->foreignUuid('variedad_validacion_id')->nullable()->constrained('variedades_validacion')->restrictOnDelete();
            $table->string('variedad_snapshot', 100)->nullable();
            $table->string('estado', 30)->default('pendiente_lote');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->unique(['validacion_mp_id', 'secuencia'], 'segmento_validacion_mp_secuencia_unique');
        });

        Schema::create('segmentos_envases_validacion_mp', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('segmento_validacion_mp_id');
            $table->foreign('segmento_validacion_mp_id', 'seg_env_validacion_segmento_fk')
                ->references('id')
                ->on('segmentos_validacion_mp')
                ->restrictOnDelete();
            $table->string('tipo_envase', 20);
            $table->unsignedInteger('cantidad');
            $table->timestamps();
            $table->unique(['segmento_validacion_mp_id', 'tipo_envase'], 'segmento_envase_mp_tipo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segmentos_envases_validacion_mp');
        Schema::dropIfExists('segmentos_validacion_mp');
        Schema::dropIfExists('validaciones_mp');
    }
};
