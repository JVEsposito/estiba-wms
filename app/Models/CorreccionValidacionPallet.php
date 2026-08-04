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
    'validacion_pallet_id',
    'folio_id',
    'corregido_por_user_id',
    'datos_anteriores',
    'datos_nuevos',
    'motivo',
    'corregido_at',
])]
class CorreccionValidacionPallet extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'correcciones_validacion_pallet';

    public function validacion(): BelongsTo
    {
        return $this->belongsTo(ValidacionPallet::class, 'validacion_pallet_id');
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function corregidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corregido_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'datos_anteriores' => 'array',
            'datos_nuevos' => 'array',
            'corregido_at' => 'datetime',
        ];
    }
}
