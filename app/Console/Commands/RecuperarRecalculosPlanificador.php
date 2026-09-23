<?php

namespace App\Console\Commands;

use App\Services\Planificador\ServicioRecalculosPendientesPlanificador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class RecuperarRecalculosPlanificador extends Command
{
    private const TIPOS = [
        ServicioRecalculosPendientesPlanificador::CARGA,
        ServicioRecalculosPendientesPlanificador::UBICACION,
        ServicioRecalculosPendientesPlanificador::SEGREGACION,
        ServicioRecalculosPendientesPlanificador::REORDENAMIENTO,
        ServicioRecalculosPendientesPlanificador::DESOCUPACION,
        ServicioRecalculosPendientesPlanificador::BUFFER_REPA,
    ];

    protected $signature = 'planificador:recuperar-proyecciones
        {--limite=200 : Máximo de fuentes por ejecución}
        {--tipo=todos : Tipo de proyección agotada que se desea reintentar; todos por defecto}
        {--reintentar-agotados : Reactiva manualmente las solicitudes agotadas tras corregir su causa}';

    protected $description = 'Reenvía recálculos del planificador que todavía no tienen una proyección confirmada';

    public function handle(ServicioRecalculosPendientesPlanificador $recalculos): int
    {
        $limite = min(1000, max(1, (int) $this->option('limite')));
        $tipoOpcion = $this->option('tipo');
        if ($tipoOpcion === null || $tipoOpcion === '') {
            $this->components->error('--tipo requiere un valor.');

            return self::FAILURE;
        }

        $tipo = $tipoOpcion === 'todos' ? null : $tipoOpcion;

        if ($tipo !== null && ! $this->option('reintentar-agotados')) {
            $this->components->error('--tipo requiere --reintentar-agotados.');

            return self::FAILURE;
        }

        if ($tipo !== null && ! in_array($tipo, self::TIPOS, true)) {
            $this->components->error('Tipo inválido. Tipos permitidos: '.implode(', ', self::TIPOS));

            return self::FAILURE;
        }

        if ($this->option('reintentar-agotados')) {
            if (config('queue.default') === 'sync') {
                $this->components->error('Configura una cola con worker antes de reintentar proyecciones agotadas.');

                return self::FAILURE;
            }

            $reactivados = $recalculos->reactivarAgotados($limite, $tipo);
            Log::notice('Reintento manual de proyecciones agotadas del planificador', [
                'tipo' => $tipo,
                'limite' => $limite,
                'reactivados' => $reactivados,
                'usuario_sistema' => getenv('SUDO_USER') ?: (getenv('USER') ?: 'desconocido'),
                'servidor' => gethostname() ?: 'desconocido',
            ]);
            $this->components->info("{$reactivados} proyecciones agotadas reactivadas.");
        }

        $this->components->info("{$recalculos->recuperar($limite, $tipo)} proyecciones pendientes revisadas.");

        return self::SUCCESS;
    }
}
