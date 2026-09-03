<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicada = DB::table('planes_operacionales')
            ->select('referencia_tipo', 'referencia_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('referencia_tipo')
            ->whereNotNull('referencia_id')
            ->groupBy('referencia_tipo', 'referencia_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicada) {
            throw new RuntimeException(sprintf(
                'Existen planes operacionales duplicados para la referencia %s/%s. Regularice esos planes antes de aplicar la unicidad.',
                $duplicada->referencia_tipo,
                $duplicada->referencia_id,
            ));
        }

        Schema::table('planes_operacionales', function (Blueprint $table): void {
            $table->dropIndex('planes_referencia_idx');
            $table->unique(
                ['referencia_tipo', 'referencia_id'],
                'planes_referencia_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('planes_operacionales', function (Blueprint $table): void {
            $table->dropUnique('planes_referencia_unique');
            $table->index(
                ['referencia_tipo', 'referencia_id'],
                'planes_referencia_idx',
            );
        });
    }
};
