<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correcciones_validacion_pallet', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id');
            $table->char('payload_hash', 64);
            $table->foreignUuid('validacion_pallet_id')
                ->constrained('validaciones_pallet')
                ->restrictOnDelete();
            $table->foreignUuid('folio_id')
                ->constrained('folios')
                ->restrictOnDelete();
            $table->foreignId('corregido_por_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->json('datos_anteriores');
            $table->json('datos_nuevos');
            $table->text('motivo');
            $table->timestamp('corregido_at');
            $table->timestamps();

            $table->unique('operacion_id', 'corr_val_operacion_unique');
            $table->index(
                ['validacion_pallet_id', 'corregido_at'],
                'corr_val_historial_idx',
            );
            $table->index(['folio_id', 'corregido_at'], 'corr_val_folio_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correcciones_validacion_pallet');
    }
};
