<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas_posiciones_inspeccion_sag', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('lote_inspeccion_sag_id');
            $table->foreignUuid('plan_operacional_id');
            $table->foreignUuid('posicion_id');
            $table->foreign('lote_inspeccion_sag_id', 'reservas_sag_lote_fk')
                ->references('id')->on('lotes_inspeccion_sag')->restrictOnDelete();
            $table->foreign('plan_operacional_id', 'reservas_sag_plan_fk')
                ->references('id')->on('planes_operacionales')->restrictOnDelete();
            $table->foreign('posicion_id', 'reservas_sag_posicion_fk')
                ->references('id')->on('posiciones')->restrictOnDelete();
            $table->string('tipo_espacio', 20);
            $table->unsignedInteger('orden');
            // Mientras la reserva está activa contiene el UUID de la posición.
            // Al liberarla queda nulo para conservar historia y permitir reutilización.
            $table->uuid('clave_bloqueo')->nullable();
            $table->timestamp('reservada_at');
            $table->timestamp('liberada_at')->nullable();
            $table->string('motivo_liberacion', 255)->nullable();
            $table->timestamps();

            $table->unique('clave_bloqueo', 'reservas_sag_posicion_activa_unique');
            $table->unique(
                ['lote_inspeccion_sag_id', 'posicion_id'],
                'reservas_sag_lote_posicion_unique',
            );
            $table->index(
                ['lote_inspeccion_sag_id', 'liberada_at'],
                'reservas_sag_lote_activa_idx',
            );
            $table->index(
                ['posicion_id', 'liberada_at'],
                'reservas_sag_posicion_historial_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas_posiciones_inspeccion_sag');
    }
};
