<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['especie_validacion_id', 'nombre', 'codigo_externo', 'activo'])]
class VariedadValidacion extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'variedades_validacion';

    public function especie(): BelongsTo
    {
        return $this->belongsTo(EspecieValidacion::class, 'especie_validacion_id');
    }

    public function csg(): BelongsToMany
    {
        return $this->belongsToMany(
            CsgValidacion::class,
            'csg_variedades_validacion',
            'variedad_validacion_id',
            'csg_validacion_id',
        )->withTimestamps();
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
