<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones_operacionales', function (Blueprint $table): void {
            $table->foreignUuid('despacho_material_id')
                ->nullable()
                ->after('carga_id')
                ->constrained('despachos_materiales')
                ->restrictOnDelete();
            $table->index(
                ['despacho_material_id', 'created_at'],
                'notificaciones_despacho_material_fecha_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones_operacionales', function (Blueprint $table): void {
            $table->dropIndex('notificaciones_despacho_material_fecha_idx');
            $table->dropConstrainedForeignId('despacho_material_id');
        });
    }
};
