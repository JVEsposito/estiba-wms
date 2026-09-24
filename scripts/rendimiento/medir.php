<?php

use App\Models\OperacionSincronizacion;
use App\Models\Temporada;
use App\Services\Consultas\ServicioConsultaOperacional;
use App\Services\Consultas\ServicioTrazabilidadLotes;

// Mide las consultas críticas contra el banco de volumen. Uso: docs/operacion-rendimiento.md.
// Ejecutar con DB_DATABASE apuntando a la base de pruebas:
// php artisan tinker --execute="$(sed 1d scripts/rendimiento/medir.php)"

$medir = function (string $nombre, callable $fn, int $repeticiones = 3) {
    $fn(); // calentamiento
    $tiempos = [];
    foreach (range(1, $repeticiones) as $_) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $t = microtime(true);
        $fn();
        $tiempos[] = (microtime(true) - $t) * 1000;
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();
    }
    sort($tiempos);
    printf("%-46s %8.0f ms  (%d consultas)\n", $nombre, $tiempos[intdiv(count($tiempos), 2)], $consultas);
};
$buscar = app(ServicioConsultaOperacional::class);
$medir('buscar folio exacto PT-2026-0012345', fn () => $buscar->buscar('PT-2026-0012345', 'folios'));
$medir('buscar últimos dígitos 12345', fn () => $buscar->buscar('12345', 'folios'));
$medir('buscar texto SANTINA', fn () => $buscar->buscar('SANTINA', 'folios'));
$medir('buscar lote L-1234 (todos)', fn () => $buscar->buscar('L-1234', 'todos'));
$medir('buscar inexistente ZZZ-999', fn () => $buscar->buscar('ZZZ-999', 'todos'));
$ahora = now();
$inicio = now()->startOfDay();
$medir('operación ahora: última sincronización', fn () => OperacionSincronizacion::query()->where('recibida_servidor_at', '<=', $ahora)->orderByDesc('recibida_servidor_at')->orderByDesc('id')->first());
$medir('operación ahora: conteo del día', fn () => OperacionSincronizacion::query()->where('recibida_servidor_at', '>=', $inicio)->where('recibida_servidor_at', '<=', $ahora)->selectRaw('estado, COUNT(*) total')->groupBy('estado')->pluck('total', 'estado'));
$traza = app(ServicioTrazabilidadLotes::class);
$temporada = Temporada::where('activa', true)->first();
$medir('trazabilidad lote L-1234 (página 1)', fn () => $traza->consultar('L-1234', $temporada));
$medir('trazabilidad proceso P-77 (página 1)', fn () => $traza->consultar('P-77', $temporada));
