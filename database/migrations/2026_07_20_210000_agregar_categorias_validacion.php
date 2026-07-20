<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('codigo_externo', 100)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['temporada_id', 'nombre'], 'categoria_validacion_temporada_nombre_unique');
        });

        Schema::table('validaciones_pallet', function (Blueprint $table) {
            $table->foreignUuid('categoria_validacion_id')
                ->nullable()
                ->after('origen_validacion_id')
                ->constrained('categorias_validacion')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('validaciones_pallet', function (Blueprint $table) {
            $table->dropForeign(['categoria_validacion_id']);
            $table->dropColumn('categoria_validacion_id');
        });

        Schema::dropIfExists('categorias_validacion');
    }
};
