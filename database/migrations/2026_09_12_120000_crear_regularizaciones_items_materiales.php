<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regularizaciones_items_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->foreignUuid('item_duplicado_id')->unique()->constrained('items_materiales')->restrictOnDelete();
            $table->foreignUuid('item_canonico_id')->constrained('items_materiales')->restrictOnDelete();
            $table->json('snapshot');
            $table->text('motivo');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('ocurrido_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regularizaciones_items_materiales');
    }
};
