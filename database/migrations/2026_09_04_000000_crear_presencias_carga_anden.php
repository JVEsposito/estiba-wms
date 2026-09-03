<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presencias_carga_anden', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('carga_id')->constrained('cargas')->restrictOnDelete();
            $table->foreignUuid('anden_id')->constrained('andenes')->restrictOnDelete();
            $table->foreignUuid('bloqueo_carga_id')
                ->nullable()
                ->constrained('cargas')
                ->restrictOnDelete();
            $table->foreignUuid('bloqueo_anden_id')
                ->nullable()
                ->constrained('andenes')
                ->restrictOnDelete();
            $table->string('estado', 20)->default('activa');
            $table->uuid('operacion_ingreso_id')->unique();
            $table->char('ingreso_payload_hash', 64);
            $table->string('patente', 20);
            $table->string('conductor', 150)->nullable();
            $table->text('observacion_ingreso')->nullable();
            $table->foreignId('ingresada_por_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('ingresada_at')->index();
            $table->uuid('operacion_salida_id')->nullable()->unique();
            $table->char('salida_payload_hash', 64)->nullable();
            $table->text('motivo_finalizacion')->nullable();
            $table->foreignId('finalizada_por_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('finalizada_at')->nullable()->index();
            $table->timestamps();

            // Los bloqueos se limpian al finalizar. MySQL admite varios NULL y
            // mantiene una única presencia activa por carga y por andén.
            $table->unique('bloqueo_carga_id', 'presencias_bloqueo_carga_unique');
            $table->unique('bloqueo_anden_id', 'presencias_bloqueo_anden_unique');
            $table->index(['estado', 'ingresada_at'], 'presencias_estado_ingreso_idx');
            $table->index(['carga_id', 'created_at'], 'presencias_carga_historial_idx');
            $table->index(['anden_id', 'created_at'], 'presencias_anden_historial_idx');
        });

        Schema::table('tareas_movimiento', function (Blueprint $table): void {
            $table->foreignUuid('reemplazada_por_tarea_id')
                ->nullable()
                ->after('cancelada_at')
                ->constrained('tareas_movimiento')
                ->restrictOnDelete();
            $table->foreignId('cancelada_por_user_id')
                ->nullable()
                ->after('reemplazada_por_tarea_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('motivo_cancelacion', 255)
                ->nullable()
                ->after('cancelada_por_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('tareas_movimiento', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reemplazada_por_tarea_id');
            $table->dropConstrainedForeignId('cancelada_por_user_id');
            $table->dropColumn('motivo_cancelacion');
        });

        Schema::dropIfExists('presencias_carga_anden');
    }
};
