<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operaciones_sincronizacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')
                ->constrained('dispositivos')
                ->restrictOnDelete();
            $table->string('tipo', 50)->index();
            $table->string('estado', 30)->default('pendiente')->index();
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->json('resultado')->nullable();
            $table->string('codigo_error', 100)->nullable()->index();
            $table->text('mensaje_error')->nullable();
            $table->json('versiones_conocidas')->nullable();
            $table->json('versiones_resultantes')->nullable();
            $table->dateTime('generada_dispositivo_at');
            $table->dateTime('recibida_servidor_at');
            $table->dateTime('procesada_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operaciones_sincronizacion');
    }
};
