<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anulaciones_validacion_pallet', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->foreignUuid('validacion_pallet_id')
                ->unique()
                ->constrained('validaciones_pallet');
            $table->foreignUuid('folio_id')->constrained('folios');
            $table->string('numero_folio', 80)->index();
            $table->string('motivo_categoria', 50)->index();
            $table->text('motivo');
            $table->foreignId('anulado_por_user_id')->constrained('users');
            $table->timestamp('anulado_at');
            $table->json('snapshot');
            $table->timestamps();

            $table->index(
                ['anulado_at', 'motivo_categoria'],
                'anulaciones_validacion_fecha_categoria',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anulaciones_validacion_pallet');
    }
};
