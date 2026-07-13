<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('movimientos', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('folio_id')->constrained('folios')->onDelete('cascade');
        $table->foreignUuid('posicion_origen_id')->nullable()->constrained('posicions')->onDelete('set null');
        $table->foreignUuid('posicion_destino_id')->nullable()->constrained('posicions')->onDelete('set null');
        $table->enum('tipo_movimiento', ['ingreso', 'reubicacion', 'despacho']);
        $table->timestamps(); 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
