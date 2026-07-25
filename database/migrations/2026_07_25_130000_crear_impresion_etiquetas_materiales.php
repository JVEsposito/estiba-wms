<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfiles_impresion_etiquetas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 120);
            $table->string('fabricante', 40);
            $table->string('modelo', 80)->nullable();
            $table->string('lenguaje', 16)->default('zpl');
            $table->unsignedSmallInteger('dpi');
            $table->decimal('ancho_mm', 7, 2);
            $table->decimal('alto_mm', 7, 2);
            $table->string('orientacion', 16)->default('horizontal');
            $table->boolean('predeterminado')->default(false)->index();
            $table->boolean('activo')->default(true)->index();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('actualizado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('trabajos_impresion_materiales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->char('payload_hash', 64);
            $table->foreignUuid('recepcion_material_id')->constrained('recepciones_materiales')->restrictOnDelete();
            $table->uuid('perfil_impresion_etiqueta_id')->nullable();
            $table->foreign(
                'perfil_impresion_etiqueta_id',
                'trabajos_impresion_perfil_fk',
            )
                ->references('id')
                ->on('perfiles_impresion_etiquetas')
                ->restrictOnDelete();
            $table->string('formato', 12);
            $table->string('canal', 24);
            $table->string('estado', 24)->index();
            $table->unsignedSmallInteger('copias')->default(1);
            $table->text('motivo_reimpresion')->nullable();
            $table->json('perfil_snapshot');
            $table->json('contenido_snapshot');
            $table->char('contenido_hash', 64);
            $table->foreignId('solicitado_por_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('dispositivo_id')->nullable()->constrained('dispositivos')->restrictOnDelete();
            $table->dateTime('solicitado_at')->index();
            $table->dateTime('enviado_at')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamps();

            $table->index(
                ['recepcion_material_id', 'solicitado_at'],
                'trabajos_impresion_material_recepcion_fecha_idx',
            );
        });

        Schema::create('folios_trabajos_impresion_materiales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('trabajo_impresion_material_id');
            $table->foreign(
                'trabajo_impresion_material_id',
                'folios_trabajos_impresion_trabajo_fk',
            )
                ->references('id')
                ->on('trabajos_impresion_materiales')
                ->restrictOnDelete();
            $table->foreignUuid('folio_id')->constrained('folios')->restrictOnDelete();
            $table->string('numero_folio_snapshot', 64);
            $table->boolean('es_reimpresion')->default(false);
            $table->timestamps();

            $table->unique(
                ['trabajo_impresion_material_id', 'folio_id'],
                'folios_trabajos_impresion_material_unico',
            );
            $table->index(
                ['folio_id', 'created_at'],
                'folios_trabajos_impresion_material_folio_fecha_idx',
            );
        });

        DB::table('perfiles_impresion_etiquetas')->insert([
            'id' => (string) Str::uuid(),
            'codigo' => 'GEN-100X50-203',
            'nombre' => 'Etiqueta 100 × 50 mm · 203 dpi',
            'fabricante' => 'Genérico',
            'modelo' => null,
            'lenguaje' => 'zpl',
            'dpi' => 203,
            'ancho_mm' => 100,
            'alto_mm' => 50,
            'orientacion' => 'horizontal',
            'predeterminado' => true,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('folios_trabajos_impresion_materiales');
        Schema::dropIfExists('trabajos_impresion_materiales');
        Schema::dropIfExists('perfiles_impresion_etiquetas');
    }
};
