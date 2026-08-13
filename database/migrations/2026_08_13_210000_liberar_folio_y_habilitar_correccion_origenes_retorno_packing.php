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
            $table->string('folio_definitivo_vigente', 80)
                ->nullable()
                ->after('folio_definitivo');
        });

        DB::table('bins_retorno_packing')
            ->whereNull('anulado_at')
            ->whereNotNull('folio_definitivo')
            ->update(['folio_definitivo_vigente' => DB::raw('folio_definitivo')]);

        Schema::table('bins_retorno_packing', function (Blueprint $table): void {
            $table->unique(
                'folio_definitivo_vigente',
                'bins_retorno_packing_folio_definitivo_vigente_unique',
            );
            $table->dropUnique('bins_retorno_packing_folio_definitivo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bins_retorno_packing', function (Blueprint $table): void {
            $table->dropUnique('bins_retorno_packing_folio_definitivo_vigente_unique');
            $table->dropColumn('folio_definitivo_vigente');
            $table->unique('folio_definitivo');
        });
    }
};
