<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'operacion_id',
    'payload_hash',
    'validacion_pallet_id',
    'folio_id',
    'numero_folio',
    'motivo_categoria',
    'motivo',
    'anulado_por_user_id',
    'anulado_at',
    'snapshot',
])]
class AnulacionValidacionPallet extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'anulaciones_validacion_pallet';

    public function validacion(): BelongsTo
    {
        return $this->belongsTo(ValidacionPallet::class, 'validacion_pallet_id');
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'anulado_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }
}
