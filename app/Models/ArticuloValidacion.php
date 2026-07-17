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

    public function combinaciones(): HasMany
    {
        return $this->hasMany(CombinacionValidacion::class);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
