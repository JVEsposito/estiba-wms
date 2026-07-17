<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones_operacionales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 180)->unique();
            $table->string('tipo', 50)->index();
            $table->string('audiencia_tipo', 20);
            $table->string('audiencia_valor', 80);
            $table->string('severidad', 20)->index();
            $table->string('titulo', 160);
            $table->text('mensaje');
            $table->foreignUuid('carga_id')
                ->nullable()
                ->constrained('cargas')
                ->restrictOnDelete();
            $table->foreignUuid('folio_id')
                ->nullable()
                ->constrained('folios')
                ->restrictOnDelete();
            $table->foreignUuid('incidencia_carga_folio_id')
                ->nullable()
                ->constrained('incidencias_carga_folio')
                ->restrictOnDelete();
            $table->json('datos')->nullable();
            $table->timestamps();

            $table->index(['audiencia_tipo', 'audiencia_valor', 'created_at'], 'notificaciones_audiencia_fecha_idx');
            $table->index(['carga_id', 'created_at']);
        });

        Schema::create('lecturas_notificaciones_operacionales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('notificacion_operacional_id')
                ->constrained('notificaciones_operacionales')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('leida_at')->nullable();
            $table->timestamp('confirmada_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['notificacion_operacional_id', 'user_id'],
                'lecturas_notificacion_usuario_unique',
            );
            $table->index(['user_id', 'leida_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturas_notificaciones_operacionales');
        Schema::dropIfExists('notificaciones_operacionales');
    }
};
