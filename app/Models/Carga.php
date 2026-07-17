<?php

namespace App\Models;

use App\Enums\EstadoCarga;
use App\Enums\PrioridadCarga;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'numero_orden_externa',
    'estado',
    'prioridad',
    'camara_objetivo_id',
    'anden_previsto_id',
    'observacion',
    'version',
    'creada_por_user_id',
    'actualizada_por_user_id',
    'publicada_por_user_id',
    'publicada_at',
    'cancelada_por_user_id',
    'cancelada_at',
    'operacion_cierre_id',
    'cierre_payload_hash',
    'patente',
    'conductor',
    'observacion_cierre',
    'cerrada_por_user_id',
    'cerrada_at',
])]
class Carga extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    public function camaraObjetivo(): BelongsTo
    {
        return $this->belongsTo(Camara::class, 'camara_objetivo_id');
    }

    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por_user_id');
    }

    public function andenPrevisto(): BelongsTo
    {
        return $this->belongsTo(Anden::class, 'anden_previsto_id');
    }

    public function actualizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizada_por_user_id');
    }

    public function publicadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicada_por_user_id');
    }

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelada_por_user_id');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por_user_id');
    }

    public function asignacionesActuales(): HasMany
    {
        return $this->hasMany(CargaFolio::class)
            ->whereHas('reservaActiva')
            ->orderBy('asignado_at');
    }

    public function asignacionesHistoricas(): HasMany
    {
        return $this->hasMany(CargaFolio::class)
            ->orderBy('asignado_at');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(TareaCarga::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoCarga::class)
            ->orderBy('created_at');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoCarga::class,
            'prioridad' => PrioridadCarga::class,
            'version' => 'integer',
            'publicada_at' => 'datetime',
            'cancelada_at' => 'datetime',
            'cerrada_at' => 'datetime',
        ];
    }
}
