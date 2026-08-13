<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transiciones_operacionales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('dominio', 40)->index();
            $table->string('tipo', 100);
            $table->uuid('operacion_id')->nullable();
            $table->string('estado', 20)->index();
            $table->string('sujeto_tipo', 160)->nullable();
            $table->string('sujeto_id', 64)->nullable();
            $table->string('referencia', 160)->nullable()->index();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')
                ->nullable()
                ->constrained('dispositivos')
                ->restrictOnDelete();
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->json('resultado')->nullable();
            $table->string('error_tipo', 180)->nullable();
            $table->string('error_codigo', 100)->nullable()->index();
            $table->text('error_mensaje')->nullable();
            $table->unsignedInteger('cantidad_cambios')->default(0);
            $table->dateTime('ocurrido_at');
            $table->dateTime('finalizado_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['dominio', 'tipo', 'operacion_id'],
                'transiciones_dominio_tipo_operacion_unique',
            );
            $table->index(
                ['sujeto_tipo', 'sujeto_id'],
                'transiciones_sujeto_index',
            );
            $table->index(
                ['dominio', 'created_at'],
                'transiciones_dominio_fecha_index',
            );
        });

        Schema::create('cambios_transiciones_operacionales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('transicion_operacional_id');
            $table->foreign(
                'transicion_operacional_id',
                'cambios_transicion_operacional_fk',
            )
                ->references('id')
                ->on('transiciones_operacionales')
                ->restrictOnDelete();
            $table->unsignedInteger('secuencia');
            $table->string('modelo_tipo', 160);
            $table->string('modelo_id', 64);
            $table->string('tipo', 20);
            $table->json('campos');
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['transicion_operacional_id', 'secuencia'],
                'cambios_transicion_secuencia_unique',
            );
            $table->index(
                ['modelo_tipo', 'modelo_id'],
                'cambios_transicion_modelo_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_transiciones_operacionales');
        Schema::dropIfExists('transiciones_operacionales');
    }
};
