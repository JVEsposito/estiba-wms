<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_control_ambiental', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id');
            $table->char('payload_hash', 64);
            $table->foreignUuid('camara_id')
                ->constrained('camaras')
                ->restrictOnDelete();
            $table->foreignId('registrado_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')
                ->constrained('dispositivos')
                ->restrictOnDelete();
            $table->decimal('temperatura_inicio_c', 5, 2);
            $table->decimal('temperatura_medio_c', 5, 2);
            $table->decimal('temperatura_fondo_c', 5, 2);
            $table->dateTime('capturado_at');
            $table->dateTime('recibido_servidor_at');
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique('operacion_id', 'control_ambiental_operacion_unique');
            $table->index(
                ['camara_id', 'capturado_at'],
                'control_ambiental_camara_fecha_idx',
            );
        });

        Schema::create('correcciones_control_ambiental', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id');
            $table->char('payload_hash', 64);
            $table->uuid('registro_control_ambiental_id');
            $table->foreign(
                'registro_control_ambiental_id',
                'correccion_ambiental_registro_fk',
            )
                ->references('id')
                ->on('registros_control_ambiental')
                ->restrictOnDelete();
            $table->foreignId('corregido_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->json('datos_anteriores');
            $table->json('datos_nuevos');
            $table->text('motivo');
            $table->dateTime('corregido_at');
            $table->timestamps();

            $table->unique('operacion_id', 'correccion_ambiental_operacion_unique');
            $table->index(
                ['registro_control_ambiental_id', 'corregido_at'],
                'correccion_ambiental_historial_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correcciones_control_ambiental');
        Schema::dropIfExists('registros_control_ambiental');
    }
};
