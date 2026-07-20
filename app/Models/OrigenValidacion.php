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
    'cliente_validacion_id',
    'marca_validacion_id',
    'csg_validacion_id',
    'cliente',
    'marca',
    'csg',
    'predio',
    'codigo_externo',
    'activo',
])]
class OrigenValidacion extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'origenes_validacion';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function clienteCatalogo(): BelongsTo
    {
        return $this->belongsTo(ClienteValidacion::class, 'cliente_validacion_id');
    }

    public function marcaCatalogo(): BelongsTo
    {
        return $this->belongsTo(MarcaValidacion::class, 'marca_validacion_id');
    }

    public function csgCatalogo(): BelongsTo
    {
        return $this->belongsTo(CsgValidacion::class, 'csg_validacion_id');
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
