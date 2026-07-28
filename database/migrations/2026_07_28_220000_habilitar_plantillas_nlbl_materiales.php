<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajos_impresion_materiales', function (Blueprint $table): void {
            $table->string('simbologia', 16)
                ->default('code128')
                ->after('formato');
        });

        $perfiles = [
            [
                'codigo' => 'BIX-SLP-TX400-203',
                'nombre' => 'BIXOLON SLP-TX400 · 100 × 50 mm',
                'fabricante' => 'Bixolon',
                'modelo' => 'SLP-TX400',
                'lenguaje' => 'bpl-z',
            ],
            [
                'codigo' => 'ZEB-ZT231-203',
                'nombre' => 'Zebra ZT231 · 100 × 50 mm',
                'fabricante' => 'Zebra',
                'modelo' => 'ZT231',
                'lenguaje' => 'zpl',
            ],
        ];

        foreach ($perfiles as $perfil) {
            if (DB::table('perfiles_impresion_etiquetas')
                ->where('codigo', $perfil['codigo'])
                ->exists()) {
                continue;
            }

            DB::table('perfiles_impresion_etiquetas')->insert([
                'id' => (string) Str::uuid(),
                ...$perfil,
                'dpi' => 203,
                'ancho_mm' => 100,
                'alto_mm' => 50,
                'orientacion' => 'horizontal',
                'predeterminado' => false,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('perfiles_impresion_etiquetas')
            ->whereIn('codigo', ['BIX-SLP-TX400-203', 'ZEB-ZT231-203'])
            ->whereNotIn(
                'id',
                DB::table('trabajos_impresion_materiales')
                    ->whereNotNull('perfil_impresion_etiqueta_id')
                    ->select('perfil_impresion_etiqueta_id'),
            )
            ->delete();

        Schema::table('trabajos_impresion_materiales', function (Blueprint $table): void {
            $table->dropColumn('simbologia');
        });
    }
};
