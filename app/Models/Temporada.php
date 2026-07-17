<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'nombre',
    'fecha_inicio',
    'fecha_fin',
    'activa',
    'version_catalogo',
])]
class Temporada extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    public function articulos(): HasMany
    {
        return $this->hasMany(ArticuloValidacion::class);
    }

    public function origenes(): HasMany
    {
        return $this->hasMany(OrigenValidacion::class);
    }

    public function combinaciones(): HasMany
    {
        return $this->hasMany(CombinacionValidacion::class);
    }

    public function importaciones(): HasMany
    {
        return $this->hasMany(ImportacionValidacion::class);
    }

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'activa' => 'boolean',
            'version_catalogo' => 'integer',
        ];
    }
}
