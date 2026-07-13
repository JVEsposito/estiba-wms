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
    Schema::create('posicions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('camara_id')->constrained('camaras')->onDelete('cascade');
        $table->foreignUuid('folio_id')->nullable()->constrained('folios')->onDelete('set null');
        $table->string('fila'); 
        $table->integer('profundidad'); 
        $table->integer('altura'); 
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posicions');
    }
};
