<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'plataforma', 'activo', 'ultimo_acceso_at'])]
class Dispositivo extends Model
{
    use HasUuids;

    public function sesionesEstiba(): HasMany
    {
        return $this->hasMany(SesionEstiba::class);
    }

    public function operacionesSincronizacion(): HasMany
    {
        return $this->hasMany(OperacionSincronizacion::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultimo_acceso_at' => 'datetime',
        ];
    }
}
