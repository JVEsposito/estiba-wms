<?php

namespace App\Services\Transiciones;

use App\Models\TransicionOperacional;
use Closure;

class ContextoEjecucionTransicionOperacional
{
    /**
     * @var array<int, array{transicion: TransicionOperacional, secuencia: int}>
     */
    private array $pila = [];

    public function ejecutar(TransicionOperacional $transicion, Closure $accion): mixed
    {
        $this->pila[] = [
            'transicion' => $transicion,
            'secuencia' => 0,
        ];

        try {
            return $accion();
        } finally {
            array_pop($this->pila);
        }
    }

    public function actual(): ?TransicionOperacional
    {
        $indice = array_key_last($this->pila);

        return $indice === null ? null : $this->pila[$indice]['transicion'];
    }

    public function siguienteSecuencia(): ?int
    {
        $indice = array_key_last($this->pila);
        if ($indice === null) {
            return null;
        }

        return ++$this->pila[$indice]['secuencia'];
    }
}
