<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes_operacionales', function (Blueprint $table): void {
            $table->unsignedInteger('ciclo_referencia')->default(1);
            $table->unique(
                ['referencia_tipo', 'referencia_id', 'ciclo_referencia'],
                'planes_referencia_ciclo_unique',
            );
        });

        Schema::table('planes_operacionales', function (Blueprint $table): void {
            $table->dropUnique('planes_referencia_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('planes_operacionales')->where('ciclo_referencia', '>', 1)->exists()) {
            throw new RuntimeException(
                'Existen ciclos históricos de planes operacionales; no se puede revertir su esquema sin perder trazabilidad.',
            );
        }

        Schema::table('planes_operacionales', function (Blueprint $table): void {
            $table->unique(['referencia_tipo', 'referencia_id'], 'planes_referencia_unique');
        });

        Schema::table('planes_operacionales', function (Blueprint $table): void {
            $table->dropUnique('planes_referencia_ciclo_unique');
            $table->dropColumn('ciclo_referencia');
        });
    }
};
