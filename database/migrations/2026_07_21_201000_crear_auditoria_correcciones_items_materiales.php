<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correcciones_items_folios_materiales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->foreignUuid('folio_id')->constrained('folios_materiales', 'folio_id')->restrictOnDelete();
            $table->foreignUuid('item_anterior_id')->constrained('items_materiales')->restrictOnDelete();
            $table->foreignUuid('item_nuevo_id')->constrained('items_materiales')->restrictOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->text('motivo');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('ocurrido_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correcciones_items_folios_materiales');
    }
};
