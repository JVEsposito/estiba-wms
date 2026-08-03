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
    'lote_materia_prima_id',
    'asignacion_camara_lote_id',
    'camara_id',
    'cantidad_envases',
    'kilos_enviados',
    'saldo_anterior',
    'saldo_posterior',
    'linea_proceso',
    'turno',
    'numero_orden',
    'observacion',
    'entregado_por_user_id',
    'dispositivo_id',
    'entregado_at',
    'operacion_anulacion_id',
    'anulado_por_user_id',
    'anulado_at',
    'motivo_anulacion',
])]
class EntregaFrutaProceso extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'entregas_fruta_proceso';

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteMateriaPrima::class, 'lote_materia_prima_id');
    }

    public function asignacionCamara(): BelongsTo
    {
        return $this->belongsTo(
            AsignacionCamaraLoteMateriaPrima::class,
            'asignacion_camara_lote_id',
        );
    }

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por_user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_user_id');
    }

    public function retornos(): HasMany
    {
        return $this->hasMany(RetornoPacking::class, 'entrega_fruta_proceso_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad_envases' => 'integer',
            'kilos_enviados' => 'decimal:3',
            'saldo_anterior' => 'integer',
            'saldo_posterior' => 'integer',
            'entregado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }
}
