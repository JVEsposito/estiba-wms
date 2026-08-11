<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['codigo', 'nombre', 'descripcion', 'activo'])]
class BloqueMercado extends Model
{
    use HasUuids;

    protected $table = 'bloques_mercado';

    public function paises(): BelongsToMany
    {
        return $this->belongsToMany(Pais::class, 'bloque_mercado_pais')
            ->withPivot(['vigente_desde', 'vigente_hasta'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
