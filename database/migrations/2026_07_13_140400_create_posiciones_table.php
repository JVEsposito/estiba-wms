<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posiciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('camara_id')
                ->constrained('camaras')
                ->restrictOnDelete();
            $table->string('fila', 50);
            $table->unsignedSmallInteger('profundidad');
            $table->unsignedSmallInteger('nivel');
            $table->string('etiqueta', 100)->nullable();
            $table->string('estado', 30)->default('activa')->index();
            $table->timestamps();

            $table->unique(
                ['camara_id', 'fila', 'profundidad', 'nivel'],
                'posiciones_coordenadas_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posiciones');
    }
};
