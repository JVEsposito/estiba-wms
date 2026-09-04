<?php

namespace App\Models;

use App\Enums\EstadoLoteInspeccionSag;
use App\Enums\TipoLoteInspeccionSag;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'temporada_id', 'cliente_id', 'codigo', 'numero_correlativo', 'numero_inspeccion_sag',
    'operacion_id', 'payload_hash', 'tipo', 'estado', 'cantidad_solicitada',
    'referencia_correo', 'observacion', 'creado_por_user_id',
    'iniciado_por_user_id', 'finalizado_por_user_id', 'cancelado_por_user_id',
    'iniciado_at', 'finalizado_at', 'cancelado_at',
])]
class LoteInspeccionSag extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'lotes_inspeccion_sag';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function destinos(): HasMany
    {
        return $this->hasMany(DestinoLoteInspeccionSag::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(LoteInspeccionSagFolio::class);
    }

    public function planPreparacion(): HasOne
    {
        return $this->hasOne(PlanOperacional::class, 'referencia_id')
            ->where('referencia_tipo', 'lote_inspeccion_sag_preparacion');
    }

    public function reservasPreparacion(): HasMany
    {
        return $this->hasMany(ReservaPosicionInspeccionSag::class)
            ->orderBy('orden');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoLoteInspeccionSag::class,
            'estado' => EstadoLoteInspeccionSag::class,
            'cantidad_solicitada' => 'integer',
            'numero_correlativo' => 'integer',
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
            'cancelado_at' => 'datetime',
        ];
    }
}
