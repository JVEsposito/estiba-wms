<?php

namespace App\Services\Estiba;

use App\Enums\EstadoCamara;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoPosicion;
use App\Enums\EstadoSesionEstiba;
use App\Enums\RolUsuario;
use App\Enums\TipoMovimiento;
use App\Exceptions\OperacionNoAutorizada;
use App\Models\BloqueoCamara;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Movimiento;
use App\Models\OperacionSincronizacion;
use App\Models\Posicion;
use App\Models\SesionEstiba;
use App\Models\User;
use DomainException;

class ValidadorMovimiento
{
    public function validar(Movimiento $movimiento): void
    {
        $tipo = $movimiento->tipo_movimiento;

        if (! $tipo instanceof TipoMovimiento) {
            throw new DomainException('El tipo de movimiento no es válido.');
        }

        $this->validarActorYOperacion($movimiento, $tipo);
        $this->validarFolio($movimiento);
        $this->validarEstructura($movimiento, $tipo);

        if ($movimiento->camara_origen_id !== null) {
            $this->validarExtremo($movimiento, 'origen', false);
        }

        if ($movimiento->camara_destino_id !== null) {
            $this->validarExtremo($movimiento, 'destino', true);
        }

        if ($tipo === TipoMovimiento::Reubicacion) {
            if ($movimiento->camara_origen_id !== $movimiento->camara_destino_id) {
                throw new DomainException('Una reubicación debe permanecer en la misma cámara.');
            }

            if ($movimiento->sesion_origen_id !== $movimiento->sesion_destino_id) {
                throw new DomainException('Una reubicación debe utilizar la misma sesión de estiba.');
            }
        }

        if ($tipo === TipoMovimiento::TrasladoEntreCamaras
            && $movimiento->camara_origen_id === $movimiento->camara_destino_id) {
            throw new DomainException('Un traslado debe utilizar cámaras diferentes.');
        }

        if ($movimiento->posicion_origen_id !== null
            && $movimiento->posicion_origen_id === $movimiento->posicion_destino_id) {
            throw new DomainException('El origen y el destino deben ser posiciones diferentes.');
        }
    }

    private function validarActorYOperacion(
        Movimiento $movimiento,
        TipoMovimiento $tipo,
    ): void {
        $usuarioActivo = User::query()
            ->whereKey($movimiento->user_id)
            ->where('activo', true)
            ->where('rol', '!=', RolUsuario::Consulta->value)
            ->exists();
        $dispositivoActivo = Dispositivo::query()
            ->whereKey($movimiento->dispositivo_id)
            ->where('activo', true)
            ->exists();
        $operacion = OperacionSincronizacion::query()->find($movimiento->operacion_id);

        if (! $usuarioActivo || ! $dispositivoActivo) {
            throw new OperacionNoAutorizada(
                'El usuario o el dispositivo no se encuentra autorizado.',
            );
        }

        if (! $operacion
            || $operacion->user_id !== $movimiento->user_id
            || $operacion->dispositivo_id !== $movimiento->dispositivo_id
            || $operacion->tipo !== $tipo->value) {
            throw new DomainException(
                'La operación de sincronización no corresponde al movimiento.',
            );
        }
    }

    private function validarFolio(Movimiento $movimiento): void
    {
        $folio = Folio::query()->find($movimiento->folio_id);

        if (! $folio?->activo
            || $folio->estado_operacional !== EstadoOperacionalFolio::Disponible) {
            throw new DomainException('El folio no se encuentra disponible para movimientos.');
        }
    }

    private function validarEstructura(Movimiento $movimiento, TipoMovimiento $tipo): void
    {
        $origenCompleto = $this->extremoCompleto($movimiento, 'origen');
        $destinoCompleto = $this->extremoCompleto($movimiento, 'destino');
        $origenVacio = $this->extremoVacio($movimiento, 'origen');
        $destinoVacio = $this->extremoVacio($movimiento, 'destino');

        $valido = match ($tipo) {
            TipoMovimiento::UbicacionInicial => $origenVacio && $destinoCompleto,
            TipoMovimiento::Reubicacion,
            TipoMovimiento::TrasladoEntreCamaras => $origenCompleto && $destinoCompleto,
            TipoMovimiento::Retiro => $origenCompleto && $destinoVacio,
            TipoMovimiento::Reversion => ($origenCompleto || $origenVacio)
                && ($destinoCompleto || $destinoVacio)
                && ! ($origenVacio && $destinoVacio)
                && filled($movimiento->motivo),
        };

        if (! $valido) {
            throw new DomainException('La combinación de origen y destino no corresponde al tipo de movimiento.');
        }
    }

    private function extremoCompleto(Movimiento $movimiento, string $extremo): bool
    {
        return $movimiento->{"camara_{$extremo}_id"} !== null
            && $movimiento->{"posicion_{$extremo}_id"} !== null
            && $movimiento->{"sesion_{$extremo}_id"} !== null;
    }

    private function extremoVacio(Movimiento $movimiento, string $extremo): bool
    {
        return $movimiento->{"camara_{$extremo}_id"} === null
            && $movimiento->{"posicion_{$extremo}_id"} === null
            && $movimiento->{"sesion_{$extremo}_id"} === null;
    }

    private function validarExtremo(
        Movimiento $movimiento,
        string $extremo,
        bool $esDestino,
    ): void {
        $camaraId = $movimiento->{"camara_{$extremo}_id"};
        $posicionId = $movimiento->{"posicion_{$extremo}_id"};
        $sesionId = $movimiento->{"sesion_{$extremo}_id"};

        $camara = Camara::query()->find($camaraId);
        $posicion = Posicion::query()->find($posicionId);
        $sesion = SesionEstiba::query()->find($sesionId);

        if (! $camara || $camara->estado !== EstadoCamara::Activa) {
            throw new DomainException("La cámara de {$extremo} no se encuentra activa.");
        }

        if (! $posicion || $posicion->camara_id !== $camara->id) {
            throw new DomainException("La posición de {$extremo} no pertenece a la cámara indicada.");
        }

        if ($esDestino && $posicion->estado !== EstadoPosicion::Activa) {
            throw new DomainException('La posición de destino no se encuentra activa.');
        }

        if (! $sesion
            || $sesion->camara_id !== $camara->id
            || $sesion->estado !== EstadoSesionEstiba::Abierta
            || $sesion->user_id !== $movimiento->user_id
            || $sesion->dispositivo_id !== $movimiento->dispositivo_id) {
            throw new DomainException("La sesión de {$extremo} no autoriza este movimiento.");
        }

        $poseeBloqueo = BloqueoCamara::query()
            ->where('camara_id', $camara->id)
            ->where('sesion_estiba_id', $sesion->id)
            ->exists();

        if (! $poseeBloqueo) {
            throw new DomainException("La sesión de {$extremo} no posee el bloqueo de la cámara.");
        }

        $versionAnterior = $movimiento->{"version_{$extremo}_anterior"};
        $versionResultante = $movimiento->{"version_{$extremo}_resultante"};

        if ($versionAnterior === null
            || $versionResultante === null
            || $versionResultante !== $versionAnterior + 1) {
            throw new DomainException(
                "Las versiones de {$extremo} deben representar un incremento unitario.",
            );
        }
    }
}
