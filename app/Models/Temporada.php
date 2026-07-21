<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function configuracionMaterial(): HasOne
    {
        return $this->hasOne(TemporadaMaterial::class);
    }

    public function recepcionesRomana(): HasMany
    {
        return $this->hasMany(RecepcionRomana::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }

    public function migracionesRecibidas(): HasMany
    {
        return $this->hasMany(MigracionTemporada::class, 'temporada_destino_id');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(ClienteValidacion::class);
    }

    public function especies(): HasMany
    {
        return $this->hasMany(EspecieValidacion::class);
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(CategoriaValidacion::class);
    }

    public function csg(): HasMany
    {
        return $this->hasMany(CsgValidacion::class);
    }

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
