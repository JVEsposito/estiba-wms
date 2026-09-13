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
    'snapshot_version',
    'capacidad_ejecucion',
    'frontera_max',
    'contexto',
])]
class CicloArbitrajeManiobras extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'ciclos_arbitraje_maniobras';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function decisiones(): HasMany
    {
        return $this->hasMany(DecisionArbitrajeManiobra::class, 'ciclo_arbitraje_id')
            ->orderBy('orden');
    }

    protected function casts(): array
    {
        return [
            'capacidad_ejecucion' => 'integer',
            'frontera_max' => 'integer',
            'contexto' => 'array',
        ];
    }
}
