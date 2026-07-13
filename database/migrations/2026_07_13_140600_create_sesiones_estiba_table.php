<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones_estiba', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('camara_id')
                ->constrained('camaras')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')
                ->constrained('dispositivos')
                ->restrictOnDelete();
            $table->string('estado', 30)->default('abierta')->index();
            $table->unsignedBigInteger('version_inicial');
            $table->unsignedBigInteger('version_final')->nullable();
            $table->dateTime('iniciada_at');
            $table->dateTime('ultima_actividad_at')->nullable();
            $table->dateTime('cerrada_at')->nullable();
            $table->foreignId('cierre_forzado_por_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('motivo_cierre')->nullable();
            $table->timestamps();

            $table->index(['camara_id', 'estado'], 'sesiones_camara_estado_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_estiba');
    }
};
