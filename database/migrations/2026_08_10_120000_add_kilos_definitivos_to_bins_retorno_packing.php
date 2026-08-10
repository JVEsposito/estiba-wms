<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bins_retorno_packing', function (Blueprint $table): void {
            $table->decimal('kilos_totales_definitivos', 12, 3)
                ->nullable()
                ->after('kilos_totales');
        });

        Schema::table('bin_retorno_packing_origenes', function (Blueprint $table): void {
            $table->decimal('kilos_aportados_definitivos', 12, 3)
                ->nullable()
                ->after('kilos_aportados');
        });

        // Los registros cerrados antes de esta mejora no distinguían peso verde y definitivo.
        // Se conserva su estado histórico tomando como definitivo el único peso disponible.
        DB::table('bins_retorno_packing')
            ->whereNotNull('regularizado_at')
            ->update(['kilos_totales_definitivos' => DB::raw('kilos_totales')]);

        DB::statement(<<<'SQL'
            UPDATE bin_retorno_packing_origenes AS origenes
            INNER JOIN bins_retorno_packing AS bins
                ON bins.id = origenes.bin_retorno_packing_id
            SET origenes.kilos_aportados_definitivos = origenes.kilos_aportados
            WHERE bins.regularizado_at IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        Schema::table('bin_retorno_packing_origenes', function (Blueprint $table): void {
            $table->dropColumn('kilos_aportados_definitivos');
        });

        Schema::table('bins_retorno_packing', function (Blueprint $table): void {
            $table->dropColumn('kilos_totales_definitivos');
        });
    }
};
