<?php

namespace App\Console\Commands;

use App\Services\Validacion\ServicioReparacionCatalogoValidacion;
use DomainException;
use Illuminate\Console\Command;

class RepararCatalogoValidacion extends Command
{
    protected $signature = 'validacion:reparar-catalogo';

    protected $description = 'Reconstruye artículos, orígenes y combinaciones de la temporada activa';

    public function handle(ServicioReparacionCatalogoValidacion $servicio): int
    {
        try {
            $resultado = $servicio->repararActiva();
        } catch (DomainException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $temporada = $resultado['temporada'];
        $this->info("Catálogo reparado: {$temporada->codigo} · {$temporada->nombre}");
        $this->table(
            ['Registro', 'Antes', 'Después'],
            [
                [
                    'Artículos',
                    $resultado['antes']['articulos'],
                    $resultado['despues']['articulos'],
                ],
                [
                    'Orígenes',
                    $resultado['antes']['origenes'],
                    $resultado['despues']['origenes'],
                ],
                [
                    'Combinaciones',
                    $resultado['antes']['combinaciones'],
                    $resultado['despues']['combinaciones'],
                ],
            ],
        );
        $this->line("Clientes globales reparados: {$resultado['clientes_reparados']}");
        $this->line("Versión resultante: {$temporada->version_catalogo}");

        return self::SUCCESS;
    }
}
