<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retenciones_operacionales_folios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('folio_id');
            // Contiene el UUID del folio solo mientras la retención está activa.
            $table->uuid('bloqueo_folio_id')->nullable();
            $table->string('estado', 20)->index();
            $table->string('motivo', 255);
            $table->string('estado_operacional_anterior', 40);
            $table->string('condicion_termica_anterior', 40)->nullable();
            $table->string('habilitacion_almacenamiento_anterior', 40)->nullable();
            $table->foreignUuid('carga_id_original')->nullable();
            $table->foreignUuid('carga_folio_id_original')->nullable();
            $table->foreignId('retenido_por_user_id')->nullable();
            $table->timestamp('retenido_at');
            $table->foreignId('liberado_por_user_id')->nullable();
            $table->timestamp('liberado_at')->nullable();
            $table->json('contexto')->nullable();
            $table->timestamps();

            $table->foreign('folio_id', 'retenciones_folio_fk')
                ->references('id')->on('folios')->restrictOnDelete();
            $table->foreign('bloqueo_folio_id', 'retenciones_bloqueo_folio_fk')
                ->references('id')->on('folios')->restrictOnDelete();
            $table->foreign('carga_id_original', 'retenciones_carga_fk')
                ->references('id')->on('cargas')->restrictOnDelete();
            $table->foreign('carga_folio_id_original', 'retenciones_carga_folio_fk')
                ->references('id')->on('carga_folios')->restrictOnDelete();
            $table->foreign('retenido_por_user_id', 'retenciones_retenido_por_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('liberado_por_user_id', 'retenciones_liberado_por_fk')
                ->references('id')->on('users')->restrictOnDelete();

            $table->unique('bloqueo_folio_id', 'retenciones_folio_activa_unique');
            $table->index(['folio_id', 'retenido_at'], 'retenciones_folio_historial_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retenciones_operacionales_folios');
    }
};
