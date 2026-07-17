<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporadas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('activa')->default(false)->index();
            $table->unsignedInteger('version_catalogo')->default(1);
            $table->timestamps();
        });

        Schema::create('articulos_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('especie', 100);
            $table->string('variedad', 100);
            $table->string('calibre', 50);
            $table->string('envase', 100);
            $table->string('codigo_externo', 100)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['temporada_id', 'especie', 'variedad', 'calibre', 'envase'], 'articulo_validacion_unique');
        });

        Schema::create('origenes_validacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->string('cliente', 150);
            $table->string('marca', 150);
            $table->string('csg', 50);
            $table->string('predio', 150)->nullable();
            $table->string('codigo_externo', 100)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['temporada_id', 'cliente', 'marca', 'csg'], 'origen_validacion_unique');
        });

        Schema::create('secuencias_validacion_folio', function (Blueprint $table) {
            $table->string('numero_folio', 50)->primary();
            $table->unsignedInteger('ultimo_intento')->default(0);
            $table->timestamps();
        });

        Schema::create('validaciones_pallet', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->string('numero_folio', 50)->index();
            $table->unsignedInteger('numero_intento');
            $table->string('tipo_bulto', 30);
            $table->unsignedInteger('cantidad_cajas');
            $table->foreignUuid('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->foreignUuid('articulo_validacion_id')->constrained('articulos_validacion')->restrictOnDelete();
            $table->foreignUuid('origen_validacion_id')->constrained('origenes_validacion')->restrictOnDelete();
            $table->string('resultado', 30)->index();
            $table->string('estado', 30)->index();
            $table->string('motivo', 80)->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedInteger('catalogo_version_dispositivo');
            $table->unsignedInteger('catalogo_version_servidor');
            $table->json('snapshot');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->constrained('dispositivos')->restrictOnDelete();
            $table->foreignUuid('folio_id')->nullable()->constrained('folios')->restrictOnDelete();
            $table->foreignUuid('validacion_conflicto_id')->nullable()->constrained('validaciones_pallet')->restrictOnDelete();
            $table->dateTime('generado_dispositivo_at');
            $table->dateTime('recibido_servidor_at');
            $table->timestamps();
            $table->unique(['numero_folio', 'numero_intento'], 'validacion_folio_intento_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validaciones_pallet');
        Schema::dropIfExists('secuencias_validacion_folio');
        Schema::dropIfExists('origenes_validacion');
        Schema::dropIfExists('articulos_validacion');
        Schema::dropIfExists('temporadas');
    }
};
