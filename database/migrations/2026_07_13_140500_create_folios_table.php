<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('numero_folio', 50)->unique();
            $table->string('tipo_bulto', 30)->index();
            $table->foreignUuid('condicion_sag_id')
                ->nullable()
                ->constrained('condiciones_sag')
                ->restrictOnDelete();
            $table->string('estado_operacional', 50)->default('disponible')->index();
            $table->dateTime('fecha_ingreso');
            $table->boolean('activo')->default(true)->index();

            $table->string('variedad', 100)->nullable();
            $table->string('calibre', 100)->nullable();
            $table->string('marca', 150)->nullable();
            $table->string('exportadora', 150)->nullable();

            $table->string('origen_sistema', 50)->default('manual')->index();
            $table->string('identificador_externo', 150)->nullable();
            $table->string('estado_integracion', 30)->default('no_vinculado')->index();
            $table->dateTime('sincronizado_at')->nullable();
            $table->json('datos_externos')->nullable();
            $table->timestamps();

            $table->unique(
                ['origen_sistema', 'identificador_externo'],
                'folios_origen_externo_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folios');
    }
};
