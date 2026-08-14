<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folios', function (Blueprint $table): void {
            $table->index(
                [
                    'activo',
                    'tipo_bulto',
                    'condicion_termica',
                    'habilitacion_almacenamiento',
                    'fecha_ingreso',
                ],
                'folios_elegibles_prefrio_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('folios', function (Blueprint $table): void {
            $table->dropIndex('folios_elegibles_prefrio_idx');
        });
    }
};
