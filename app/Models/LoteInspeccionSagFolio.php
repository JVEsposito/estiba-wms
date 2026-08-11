<?php

namespace App\Models;

use App\Enums\EstadoFolioInspeccionSag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'lote_inspeccion_sag_id', 'folio_id', 'estado', 'estado_sag_anterior',
    'observacion', 'resuelto_por_user_id', 'resuelto_at',
])]
class LoteInspeccionSagFolio extends Model
{
    use HasUuids;

    protected $table = 'lotes_inspeccion_sag_folios';

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInspeccionSag::class, 'lote_inspeccion_sag_id');
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoDestinoInspeccionSag::class);
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoFolioInspeccionSag::class,
            'estado_sag_anterior' => 'array',
            'resuelto_at' => 'datetime',
        ];
    }
}
