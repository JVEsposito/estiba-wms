<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Índices medidos con una temporada de 150.000 folios PT y 500.000 operaciones
 * sincronizadas (docs/operacion-rendimiento.md). Solo agregan índices: no modifican datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Operación ahora consulta la última sincronización y las del día en cada refresco.
        Schema::table('operaciones_sincronizacion', function (Blueprint $table) {
            $table->index('recibida_servidor_at', 'operaciones_sincronizacion_recibida_idx');
        });

        // Búsqueda por fragmento del número (p. ej. últimos dígitos) dentro de la temporada,
        // ordenada por ingreso: el índice cubre la consulta sin leer las filas.
        Schema::table('folios', function (Blueprint $table) {
            $table->index(['temporada_id', 'numero_folio', 'fecha_ingreso'], 'folios_temporada_numero_ingreso_idx');
        });
    }

    public function down(): void
    {
        Schema::table('folios', function (Blueprint $table) {
            $table->dropIndex('folios_temporada_numero_ingreso_idx');
        });
        Schema::table('operaciones_sincronizacion', function (Blueprint $table) {
            $table->dropIndex('operaciones_sincronizacion_recibida_idx');
        });
    }
};
