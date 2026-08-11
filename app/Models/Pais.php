<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['iso_alpha2', 'iso_alpha3', 'iso_numerico', 'nombre_es', 'es_iso_oficial', 'activo'])]
class Pais extends Model
{
    use HasUuids;

    protected $table = 'paises';

    protected function casts(): array
    {
        return ['es_iso_oficial' => 'boolean', 'activo' => 'boolean'];
    }
}
