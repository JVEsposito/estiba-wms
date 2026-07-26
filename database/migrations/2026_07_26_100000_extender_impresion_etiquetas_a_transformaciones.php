<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajos_impresion_materiales', function (Blueprint $table): void {
            $table->dropForeign(['recepcion_material_id']);
        });

        Schema::table('trabajos_impresion_materiales', function (Blueprint $table): void {
            $table->uuid('recepcion_material_id')->nullable()->change();
            $table->string('origen', 24)->default('recepcion')->after('payload_hash')->index();
            $table->uuid('orden_transformacion_material_id')
                ->nullable()
                ->after('recepcion_material_id');
            $table->foreign(
                'orden_transformacion_material_id',
                'trabajos_impresion_orden_fk',
            )
                ->references('id')
                ->on('ordenes_transformacion_materiales')
                ->restrictOnDelete();
            $table->uuid('lote_transformacion_material_id')
                ->nullable()
                ->after('orden_transformacion_material_id');
            $table->foreign(
                'lote_transformacion_material_id',
                'trabajos_impresion_lote_fk',
            )
                ->references('id')
                ->on('lotes_transformacion_materiales')
                ->restrictOnDelete();
            $table->foreign('recepcion_material_id')
                ->references('id')
                ->on('recepciones_materiales')
                ->restrictOnDelete();
            $table->index(
                ['orden_transformacion_material_id', 'solicitado_at'],
                'trabajos_impresion_material_orden_fecha_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('trabajos_impresion_materiales', function (Blueprint $table): void {
            $table->dropIndex('trabajos_impresion_material_orden_fecha_idx');
            $table->dropForeign('trabajos_impresion_lote_fk');
            $table->dropForeign('trabajos_impresion_orden_fk');
            $table->dropForeign(['recepcion_material_id']);
            $table->dropColumn([
                'lote_transformacion_material_id',
                'orden_transformacion_material_id',
                'origen',
            ]);
        });

        Schema::table('trabajos_impresion_materiales', function (Blueprint $table): void {
            $table->uuid('recepcion_material_id')->nullable(false)->change();
            $table->foreign('recepcion_material_id')
                ->references('id')
                ->on('recepciones_materiales')
                ->restrictOnDelete();
        });
    }
};
