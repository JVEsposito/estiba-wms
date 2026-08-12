<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['iso_alpha2', 'iso_alpha3', 'iso_numerico', 'nombre_es', 'es_iso_oficial', 'activo'])]
class Pais extends Model
{
    use HasUuids;

    protected $table = 'paises';

    public function puertos(): HasMany
    {
        return $this->hasMany(Puerto::class);
    }

    protected function casts(): array
    {
        return ['es_iso_oficial' => 'boolean', 'activo' => 'boolean'];
    }
}
