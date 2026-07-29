<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despachos_materiales', function (Blueprint $table): void {
            $table->index(
                ['temporada_id', 'created_at'],
                'despachos_materiales_temporada_fecha_idx',
            );
            $table->index(
                ['temporada_id', 'estado', 'created_at'],
                'despachos_materiales_temporada_estado_fecha_idx',
            );
        });

        Schema::table('movimientos_inventario_materiales', function (Blueprint $table): void {
            $table->index(
                ['folio_id', 'ocurrido_at'],
                'movimientos_inventario_folio_fecha_idx',
            );
            $table->index(
                ['item_material_id', 'ocurrido_at'],
                'movimientos_inventario_item_fecha_idx',
            );
        });

        Schema::table('movimientos', function (Blueprint $table): void {
            $table->index(
                ['camara_origen_id', 'created_at'],
                'movimientos_camara_origen_fecha_idx',
            );
            $table->index(
                ['camara_destino_id', 'created_at'],
                'movimientos_camara_destino_fecha_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table): void {
            $table->dropIndex('movimientos_camara_origen_fecha_idx');
            $table->dropIndex('movimientos_camara_destino_fecha_idx');
        });

        Schema::table('movimientos_inventario_materiales', function (Blueprint $table): void {
            $table->dropIndex('movimientos_inventario_folio_fecha_idx');
            $table->dropIndex('movimientos_inventario_item_fecha_idx');
        });

        Schema::table('despachos_materiales', function (Blueprint $table): void {
            $table->dropIndex('despachos_materiales_temporada_fecha_idx');
            $table->dropIndex('despachos_materiales_temporada_estado_fecha_idx');
        });
    }
};
