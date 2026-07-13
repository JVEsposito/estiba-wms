<?php

namespace App\Models;

use App\Enums\EstadoCamara;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['codigo', 'nombre', 'tipo', 'estado', 'version_plano'])]
class Camara extends Model
{
    use HasUuids;

    public function posiciones(): HasMany
    {
        return $this->hasMany(Posicion::class);
    }

    public function sesionesEstiba(): HasMany
    {
        return $this->hasMany(SesionEstiba::class);
    }

    public function bloqueo(): HasOne
    {
        return $this->hasOne(BloqueoCamara::class);
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoCamara::class,
            'version_plano' => 'integer',
        ];
    }
}
