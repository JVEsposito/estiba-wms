<?php

namespace App\Models;

use App\Enums\EstadoPosicion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['camara_id', 'fila', 'profundidad', 'nivel', 'etiqueta', 'estado'])]
class Posicion extends Model
{
    use HasUuids;

    protected $table = 'posiciones';

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function ubicacionActual(): HasOne
    {
        return $this->hasOne(UbicacionActual::class);
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoPosicion::class,
            'profundidad' => 'integer',
            'nivel' => 'integer',
        ];
    }
}
