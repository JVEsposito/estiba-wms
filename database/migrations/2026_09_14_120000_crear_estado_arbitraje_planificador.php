<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_arbitraje_planificador', function (Blueprint $table): void {
            $table->foreignUuid('temporada_id')
                ->primary()
                ->constrained('temporadas')
                ->cascadeOnDelete();
            $table->foreignUuid('ultimo_ciclo_id')
                ->nullable()
                ->constrained('ciclos_arbitraje_maniobras')
                ->nullOnDelete();
            $table->unsignedBigInteger('version_solicitada')->default(0);
            $table->unsignedBigInteger('version_calculada')->default(0);
            $table->unsignedBigInteger('calculos_exitosos')->default(0);
            $table->unsignedBigInteger('calculos_fallidos')->default(0);
            $table->unsignedInteger('espera_ultimo_calculo_ms')->nullable();
            $table->unsignedInteger('duracion_ultimo_calculo_ms')->nullable();
            $table->timestamp('solicitado_at')->nullable();
            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('calculado_at')->nullable();
            $table->timestamp('fallo_at')->nullable();
            $table->string('ultimo_motivo', 80)->nullable();
            $table->string('ultimo_error', 500)->nullable();
            $table->timestamps();

            $table->index('calculado_at', 'estado_arbitraje_calculado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_arbitraje_planificador');
    }
};
