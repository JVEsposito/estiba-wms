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
            $table->uuid('bin_retorno_packing_id');
            $table->foreign(
                'bin_retorno_packing_id',
                'mod_bin_retorno_bin_fk',
            )->references('id')->on('bins_retorno_packing')->restrictOnDelete();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->json('datos_anteriores');
            $table->json('datos_nuevos');
            $table->text('motivo');
            $table->unsignedBigInteger('modificado_por_user_id');
            $table->foreign(
                'modificado_por_user_id',
                'mod_bin_retorno_user_fk',
            )->references('id')->on('users')->restrictOnDelete();
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
