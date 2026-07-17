<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'temporada_id',
    'articulo_validacion_id',
    'origen_validacion_id',
    'codigo_externo',
    'activo',
])]
class CombinacionValidacion extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'combinaciones_validacion';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(ArticuloValidacion::class, 'articulo_validacion_id');
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(OrigenValidacion::class, 'origen_validacion_id');
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
