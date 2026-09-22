<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_cierres_acceso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('administrador_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('usuario_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('token_id');
            $table->string('token_nombre');
            $table->string('dispositivo_codigo')->nullable();
            $table->unsignedInteger('sesiones_camara_cerradas')->default(0);
            $table->timestamp('created_at');

            $table->index(['usuario_user_id', 'created_at'], 'auditoria_cierres_acceso_usuario_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_cierres_acceso');
    }
};
