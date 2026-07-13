<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('operacion_id')
                ->unique()
                ->constrained('operaciones_sincronizacion')
                ->restrictOnDelete();
            $table->foreignUuid('folio_id')
                ->constrained('folios')
                ->restrictOnDelete();
            $table->string('tipo_movimiento', 50)->index();

            $table->foreignUuid('camara_origen_id')
                ->nullable()
                ->constrained('camaras')
                ->restrictOnDelete();
            $table->foreignUuid('posicion_origen_id')
                ->nullable()
                ->constrained('posiciones')
                ->restrictOnDelete();
            $table->foreignUuid('camara_destino_id')
                ->nullable()
                ->constrained('camaras')
                ->restrictOnDelete();
            $table->foreignUuid('posicion_destino_id')
                ->nullable()
                ->constrained('posiciones')
                ->restrictOnDelete();

            $table->foreignUuid('sesion_origen_id')
                ->nullable()
                ->constrained('sesiones_estiba')
                ->restrictOnDelete();
            $table->foreignUuid('sesion_destino_id')
                ->nullable()
                ->constrained('sesiones_estiba')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')
                ->constrained('dispositivos')
                ->restrictOnDelete();

            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('version_origen_anterior')->nullable();
            $table->unsignedBigInteger('version_origen_resultante')->nullable();
            $table->unsignedBigInteger('version_destino_anterior')->nullable();
            $table->unsignedBigInteger('version_destino_resultante')->nullable();
            $table->dateTime('generado_dispositivo_at');
            $table->dateTime('recibido_servidor_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['folio_id', 'created_at'],
                'movimientos_folio_fecha_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
