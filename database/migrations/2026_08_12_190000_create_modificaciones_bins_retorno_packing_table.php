<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modificaciones_bin_retorno_packing', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('bin_retorno_packing_id')
                ->constrained('bins_retorno_packing')
                ->restrictOnDelete();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->json('datos_anteriores');
            $table->json('datos_nuevos');
            $table->text('motivo');
            $table->foreignId('modificado_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('modificado_at');
            $table->timestamps(precision: 6);

            $table->index(
                ['bin_retorno_packing_id', 'modificado_at'],
                'modificaciones_bin_retorno_fecha_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modificaciones_bin_retorno_packing');
    }
};
