<?php

namespace App\Jobs;

use App\Services\Planificador\ServicioRecalculosPendientesPlanificador;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class EjecutarRecalculoPendientePlanificador implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = ServicioRecalculosPendientesPlanificador::MAX_INTENTOS_FALLIDOS;

    public int $timeout = 90;

    public int $uniqueFor = 120;

    /** @var array<int, int> */
    public array $backoff = [2, 5, 15, 30];

    public function __construct(
        public readonly string $tipo,
        public readonly string $fuenteId,
    ) {}

    public function handle(ServicioRecalculosPendientesPlanificador $servicio): void
    {
        $servicio->ejecutar($this->tipo, $this->fuenteId);
    }

    public function uniqueId(): string
    {
        return "planificador:{$this->tipo}:{$this->fuenteId}";
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))
            ->releaseAfter(5)
            ->expireAfter($this->timeout + 30)];
    }
}
