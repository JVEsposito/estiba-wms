<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloqueos_camara', function (Blueprint $table) {
            $table->uuid('camara_id')->primary();
            $table->foreignUuid('sesion_estiba_id')->unique();
            $table->dateTime('adquirido_at');
            $table->timestamps();

            $table->foreign('camara_id')
                ->references('id')
                ->on('camaras')
                ->restrictOnDelete();
            $table->foreign('sesion_estiba_id')
                ->references('id')
                ->on('sesiones_estiba')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloqueos_camara');
    }
};
