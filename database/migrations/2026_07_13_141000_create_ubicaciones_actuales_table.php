<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubicaciones_actuales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('folio_id')
                ->unique()
                ->constrained('folios')
                ->restrictOnDelete();
            $table->foreignUuid('posicion_id')
                ->unique()
                ->constrained('posiciones')
                ->restrictOnDelete();
            $table->foreignUuid('movimiento_id')
                ->unique()
                ->constrained('movimientos')
                ->restrictOnDelete();
            $table->dateTime('ubicado_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubicaciones_actuales');
    }
};
