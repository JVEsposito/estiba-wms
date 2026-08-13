<?php

namespace App\Services\IntegridadOperacional;

use App\Enums\SeveridadHallazgoIntegridadOperacional;

final readonly class HallazgoIntegridadDetectado
{
    /**
     * @param  array<string, mixed>  $contexto
     */
    public function __construct(
        public string $reglaCodigo,
        public SeveridadHallazgoIntegridadOperacional $severidad,
        public string $modulo,
        public string $entidadTipo,
        public ?string $entidadId,
        public ?string $referencia,
        public string $titulo,
        public string $detalle,
        public array $contexto = [],
    ) {}

    public function huella(): string
    {
        return hash('sha256', implode('|', [
            $this->reglaCodigo,
            $this->entidadTipo,
            $this->entidadId ?? '',
            $this->referencia ?? '',
        ]));
    }
}
