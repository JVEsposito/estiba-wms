<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CLAVE = 'despachos_materiales';

    public function up(): void
    {
        $ultimoHistorico = DB::table('despachos_materiales')
            ->where('codigo', 'like', 'MAT-DES-%')
            ->select('codigo')
            ->orderBy('codigo')
            ->cursor()
            ->reduce(
                fn (int $mayor, object $despacho): int => preg_match(
                    '/^MAT-DES-(\d+)$/',
                    $despacho->codigo,
                    $coincidencias,
                )
                    ? max($mayor, (int) $coincidencias[1])
                    : $mayor,
                0,
            );

        $ultimoConfigurado = DB::table('secuencias_documentos')
            ->where('clave', self::CLAVE)
            ->value('ultimo_numero');

        DB::table('secuencias_documentos')->updateOrInsert(
            ['clave' => self::CLAVE],
            ['ultimo_numero' => max((int) $ultimoConfigurado, $ultimoHistorico)],
        );
    }

    public function down(): void
    {
        DB::table('secuencias_documentos')
            ->where('clave', self::CLAVE)
            ->delete();
    }
};
