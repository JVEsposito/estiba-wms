<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repaletizajes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->string('codigo', 24)->unique();
            $table->string('tipo_resultado', 20);
            $table->string('estrategia_folio', 20);
            $table->foreignUuid('folio_resultante_id')->constrained('folios');
            $table->foreignUuid('folio_conservado_id')->nullable()->constrained('folios');
            $table->unsignedInteger('cantidad_objetivo')->nullable();
            $table->unsignedInteger('cantidad_resultante');
            $table->string('condicion_termica', 40);
            $table->json('campos_mix');
            $table->json('snapshot');
            $table->string('estado', 20)->default('confirmado');
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos');
            $table->timestamp('confirmado_at');
            $table->uuid('operacion_anulacion_id')->nullable()->unique();
            $table->foreignId('anulado_por_user_id')->nullable()->constrained('users');
            $table->timestamp('anulado_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();

            $table->index(['estado', 'confirmado_at']);
            $table->index(['folio_resultante_id', 'estado']);
        });

        Schema::create('repaletizaje_detalles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repaletizaje_id')->constrained('repaletizajes')->cascadeOnDelete();
            $table->foreignUuid('folio_origen_id')->constrained('folios');
            $table->unsignedSmallInteger('orden');
            $table->boolean('es_folio_conservado')->default(false);
            $table->unsignedInteger('cajas_antes');
            $table->unsignedInteger('cajas_aportadas');
            $table->unsignedInteger('cajas_despues');
            $table->string('tipo_bulto_antes', 20);
            $table->string('tipo_bulto_despues', 20)->nullable();
            $table->string('estado_antes', 40);
            $table->string('estado_despues', 40);
            $table->json('snapshot_antes');
            $table->json('snapshot_despues');
            $table->timestamps();

            $table->unique(['repaletizaje_id', 'folio_origen_id']);
            $table->unique(['repaletizaje_id', 'orden']);
        });

        Schema::create('secuencias_repaletizajes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('anio')->primary();
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repaletizaje_detalles');
        Schema::dropIfExists('repaletizajes');
        Schema::dropIfExists('secuencias_repaletizajes');
    }
};
