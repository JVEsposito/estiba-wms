<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operacion_id',
    'payload_hash',
    'retorno_packing_id',
    'bin_retorno_packing_id',
    'accion',
    'motivo',
    'registrado_por_user_id',
    'registrado_at',
])]
class RegularizacionRetornoPackingLegacy extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'regularizaciones_retorno_packing_legacy';

    public function retorno(): BelongsTo
    {
        return $this->belongsTo(RetornoPacking::class, 'retorno_packing_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinRetornoPacking::class, 'bin_retorno_packing_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'registrado_at' => 'datetime',
        ];
    }
}
