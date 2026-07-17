<?php

namespace App\Services\Estiba;

use App\Enums\EstadoCamara;
use App\Enums\EstadoSesionEstiba;
use App\Exceptions\CamaraEnUso;
use App\Exceptions\OperacionNoAutorizada;
use App\Models\BloqueoCamara;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\SesionEstiba;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ServicioSesionEstiba
{
    public function __construct(
        private readonly AlcanceOperacionalUsuario $alcance,
    ) {}

    public function abrir(Camara $camara, User $usuario, Dispositivo $dispositivo): SesionEstiba
    {
        try {
            return DB::transaction(function () use ($camara, $usuario, $dispositivo): SesionEstiba {
                $camaraBloqueada = Camara::query()->lockForUpdate()->findOrFail($camara->id);
                $usuarioBloqueado = User::query()->lockForUpdate()->findOrFail($usuario->id);
                $dispositivoBloqueado = Dispositivo::query()
                    ->lockForUpdate()
                    ->findOrFail($dispositivo->id);

                $this->validarParticipantes(
                    $camaraBloqueada,
                    $usuarioBloqueado,
                    $dispositivoBloqueado,
                );

                $camaraOcupada = BloqueoCamara::query()
                    ->whereKey($camaraBloqueada->id)
                    ->exists()
                    || SesionEstiba::query()
                        ->where('camara_id', $camaraBloqueada->id)
                        ->where('estado', EstadoSesionEstiba::Abierta->value)
                        ->exists();

                if ($camaraOcupada) {
                    throw new CamaraEnUso('La cámara ya está siendo modificada por otra sesión.');
                }

                $sesion = SesionEstiba::create([
                    'camara_id' => $camaraBloqueada->id,
                    'user_id' => $usuarioBloqueado->id,
                    'dispositivo_id' => $dispositivoBloqueado->id,
                    'estado' => EstadoSesionEstiba::Abierta,
                    'version_inicial' => $camaraBloqueada->version_plano,
                    'iniciada_at' => now(),
                    'ultima_actividad_at' => now(),
                ]);

                BloqueoCamara::create([
                    'camara_id' => $camaraBloqueada->id,
                    'sesion_estiba_id' => $sesion->id,
                    'adquirido_at' => now(),
                ]);

                return $sesion->load('bloqueo');
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw new CamaraEnUso(
                'La cámara ya está siendo modificada por otra sesión.',
                previous: $exception,
            );
        }
    }

    public function cerrar(
        SesionEstiba $sesion,
        User $usuario,
        ?string $motivo = null,
    ): SesionEstiba {
        return DB::transaction(function () use ($sesion, $usuario, $motivo): SesionEstiba {
            $sesionBloqueada = SesionEstiba::query()->lockForUpdate()->findOrFail($sesion->id);
            $camara = Camara::query()->lockForUpdate()->findOrFail($sesionBloqueada->camara_id);
            $usuarioBloqueado = User::query()->lockForUpdate()->findOrFail($usuario->id);

            if ($sesionBloqueada->estado !== EstadoSesionEstiba::Abierta) {
                throw new DomainException('La sesión de estiba ya se encuentra cerrada.');
            }

            if (! $usuarioBloqueado->activo
                || $sesionBloqueada->user_id !== $usuarioBloqueado->id) {
                throw new OperacionNoAutorizada(
                    'El cierre normal solo puede realizarlo el dueño de la sesión.',
                );
            }

            $bloqueo = BloqueoCamara::query()
                ->where('camara_id', $camara->id)
                ->where('sesion_estiba_id', $sesionBloqueada->id)
                ->lockForUpdate()
                ->first();

            if (! $bloqueo) {
                throw new DomainException('La sesión no posee el bloqueo de la cámara.');
            }

            $sesionBloqueada->update([
                'estado' => EstadoSesionEstiba::Cerrada,
                'version_final' => $camara->version_plano,
                'cerrada_at' => now(),
                'ultima_actividad_at' => now(),
                'cierre_forzado_por_user_id' => null,
                'motivo_cierre' => $motivo,
            ]);

            $bloqueo->delete();

            return $sesionBloqueada->refresh();
        }, attempts: 3);
    }

    public function cerrarForzosamente(
        SesionEstiba $sesion,
        User $usuario,
        string $motivo,
    ): SesionEstiba {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < 3) {
            throw new DomainException('El cierre forzoso requiere un motivo válido.');
        }

        return DB::transaction(function () use ($sesion, $usuario, $motivo): SesionEstiba {
            $sesionBloqueada = SesionEstiba::query()->lockForUpdate()->findOrFail($sesion->id);
            $camara = Camara::query()->lockForUpdate()->findOrFail($sesionBloqueada->camara_id);
            $usuarioBloqueado = User::query()->lockForUpdate()->findOrFail($usuario->id);

            if ($sesionBloqueada->estado !== EstadoSesionEstiba::Abierta) {
                throw new DomainException('La sesión de estiba ya se encuentra cerrada.');
            }

            if (! $this->alcance->puedeCerrarSesionForzosamente($usuarioBloqueado, $camara)) {
                throw new OperacionNoAutorizada(
                    'El usuario no puede cerrar forzosamente sesiones de esta área.',
                );
            }

            $bloqueo = BloqueoCamara::query()
                ->where('camara_id', $camara->id)
                ->where('sesion_estiba_id', $sesionBloqueada->id)
                ->lockForUpdate()
                ->first();

            if (! $bloqueo) {
                throw new DomainException('La sesión no posee el bloqueo de la cámara.');
            }

            $sesionBloqueada->update([
                'estado' => EstadoSesionEstiba::CierreForzado,
                'version_final' => $camara->version_plano,
                'cerrada_at' => now(),
                'ultima_actividad_at' => now(),
                'cierre_forzado_por_user_id' => $usuarioBloqueado->id,
                'motivo_cierre' => $motivo,
            ]);

            $bloqueo->delete();

            return $sesionBloqueada->refresh();
        }, attempts: 3);
    }

    private function validarParticipantes(
        Camara $camara,
        User $usuario,
        Dispositivo $dispositivo,
    ): void {
        if ($camara->estado !== EstadoCamara::Activa) {
            throw new DomainException('La cámara no se encuentra activa.');
        }

        if (! $this->alcance->puedeOperarCamara($usuario, $camara)) {
            throw new OperacionNoAutorizada(
                'El usuario no está autorizado para operar esta cámara.',
            );
        }

        if (! $dispositivo->activo) {
            throw new OperacionNoAutorizada('El dispositivo no se encuentra activo.');
        }
    }
}
