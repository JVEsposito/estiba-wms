<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lotes_inspeccion_sag')
            ->where('tipo', 'segregacion')
            ->update(['tipo' => 'inspeccion_origen']);
    }

    public function down(): void
    {
        DB::table('lotes_inspeccion_sag')
            ->where('tipo', 'inspeccion_origen')
            ->update(['tipo' => 'segregacion']);
    }
};
