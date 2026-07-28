<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('envases_validacion', function (Blueprint $table) {
            $table->foreignUuid('cliente_validacion_id')
                ->nullable()
                ->after('especie_validacion_id')
                ->constrained('clientes_validacion')
                ->restrictOnDelete();
            $table->unique(
                ['especie_validacion_id', 'cliente_validacion_id', 'nombre'],
                'envase_validacion_especie_cliente_nombre_unique',
            );
        });
        Schema::table('envases_validacion', function (Blueprint $table) {
            $table->dropUnique('envase_validacion_especie_nombre_unique');
        });

        Schema::table('articulos_validacion', function (Blueprint $table) {
            $table->foreignUuid('cliente_validacion_id')
                ->nullable()
                ->after('temporada_id')
                ->constrained('clientes_validacion')
                ->restrictOnDelete();
            $table->unique(
                [
                    'temporada_id',
                    'cliente_validacion_id',
                    'especie',
                    'variedad',
                    'calibre',
                    'envase',
                ],
                'articulo_validacion_cliente_unique',
            );
        });
        Schema::table('articulos_validacion', function (Blueprint $table) {
            $table->dropUnique('articulo_validacion_unique');
        });
    }

    public function down(): void
    {
        Schema::table('articulos_validacion', function (Blueprint $table) {
            $table->unique(
                ['temporada_id', 'especie', 'variedad', 'calibre', 'envase'],
                'articulo_validacion_unique',
            );
        });
        Schema::table('articulos_validacion', function (Blueprint $table) {
            $table->dropUnique('articulo_validacion_cliente_unique');
            $table->dropForeign(['cliente_validacion_id']);
            $table->dropColumn('cliente_validacion_id');
        });

        Schema::table('envases_validacion', function (Blueprint $table) {
            $table->unique(
                ['especie_validacion_id', 'nombre'],
                'envase_validacion_especie_nombre_unique',
            );
        });
        Schema::table('envases_validacion', function (Blueprint $table) {
            $table->dropUnique('envase_validacion_especie_cliente_nombre_unique');
            $table->dropForeign(['cliente_validacion_id']);
            $table->dropColumn('cliente_validacion_id');
        });
    }
};
