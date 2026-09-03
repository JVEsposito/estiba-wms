<?php

namespace App\Console\Commands;

use App\Services\Estiba\ServicioReservasTareasMovimiento;
use Illuminate\Console\Command;

class ExpirarReservasTareasMovimiento extends Command
{
    protected $signature = 'tareas:expirar-reservas {--limite=250}';

    protected $description = 'Libera leases vencidos de tareas y destinos operacionales';

    public function handle(ServicioReservasTareasMovimiento $servicio): int
    {
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);

        if ($limite === false) {
            $this->error('El límite debe ser un entero entre 1 y 1000.');

            return self::INVALID;
        }

        $expiradas = $servicio->expirarVencidas($limite);
        $this->info("Reservas expiradas: {$expiradas}.");

        return self::SUCCESS;
    }
}
