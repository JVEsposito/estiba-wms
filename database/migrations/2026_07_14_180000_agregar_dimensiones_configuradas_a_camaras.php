<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camaras', function (Blueprint $table) {
            $table->unsignedSmallInteger('cantidad_bandas')->default(1)->after('version_plano');
            $table->unsignedSmallInteger('posiciones_por_banda')->default(1)->after('cantidad_bandas');
            $table->unsignedSmallInteger('cantidad_niveles')->default(1)->after('posiciones_por_banda');
        });

        DB::table('camaras')
            ->select('id')
            ->orderBy('id')
            ->each(function (object $camara): void {
                $dimensiones = DB::table('posiciones')
                    ->where('camara_id', $camara->id)
                    ->selectRaw('COALESCE(MAX(banda), 1) as bandas')
                    ->selectRaw('COALESCE(MAX(posicion), 1) as posiciones')
                    ->selectRaw('COALESCE(MAX(nivel), 1) as niveles')
                    ->first();

                DB::table('camaras')
                    ->where('id', $camara->id)
                    ->update([
                        'cantidad_bandas' => $dimensiones->bandas,
                        'posiciones_por_banda' => $dimensiones->posiciones,
                        'cantidad_niveles' => $dimensiones->niveles,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('camaras', function (Blueprint $table) {
            $table->dropColumn([
                'cantidad_bandas',
                'posiciones_por_banda',
                'cantidad_niveles',
            ]);
        });
    }
};
