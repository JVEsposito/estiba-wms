<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Baja administrativa auditada de un registro que quedó abierto al
        // cerrar la temporada. No admite modificación ni eliminación física.
        Schema::create('regularizaciones_cierre_temporada', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')
                ->constrained('temporadas', indexName: 'regularizaciones_cierre_temporada_fk')
                ->restrictOnDelete();
            $table->uuid('lote_regularizacion_id')->index();
            $table->string('categoria', 40);
            $table->string('entidad_id', 64);
            $table->string('referencia', 120);
            $table->string('estado_anterior', 40)->nullable();
            $table->string('motivo_categoria', 40);
            $table->string('motivo', 500);
            $table->json('snapshot');
            $table->foreignId('regularizado_por_user_id')
                ->constrained('users', indexName: 'regularizaciones_cierre_usuario_fk')
                ->restrictOnDelete();
            $table->timestamp('regularizado_at');
            $table->timestamps();

            $table->unique(['categoria', 'entidad_id'], 'regularizaciones_cierre_entidad_unique');
            $table->index(['temporada_id', 'categoria'], 'regularizaciones_cierre_temporada_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regularizaciones_cierre_temporada');
    }
};
