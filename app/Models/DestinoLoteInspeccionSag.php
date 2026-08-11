<?php

namespace App\Models;

use App\Enums\TipoDestinoSag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lote_inspeccion_sag_id', 'tipo_destino', 'pais_id', 'bloque_mercado_id',
    'destino_snapshot', 'miembros_snapshot',
])]
class DestinoLoteInspeccionSag extends Model
{
    use HasUuids;

    protected $table = 'destinos_lote_inspeccion_sag';

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInspeccionSag::class, 'lote_inspeccion_sag_id');
    }

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(BloqueMercado::class, 'bloque_mercado_id');
    }

    protected function casts(): array
    {
        return [
            'tipo_destino' => TipoDestinoSag::class,
            'destino_snapshot' => 'array',
            'miembros_snapshot' => 'array',
        ];
    }
}
