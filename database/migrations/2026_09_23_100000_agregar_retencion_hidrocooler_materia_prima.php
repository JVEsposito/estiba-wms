<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procesos_hidrocooler_materia_prima', function (Blueprint $table): void {
            $table->boolean('control_inicial_conforme')->nullable();
            $table->decimal('cloro_libre_final_ppm', 6, 2)->nullable();
            $table->decimal('ph_agua_final', 4, 2)->nullable();
            $table->string('condicion_visual_agua_final', 20)->nullable();
            $table->boolean('dosificador_operativo_final')->nullable();
            $table->boolean('control_final_conforme')->nullable();
            $table->text('motivo_retencion')->nullable();
            $table->uuid('operacion_liberacion_id')->nullable()->unique('hidro_mp_liberacion_unique');
            $table->char('payload_liberacion_hash', 64)->nullable();
            $table->decimal('temperatura_verificacion_c', 6, 2)->nullable();
            $table->decimal('cloro_libre_verificacion_ppm', 6, 2)->nullable();
            $table->decimal('ph_agua_verificacion', 4, 2)->nullable();
            $table->text('evaluacion_producto')->nullable();
            $table->text('verificacion_liberacion')->nullable();
            $table->foreignId('liberado_por_user_id')->nullable();
            $table->timestamp('liberado_at')->nullable();
            $table->foreign('liberado_por_user_id', 'hidro_mp_liberado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procesos_hidrocooler_materia_prima', function (Blueprint $table): void {
            $table->dropForeign('hidro_mp_liberado_por_fk');
            $table->dropUnique('hidro_mp_liberacion_unique');
            $table->dropColumn([
                'control_inicial_conforme', 'cloro_libre_final_ppm', 'ph_agua_final',
                'condicion_visual_agua_final', 'dosificador_operativo_final',
                'control_final_conforme', 'motivo_retencion', 'operacion_liberacion_id',
                'payload_liberacion_hash', 'temperatura_verificacion_c',
                'cloro_libre_verificacion_ppm', 'ph_agua_verificacion',
                'evaluacion_producto', 'verificacion_liberacion', 'liberado_por_user_id',
                'liberado_at',
            ]);
        });
    }
};
