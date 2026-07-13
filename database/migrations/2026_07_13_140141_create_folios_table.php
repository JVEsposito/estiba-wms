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
    Schema::create('folios', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('despacho_id')->nullable()->constrained('despachos')->onDelete('set null');
        $table->string('numero_folio', 20)->unique(); 
        $table->string('variedad')->nullable(); 
        $table->string('calibre')->nullable(); 
        $table->string('marca')->nullable(); 
        $table->string('exportadora')->nullable(); 
        
        // ¡NUEVO! Define si el pallet es un saldo/incompleto
        $table->boolean('es_saldo')->default(false); 
        
        // ¡ACTUALIZADO! Agregamos 'consolidado_repa' para el historial histórico
        $table->enum('estado', ['en_recepcion', 'en_camara', 'despachado', 'consolidado_repa'])->default('en_recepcion');
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folios');
    }
};
