<?php

namespace App\Console\Commands;

use App\Services\Planificador\ServicioRecalculosPendientesPlanificador;
use Illuminate\Console\Command;

final class RecuperarRecalculosPlanificador extends Command
{
    protected $signature = 'planificador:recuperar-proyecciones
        {--limite=200 : Máximo de fuentes por ejecución}
        {--reintentar-agotados : Reactiva manualmente las solicitudes agotadas tras corregir su causa}';

    protected $description = 'Reenvía recálculos del planificador que todavía no tienen una proyección confirmada';

    public function handle(ServicioRecalculosPendientesPlanificador $recalculos): int
    {
        $limite = min(1000, max(1, (int) $this->option('limite')));

        if ($this->option('reintentar-agotados')) {
            if (config('queue.default') === 'sync') {
                $this->components->error('Configura una cola con worker antes de reintentar proyecciones agotadas.');

                return self::FAILURE;
            }

            $reactivados = $recalculos->reactivarAgotados($limite);
            $this->components->info("{$reactivados} proyecciones agotadas reactivadas.");
        }

        $this->components->info("{$recalculos->recuperar($limite)} proyecciones pendientes revisadas.");

        return self::SUCCESS;
    }
}
