<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recalculos_pendientes_planificador', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 40);
            $table->uuid('fuente_id');
            $table->unsignedBigInteger('version_solicitada')->default(0);
            $table->unsignedBigInteger('version_calculada')->default(0);
            $table->timestamp('solicitado_at')->nullable();
            $table->timestamp('calculado_at')->nullable();
            $table->timestamp('ultimo_reenvio_at')->nullable();
            $table->timestamp('fallo_at')->nullable();
            $table->string('ultimo_error', 500)->nullable();
            $table->timestamps();

            $table->unique(['tipo', 'fuente_id'], 'recalculo_planificador_fuente_unica');
            $table->index('ultimo_reenvio_at', 'recalculo_planificador_reenvio_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recalculos_pendientes_planificador');
    }
};
