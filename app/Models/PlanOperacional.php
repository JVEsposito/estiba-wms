<?php

namespace App\Models;

use App\Enums\EstadoPlanOperacional;
use App\Enums\PrioridadOperacional;
use App\Enums\TipoPlanOperacional;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'temporada_id',
    'tipo',
    'estado',
    'prioridad',
    'titulo',
    'motivo',
    'referencia_tipo',
    'referencia_id',
    'contexto',
    'creado_por_user_id',
    'iniciado_por_user_id',
    'completado_por_user_id',
    'cancelado_por_user_id',
    'programado_at',
    'iniciado_at',
    'pausado_at',
    'completado_at',
    'cancelado_at',
    'motivo_cancelacion',
    'version',
])]
class PlanOperacional extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'planes_operacionales';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(TareaMovimiento::class)->orderBy('secuencia');
    }

    public function maniobras(): HasMany
    {
        return $this->hasMany(ManiobraOperacional::class)->orderBy('created_at');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function iniciadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoPlanOperacional::class,
            'estado' => EstadoPlanOperacional::class,
            'prioridad' => PrioridadOperacional::class,
            'contexto' => 'array',
            'programado_at' => 'datetime',
            'iniciado_at' => 'datetime',
            'pausado_at' => 'datetime',
            'completado_at' => 'datetime',
            'cancelado_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
