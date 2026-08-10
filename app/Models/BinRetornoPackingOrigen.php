<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bin_retorno_packing_id',
    'lote_materia_prima_id',
    'numero_lote',
    'numero_orden',
    'linea_proceso',
    'turno',
    'clave_proceso',
    'kilos_aportados',
])]
class BinRetornoPackingOrigen extends Model
{
    use HasUuids;

    protected $table = 'bin_retorno_packing_origenes';

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinRetornoPacking::class, 'bin_retorno_packing_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteMateriaPrima::class, 'lote_materia_prima_id');
    }

    protected function casts(): array
    {
        return [
            'kilos_aportados' => 'decimal:3',
        ];
    }
}
