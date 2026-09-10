<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos_planta', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            $table->unsignedInteger('version')->default(1);
            $table->json('elementos');
            $table->foreignId('actualizado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos_planta');
    }
};
