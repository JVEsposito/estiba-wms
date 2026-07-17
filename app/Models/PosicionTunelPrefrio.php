<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tunel_prefrio_id',
    'numero',
    'etiqueta',
    'activa',
])]
class PosicionTunelPrefrio extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'posiciones_tunel_prefrio';

    public function tunel(): BelongsTo
    {
        return $this->belongsTo(TunelPrefrio::class, 'tunel_prefrio_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(ProcesoPrefrioFolio::class);
    }

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'activa' => 'boolean',
        ];
    }
}
