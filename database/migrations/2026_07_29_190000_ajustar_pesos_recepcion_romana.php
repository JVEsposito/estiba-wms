<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->decimal('peso_bruto', 12, 3)->nullable()->change();
            $table->boolean('salida_sin_envases')
                ->default(false)
                ->after('peso_neto');
            $table->decimal('peso_tara_envases', 12, 3)
                ->nullable()
                ->after('salida_sin_envases');
        });

        Schema::table('detalles_envases_recepcion_romana', function (Blueprint $table): void {
            $table->decimal('tara_unitaria_salida', 10, 3)
                ->nullable()
                ->after('cantidad_validada');
        });
    }

    public function down(): void
    {
        Schema::table('detalles_envases_recepcion_romana', function (Blueprint $table): void {
            $table->dropColumn('tara_unitaria_salida');
        });

        DB::table('recepciones_romana')
            ->whereNull('peso_bruto')
            ->update(['peso_bruto' => 0]);

        Schema::table('recepciones_romana', function (Blueprint $table): void {
            $table->dropColumn(['salida_sin_envases', 'peso_tara_envases']);
            $table->decimal('peso_bruto', 12, 3)->nullable(false)->change();
        });
    }
};
