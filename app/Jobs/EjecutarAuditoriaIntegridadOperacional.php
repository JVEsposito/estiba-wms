<?php

namespace App\Jobs;

use App\Enums\OrigenAuditoriaIntegridadOperacional;
use App\Models\User;
use App\Services\IntegridadOperacional\ServicioAuditoriaIntegridadOperacional;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EjecutarAuditoriaIntegridadOperacional implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 840;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 900;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120, 240];

    public function __construct(
        public readonly int $actorId,
    ) {}

    public function handle(ServicioAuditoriaIntegridadOperacional $servicio): void
    {
        $servicio->ejecutar(
            OrigenAuditoriaIntegridadOperacional::Manual,
            User::query()->find($this->actorId),
        );
    }

    public function uniqueId(): string
    {
        return 'integridad-operacional:manual';
    }
}
