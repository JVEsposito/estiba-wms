<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pais_id', 'codigo', 'nombre', 'tipo', 'activo'])]
class Puerto extends Model
{
    use HasUuids;

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
