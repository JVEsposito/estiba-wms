<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'temporada_id',
    'especie_validacion_id',
    'variedad_validacion_id',
    'calibre_validacion_id',
    'envase_validacion_id',
    'especie',
    'variedad',
    'calibre',
    'envase',
    'codigo_externo',
    'activo',
])]
class ArticuloValidacion extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'articulos_validacion';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function especieCatalogo(): BelongsTo
    {
        return $this->belongsTo(EspecieValidacion::class, 'especie_validacion_id');
    }

    public function variedadCatalogo(): BelongsTo
    {
        return $this->belongsTo(VariedadValidacion::class, 'variedad_validacion_id');
    }

    public function calibreCatalogo(): BelongsTo
    {
        return $this->belongsTo(CalibreValidacion::class, 'calibre_validacion_id');
    }

    public function envaseCatalogo(): BelongsTo
    {
        return $this->belongsTo(EnvaseValidacion::class, 'envase_validacion_id');
    }

    public function combinaciones(): HasMany
    {
        return $this->hasMany(CombinacionValidacion::class);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
