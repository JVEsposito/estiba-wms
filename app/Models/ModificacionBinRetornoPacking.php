<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bin_retorno_packing_id',
    'operacion_id',
    'payload_hash',
    'datos_anteriores',
    'datos_nuevos',
    'motivo',
    'modificado_por_user_id',
    'modificado_at',
])]
class ModificacionBinRetornoPacking extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'modificaciones_bin_retorno_packing';

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinRetornoPacking::class, 'bin_retorno_packing_id');
    }

    public function modificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modificado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'datos_anteriores' => 'array',
            'datos_nuevos' => 'array',
            'modificado_at' => 'datetime',
        ];
    }
}
