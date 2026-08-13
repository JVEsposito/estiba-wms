<?php

namespace App\Console\Commands;

use App\Enums\OrigenAuditoriaIntegridadOperacional;
use App\Services\IntegridadOperacional\ServicioAuditoriaIntegridadOperacional;
use Illuminate\Console\Command;
use ValueError;

class AuditarIntegridadOperacional extends Command
{
    protected $signature = 'folios:auditar-integridad
                            {--origen=consola : consola o programada}';

    protected $description = 'Detecta contradicciones operacionales sin modificar folios ni procesos';

    public function handle(ServicioAuditoriaIntegridadOperacional $servicio): int
    {
        try {
            $origen = OrigenAuditoriaIntegridadOperacional::from(
                (string) $this->option('origen'),
            );
        } catch (ValueError) {
            $this->error('El origen debe ser consola o programada.');

            return self::INVALID;
        }

        if ($origen === OrigenAuditoriaIntegridadOperacional::Manual) {
            $this->error('El origen manual está reservado para la oficina administrativa.');

            return self::INVALID;
        }

        $auditoria = $servicio->ejecutar($origen);

        $this->info("Auditoría {$auditoria->id} completada en {$auditoria->duracion_ms} ms.");
        $this->table(
            ['Activos', 'Críticos', 'Advertencias', 'Nuevos', 'Resueltos'],
            [[
                $auditoria->hallazgos_activos,
                $auditoria->hallazgos_criticos,
                $auditoria->hallazgos_advertencia,
                $auditoria->hallazgos_nuevos,
                $auditoria->hallazgos_resueltos,
            ]],
        );

        return self::SUCCESS;
    }
}
