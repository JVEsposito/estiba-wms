<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_operacionales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('tipo', 50);
            $table->string('estado', 30)->default('programado');
            $table->string('prioridad', 20)->default('normal');
            $table->string('titulo', 180);
            $table->text('motivo')->nullable();
            $table->string('referencia_tipo', 80)->nullable();
            $table->uuid('referencia_id')->nullable();
            $table->json('contexto')->nullable();
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('iniciado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('completado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('programado_at')->index();
            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('pausado_at')->nullable();
            $table->timestamp('completado_at')->nullable();
            $table->timestamp('cancelado_at')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['temporada_id', 'estado', 'prioridad'], 'planes_temporada_estado_prioridad_idx');
            $table->index(['referencia_tipo', 'referencia_id'], 'planes_referencia_idx');
        });

        Schema::create('tareas_movimiento', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_operacional_id')->constrained('planes_operacionales')->restrictOnDelete();
            $table->unsignedInteger('secuencia');
            $table->string('tipo_movimiento', 40);
            $table->string('estado', 30)->default('pendiente');
            $table->string('prioridad', 20)->default('normal');
            $table->foreignUuid('folio_id')->constrained('folios')->restrictOnDelete();
            $table->foreignUuid('camara_origen_id')->nullable()->constrained('camaras')->restrictOnDelete();
            $table->foreignUuid('posicion_origen_id')->nullable()->constrained('posiciones')->restrictOnDelete();
            $table->foreignUuid('camara_destino_id')->nullable()->constrained('camaras')->restrictOnDelete();
            $table->foreignUuid('posicion_destino_id')->nullable()->constrained('posiciones')->restrictOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos')->restrictOnDelete();
            $table->text('instruccion')->nullable();
            $table->json('contexto')->nullable();
            $table->timestamp('asumida_at')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('completada_at')->nullable();
            $table->timestamp('cancelada_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['plan_operacional_id', 'secuencia'], 'tareas_plan_secuencia_unique');
            $table->index(['estado', 'prioridad', 'created_at'], 'tareas_estado_prioridad_fecha_idx');
            $table->index(['responsable_user_id', 'estado'], 'tareas_responsable_estado_idx');
            $table->index(['folio_id', 'estado'], 'tareas_folio_estado_idx');
        });

        Schema::table('movimientos', function (Blueprint $table): void {
            $table->foreignUuid('plan_operacional_id')
                ->nullable()
                ->after('operacion_id')
                ->constrained('planes_operacionales')
                ->restrictOnDelete();
            $table->foreignUuid('tarea_movimiento_id')
                ->nullable()
                ->after('plan_operacional_id')
                ->constrained('tareas_movimiento')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tarea_movimiento_id');
            $table->dropConstrainedForeignId('plan_operacional_id');
        });

        Schema::dropIfExists('tareas_movimiento');
        Schema::dropIfExists('planes_operacionales');
    }
};
