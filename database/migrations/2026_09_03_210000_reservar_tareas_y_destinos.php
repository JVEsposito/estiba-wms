<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas_tareas_movimiento', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tarea_movimiento_id')
                ->constrained('tareas_movimiento')
                ->restrictOnDelete();
            $table->foreignUuid('posicion_destino_id')
                ->nullable()
                ->constrained('posiciones')
                ->restrictOnDelete();
            $table->foreignUuid('bloqueo_tarea_id')
                ->nullable()
                ->constrained('tareas_movimiento')
                ->restrictOnDelete();
            $table->foreignUuid('bloqueo_posicion_id')
                ->nullable()
                ->constrained('posiciones')
                ->restrictOnDelete();
            $table->string('estado', 20)->default('activa');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->constrained('dispositivos')->restrictOnDelete();
            $table->timestamp('reservada_at');
            $table->timestamp('renovada_at');
            $table->timestamp('vence_at');
            $table->timestamp('liberada_at')->nullable();
            $table->timestamp('expirada_at')->nullable();
            $table->timestamp('completada_at')->nullable();
            $table->string('motivo_liberacion', 255)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            // Los campos de bloqueo solo contienen valores mientras el lease está activo.
            // Al finalizar quedan nulos, preservando el historial sin impedir una nueva toma.
            $table->unique('bloqueo_tarea_id', 'reservas_bloqueo_tarea_unique');
            $table->unique('bloqueo_posicion_id', 'reservas_bloqueo_posicion_unique');
            $table->index(['estado', 'vence_at'], 'reservas_estado_vencimiento_idx');
            $table->index(['tarea_movimiento_id', 'created_at'], 'reservas_tarea_fecha_idx');
            $table->index(['posicion_destino_id', 'updated_at'], 'reservas_destino_revision_idx');
            $table->index(['user_id', 'dispositivo_id', 'estado'], 'reservas_actor_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas_tareas_movimiento');
    }
};
