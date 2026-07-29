<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->sincronizar('camaras', 'camaras', 'CAM-');
        $this->sincronizar('tuneles_prefrio', 'tuneles_prefrio', 'TUN-');
    }

    public function down(): void
    {
        DB::table('secuencias_documentos')
            ->whereIn('clave', ['camaras', 'tuneles_prefrio'])
            ->delete();
    }

    private function sincronizar(string $clave, string $tabla, string $prefijo): void
    {
        $patron = '/^'.preg_quote($prefijo, '/').'(\d+)$/';
        $ultimoHistorico = DB::table($tabla)
            ->where('codigo', 'like', "{$prefijo}%")
            ->select('codigo')
            ->orderBy('codigo')
            ->cursor()
            ->reduce(
                fn (int $mayor, object $registro): int => preg_match(
                    $patron,
                    $registro->codigo,
                    $coincidencias,
                )
                    ? max($mayor, (int) $coincidencias[1])
                    : $mayor,
                0,
            );
        $ultimoConfigurado = DB::table('secuencias_documentos')
            ->where('clave', $clave)
            ->value('ultimo_numero');

        DB::table('secuencias_documentos')->updateOrInsert(
            ['clave' => $clave],
            ['ultimo_numero' => max((int) $ultimoConfigurado, $ultimoHistorico)],
        );
    }
};
