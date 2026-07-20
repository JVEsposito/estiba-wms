<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['temporada_id', 'codigo', 'predio', 'codigo_externo', 'activo'])]
class CsgValidacion extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'csg_validacion';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function variedades(): BelongsToMany
    {
        return $this->belongsToMany(
            VariedadValidacion::class,
            'csg_variedades_validacion',
            'csg_validacion_id',
            'variedad_validacion_id',
        )->withTimestamps();
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
