<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_operacional_hash')->nullable();
            $table->timestamp('pin_operacional_actualizado_at')->nullable();
            $table->unsignedTinyInteger('pin_operacional_intentos_fallidos')->default(0);
            $table->timestamp('pin_operacional_bloqueado_hasta')->nullable();
        });

        // Evidencia de quién confirmó, frente a la etiqueta física, el folio antes de retirarlo.
        Schema::create('confirmaciones_inicio_tarea', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tarea_movimiento_id')->constrained('tareas_movimiento')->restrictOnDelete();
            $table->foreignUuid('folio_id')->nullable()->constrained('folios')->restrictOnDelete();
            $table->string('numero_folio', 50);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->constrained('dispositivos')->restrictOnDelete();
            $table->string('resultado', 30);
            $table->string('digitos_ingresados', 50)->nullable();
            $table->timestamp('confirmado_at');
            $table->timestamps();

            $table->index(['tarea_movimiento_id', 'confirmado_at'], 'confirmaciones_inicio_tarea_tarea_idx');
            $table->index(['user_id', 'confirmado_at'], 'confirmaciones_inicio_tarea_usuario_idx');
            $table->index(['resultado', 'confirmado_at'], 'confirmaciones_inicio_tarea_resultado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('confirmaciones_inicio_tarea');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pin_operacional_hash',
                'pin_operacional_actualizado_at',
                'pin_operacional_intentos_fallidos',
                'pin_operacional_bloqueado_hasta',
            ]);
        });
    }
};
