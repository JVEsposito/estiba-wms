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
    Schema::create('camaras', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('nombre'); 
        $table->enum('tipo', ['pre_frio', 'almacenaje', 'despacho'])->default('almacenaje');
        $table->integer('capacidad_maxima'); 
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camaras');
    }
};
