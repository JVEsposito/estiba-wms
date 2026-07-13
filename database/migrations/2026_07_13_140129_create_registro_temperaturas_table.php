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
    Schema::create('registros_temperatura', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('camara_id')->constrained('camaras')->onDelete('cascade');
        $table->decimal('grados', 5, 2); 
        $table->string('observacion')->nullable(); 
        $table->timestamps(); 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registro_temperaturas');
    }
};
