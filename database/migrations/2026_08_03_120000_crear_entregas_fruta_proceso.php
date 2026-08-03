<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas_fruta_proceso', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique('entrega_fp_operacion_unique');
            $table->char('payload_hash', 64);
            $table->uuid('lote_materia_prima_id');
            $table->uuid('asignacion_camara_lote_id');
            $table->uuid('camara_id');
            $table->unsignedInteger('cantidad_envases');
            $table->unsignedInteger('saldo_anterior');
            $table->unsignedInteger('saldo_posterior');
            $table->string('linea_proceso', 50);
            $table->string('turno', 10);
            $table->string('numero_orden', 80);
            $table->text('observacion')->nullable();
            $table->foreignId('entregado_por_user_id');
            $table->uuid('dispositivo_id')->nullable();
            $table->timestamp('entregado_at', precision: 6);
            $table->uuid('operacion_anulacion_id')->nullable()
                ->unique('entrega_fp_anulacion_unique');
            $table->foreignId('anulado_por_user_id')->nullable();
            $table->timestamp('anulado_at', precision: 6)->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps(precision: 6);

            $table->foreign('lote_materia_prima_id', 'entrega_fp_lote_fk')
                ->references('id')->on('lotes_materia_prima')->restrictOnDelete();
            $table->foreign('asignacion_camara_lote_id', 'entrega_fp_asignacion_fk')
                ->references('id')->on('asignaciones_camara_lote_materia_prima')->restrictOnDelete();
            $table->foreign('camara_id', 'entrega_fp_camara_fk')
                ->references('id')->on('camaras')->restrictOnDelete();
            $table->foreign('entregado_por_user_id', 'entrega_fp_entregado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('dispositivo_id', 'entrega_fp_dispositivo_fk')
                ->references('id')->on('dispositivos')->restrictOnDelete();
            $table->foreign('anulado_por_user_id', 'entrega_fp_anulado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->index(
                ['lote_materia_prima_id', 'anulado_at', 'entregado_at'],
                'entrega_fp_lote_estado_fecha_index',
            );
            $table->index(
                ['numero_orden', 'linea_proceso', 'turno'],
                'entrega_fp_destino_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas_fruta_proceso');
    }
};
