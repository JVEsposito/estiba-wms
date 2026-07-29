<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reinicios_operacionales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique('reinicio_operacional_operacion_unique');
            $table->foreignUuid('temporada_id')
                ->constrained('temporadas')
                ->restrictOnDelete();
            $table->json('alcances');
            $table->text('motivo');
            $table->json('resumen_antes');
            $table->json('resumen_eliminado');
            $table->json('resumen_despues');
            $table->foreignId('ejecutado_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(
                ['temporada_id', 'created_at'],
                'reinicio_operacional_temporada_fecha_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reinicios_operacionales');
    }
};
