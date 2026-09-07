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
    'registro_control_ambiental_id',
    'corregido_por_user_id',
    'datos_anteriores',
    'datos_nuevos',
    'motivo',
    'corregido_at',
])]
class CorreccionControlAmbiental extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'correcciones_control_ambiental';

    public function registro(): BelongsTo
    {
        return $this->belongsTo(
            RegistroControlAmbiental::class,
            'registro_control_ambiental_id',
        );
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
            'corregido_at' => 'immutable_datetime',
        ];
    }
}
