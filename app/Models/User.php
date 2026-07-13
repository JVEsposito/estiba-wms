<?php

namespace App\Models;

use App\Enums\RolUsuario;
use App\Models\Concerns\ImpideEliminacionFisica;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

#[Fillable(['name', 'email', 'password', 'rol', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, ImpideEliminacionFisica, Notifiable;

    public function crearTokenParaDispositivo(
        Dispositivo $dispositivo,
        string $nombre,
        array $habilidades = ['*'],
        ?DateTimeInterface $expiraEn = null,
    ): NewAccessToken {
        if (! static::query()->whereKey($this->id)->where('activo', true)->exists()) {
            throw new DomainException('Un usuario inactivo no puede crear tokens.');
        }

        if (! Dispositivo::query()
            ->whereKey($dispositivo->id)
            ->where('activo', true)
            ->exists()) {
            throw new DomainException('Un dispositivo inactivo no puede recibir tokens.');
        }

        return DB::transaction(function () use (
            $dispositivo,
            $nombre,
            $habilidades,
            $expiraEn,
        ): NewAccessToken {
            $nuevoToken = $this->createToken($nombre, $habilidades, $expiraEn);
            $nuevoToken->accessToken->update([
                'dispositivo_id' => $dispositivo->id,
            ]);

            return $nuevoToken;
        });
    }

    public function sesionesEstiba(): HasMany
    {
        return $this->hasMany(SesionEstiba::class);
    }

    public function sesionesCerradasForzosamente(): HasMany
    {
        return $this->hasMany(SesionEstiba::class, 'cierre_forzado_por_user_id');
    }

    public function operacionesSincronizacion(): HasMany
    {
        return $this->hasMany(OperacionSincronizacion::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => RolUsuario::class,
            'activo' => 'boolean',
        ];
    }
}
