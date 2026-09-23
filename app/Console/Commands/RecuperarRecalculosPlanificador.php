<?php

namespace App\Console\Commands;

use App\Services\Planificador\ServicioRecalculosPendientesPlanificador;
use Illuminate\Console\Command;

final class RecuperarRecalculosPlanificador extends Command
{
    protected $signature = 'planificador:recuperar-proyecciones {--limite=200 : Máximo de fuentes pendientes por ejecución}';

    protected $description = 'Reenvía recálculos del planificador que todavía no tienen una proyección confirmada';

    public function handle(ServicioRecalculosPendientesPlanificador $recalculos): int
    {
        $limite = min(1000, max(1, (int) $this->option('limite')));
        $this->components->info("{$recalculos->recuperar($limite)} proyecciones pendientes revisadas.");

        return self::SUCCESS;
    }
}
