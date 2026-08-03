<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'operacion_id',
    'payload_hash',
    'numero',
    'entrega_fruta_proceso_id',
    'cierra_entrega',
    'observacion',
    'registrado_por_user_id',
    'dispositivo_id',
    'registrado_at',
    'operacion_anulacion_id',
    'anulado_por_user_id',
    'anulado_at',
    'motivo_anulacion',
])]
class RetornoPacking extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'retornos_packing';

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(EntregaFrutaProceso::class, 'entrega_fruta_proceso_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(SubloteRetornoPacking::class, 'retorno_packing_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'cierra_entrega' => 'boolean',
            'registrado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }
}
