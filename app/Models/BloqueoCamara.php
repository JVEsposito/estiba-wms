<?php

namespace App\Models;

use App\Enums\EstadoSesionEstiba;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['camara_id', 'sesion_estiba_id', 'adquirido_at'])]
class BloqueoCamara extends Model
{
    protected $table = 'bloqueos_camara';

    protected $primaryKey = 'camara_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (BloqueoCamara $bloqueo): void {
            $sesion = SesionEstiba::query()->find($bloqueo->sesion_estiba_id);

            if (! $sesion
                || $sesion->camara_id !== $bloqueo->camara_id
                || $sesion->estado !== EstadoSesionEstiba::Abierta) {
                throw new DomainException(
                    'El bloqueo debe corresponder a una sesión abierta de la misma cámara.',
                );
            }
        });

        static::updating(function (): never {
            throw new DomainException('Un bloqueo de cámara no puede reasignarse.');
        });
    }

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function sesionEstiba(): BelongsTo
    {
        return $this->belongsTo(SesionEstiba::class);
    }

    protected function casts(): array
    {
        return ['adquirido_at' => 'datetime'];
    }
}
