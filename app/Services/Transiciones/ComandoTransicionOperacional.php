<?php

namespace App\Services\Transiciones;

use App\Enums\DominioTransicionOperacional;
use App\Models\Dispositivo;
use App\Models\User;
use DateTimeInterface;

final readonly class ComandoTransicionOperacional
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public DominioTransicionOperacional $dominio,
        public string $tipo,
        public User $usuario,
        public array $payload = [],
        public ?string $operacionId = null,
        public ?Dispositivo $dispositivo = null,
        public ?string $sujetoTipo = null,
        public ?string $sujetoId = null,
        public ?string $referencia = null,
        public ?DateTimeInterface $ocurridoAt = null,
    ) {}
}
