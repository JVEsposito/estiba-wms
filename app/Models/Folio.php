<?php

namespace App\Models;

use App\Enums\TipoBulto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'numero_folio',
    'tipo_bulto',
    'condicion_sag_id',
    'estado_operacional',
    'fecha_ingreso',
    'activo',
    'variedad',
    'calibre',
    'marca',
    'exportadora',
    'origen_sistema',
    'identificador_externo',
    'estado_integracion',
    'sincronizado_at',
    'datos_externos',
])]
class Folio extends Model
{
    use HasUuids;

    public function condicionSag(): BelongsTo
    {
        return $this->belongsTo(CondicionSag::class);
    }

    public function ubicacionActual(): HasOne
    {
        return $this->hasOne(UbicacionActual::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    protected function casts(): array
    {
        return [
            'tipo_bulto' => TipoBulto::class,
            'fecha_ingreso' => 'datetime',
            'activo' => 'boolean',
            'sincronizado_at' => 'datetime',
            'datos_externos' => 'array',
        ];
    }
}
