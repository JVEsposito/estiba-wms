<?php

namespace App\Console\Commands;

use App\Enums\RolUsuario;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Temporadas\Archivo\ServicioArchivoTemporada;
use DomainException;
use Illuminate\Console\Command;

final class ArchivarTemporada extends Command
{
    protected $signature = 'temporadas:archivar
        {temporada : Código o identificador de la temporada cerrada}
        {--administrador= : Correo del administrador que solicita el archivo}';

    protected $description = 'Genera y verifica el archivo completo de una temporada cerrada (no borra datos)';

    public function handle(ServicioArchivoTemporada $servicio): int
    {
        $temporada = Temporada::query()
            ->where('codigo', $this->argument('temporada'))
            ->orWhere('id', $this->argument('temporada'))
            ->first();
        if (! $temporada) {
            $this->components->error('No existe esa temporada.');

            return self::FAILURE;
        }

        $administrador = User::query()
            ->where('rol', RolUsuario::Administrador->value)
            ->where('activo', true)
            ->when($this->option('administrador'), fn ($consulta, string $correo) => $consulta->where('email', mb_strtolower($correo)))
            ->orderBy('id')
            ->first();
        if (! $administrador) {
            $this->components->error('Indica un administrador activo con --administrador=correo.');

            return self::FAILURE;
        }

        try {
            $archivo = $servicio->solicitar($temporada, $administrador);
        } catch (DomainException $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Archivando {$temporada->codigo}…");
        $archivo = $servicio->procesar($archivo);
        if ($archivo->estado !== ServicioArchivoTemporada::VERIFICADO) {
            $this->components->error('El archivo falló: '.$archivo->mensaje_error);

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Archivo verificado: %s (%s MB, %d tablas).',
            $archivo->ruta,
            number_format($archivo->tamano_bytes / 1048576, 1, ',', '.'),
            count($archivo->manifiesto['tablas'] ?? []),
        ));

        return self::SUCCESS;
    }
}
