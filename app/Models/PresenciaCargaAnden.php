<?php

namespace App\Models;

use App\Enums\EstadoPresenciaCargaAnden;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'carga_id',
    'anden_id',
    'bloqueo_carga_id',
    'bloqueo_anden_id',
    'estado',
    'operacion_ingreso_id',
    'ingreso_payload_hash',
    'patente',
    'conductor',
    'observacion_ingreso',
    'ingresada_por_user_id',
    'ingresada_at',
    'operacion_salida_id',
    'salida_payload_hash',
    'motivo_finalizacion',
    'finalizada_por_user_id',
    'finalizada_at',
])]
class PresenciaCargaAnden extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'presencias_carga_anden';

    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }

    public function anden(): BelongsTo
    {
        return $this->belongsTo(Anden::class);
    }

    public function ingresadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ingresada_por_user_id');
    }

    public function finalizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalizada_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoPresenciaCargaAnden::class,
            'ingresada_at' => 'datetime',
            'finalizada_at' => 'datetime',
        ];
    }
}
