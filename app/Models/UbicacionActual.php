<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['folio_id', 'posicion_id', 'movimiento_id', 'ubicado_at'])]
class UbicacionActual extends Model
{
    use HasUuids;

    protected $table = 'ubicaciones_actuales';

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function posicion(): BelongsTo
    {
        return $this->belongsTo(Posicion::class);
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(Movimiento::class);
    }

    protected function casts(): array
    {
        return ['ubicado_at' => 'datetime'];
    }
}
