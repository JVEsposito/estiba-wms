<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conexiones_existencias', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expira_at')->nullable()->index();
            $table->timestamp('ultimo_uso_at')->nullable();
            $table->timestamp('revocado_at')->nullable()->index();
            $table->timestamps();

            $table->index(
                ['user_id', 'tipo', 'revocado_at'],
                'conexiones_existencias_usuario_tipo_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conexiones_existencias');
    }
};
