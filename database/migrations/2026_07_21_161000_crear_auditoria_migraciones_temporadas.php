<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migraciones_temporadas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_origen_id')->constrained('temporadas')->restrictOnDelete();
            $table->foreignUuid('temporada_destino_id')->constrained('temporadas')->restrictOnDelete();
            $table->boolean('copio_catalogo_validacion')->default(false);
            $table->boolean('copio_catalogo_materiales')->default(false);
            $table->boolean('migro_inventario_materiales')->default(false);
            $table->boolean('activo_destino')->default(false);
            $table->json('resumen');
            $table->foreignId('creado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['temporada_origen_id', 'temporada_destino_id'], 'migraciones_temporadas_origen_destino_idx');
        });

        Schema::create('migraciones_temporadas_folios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('migracion_temporada_id')->constrained('migraciones_temporadas')->restrictOnDelete();
            $table->foreignUuid('folio_id')->constrained('folios')->restrictOnDelete();
            $table->foreignUuid('item_material_origen_id')->constrained('items_materiales')->restrictOnDelete();
            $table->foreignUuid('item_material_destino_id')->constrained('items_materiales')->restrictOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->timestamps();

            $table->unique(['migracion_temporada_id', 'folio_id'], 'migracion_temporada_folio_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migraciones_temporadas_folios');
        Schema::dropIfExists('migraciones_temporadas');
    }
};
