<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciclos_arbitraje_maniobras', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->char('snapshot_version', 64)->unique();
            $table->unsignedSmallInteger('capacidad_ejecucion');
            $table->unsignedSmallInteger('frontera_max');
            $table->json('contexto')->nullable();
            $table->timestamps();

            $table->index(['temporada_id', 'created_at'], 'ciclos_arbitraje_temporada_fecha_idx');
        });

        Schema::create('decisiones_arbitraje_maniobras', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('ciclo_arbitraje_id')
                ->constrained('ciclos_arbitraje_maniobras')
                ->restrictOnDelete();
            $table->foreignUuid('maniobra_operacional_id')
                ->constrained('maniobras_operacionales')
                ->restrictOnDelete();
            $table->unsignedInteger('orden');
            $table->string('decision', 30);
            $table->bigInteger('puntaje');
            $table->integer('beneficio_neto');
            $table->string('motivo', 255);
            $table->json('conflictos')->nullable();
            $table->timestamps();

            $table->unique(
                ['ciclo_arbitraje_id', 'maniobra_operacional_id'],
                'decision_arbitraje_ciclo_maniobra_unique',
            );
            $table->index(
                ['maniobra_operacional_id', 'id'],
                'decision_arbitraje_maniobra_fecha_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisiones_arbitraje_maniobras');
        Schema::dropIfExists('ciclos_arbitraje_maniobras');
    }
};
