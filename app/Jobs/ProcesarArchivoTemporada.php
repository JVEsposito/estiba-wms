<?php

namespace App\Jobs;

use App\Models\ArchivoTemporada;
use App\Services\Temporadas\Archivo\ServicioArchivoTemporada;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Genera y verifica el archivo de una temporada fuera de la petición HTTP. Un solo intento:
 * si falla, el archivo queda «fallido» con su motivo y el administrador lo vuelve a pedir.
 * Para temporadas muy grandes existe el comando temporadas:archivar, sin límite de tiempo.
 */
class ProcesarArchivoTemporada implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 840;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 900;

    public function __construct(public readonly string $archivoId) {}

    public function handle(ServicioArchivoTemporada $servicio): void
    {
        $archivo = ArchivoTemporada::query()->find($this->archivoId);
        if ($archivo && $archivo->estado === ServicioArchivoTemporada::EN_PROCESO) {
            $servicio->procesar($archivo);
        }
    }

    public function failed(?\Throwable $error): void
    {
        ArchivoTemporada::query()
            ->whereKey($this->archivoId)
            ->where('estado', ServicioArchivoTemporada::EN_PROCESO)
            ->update([
                'estado' => ServicioArchivoTemporada::FALLIDO,
                'mensaje_error' => 'El proceso en cola no terminó: '.mb_substr((string) $error?->getMessage(), 0, 500).'. Use php artisan temporadas:archivar para temporadas muy grandes.',
                'updated_at' => now(),
            ]);
    }

    public function uniqueId(): string
    {
        return 'archivo-temporada:'.$this->archivoId;
    }
}
