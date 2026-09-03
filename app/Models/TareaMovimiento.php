<?php

namespace App\Models;

use App\Enums\EstadoTareaMovimiento;
use App\Enums\PrioridadOperacional;
use App\Enums\TipoMovimiento;
use App\Models\Concerns\ImpideEliminacionFisica;
use App\Observers\CerrarRecepcionTunelObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy([CerrarRecepcionTunelObserver::class])]
#[Fillable([
    'plan_operacional_id',
    'secuencia',
    'tipo_movimiento',
    'estado',
    'prioridad',
    'folio_id',
    'camara_origen_id',
    'posicion_origen_id',
    'camara_destino_id',
    'posicion_destino_id',
    'responsable_user_id',
    'dispositivo_id',
    'instruccion',
    'contexto',
    'asumida_at',
    'iniciada_at',
    'completada_at',
    'cancelada_at',
    'reemplazada_por_tarea_id',
    'cancelada_por_user_id',
    'motivo_cancelacion',
    'version',
])]
class TareaMovimiento extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'tareas_movimiento';

    public function planOperacional(): BelongsTo
    {
        return $this->belongsTo(PlanOperacional::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function camaraOrigen(): BelongsTo
    {
        return $this->belongsTo(Camara::class, 'camara_origen_id');
    }

    public function posicionOrigen(): BelongsTo
    {
        return $this->belongsTo(Posicion::class, 'posicion_origen_id');
    }

    public function camaraDestino(): BelongsTo
    {
        return $this->belongsTo(Camara::class, 'camara_destino_id');
    }

    public function posicionDestino(): BelongsTo
    {
        return $this->belongsTo(Posicion::class, 'posicion_destino_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(ReservaTareaMovimiento::class);
    }

    public function reservaActiva(): HasOne
    {
        return $this->hasOne(ReservaTareaMovimiento::class, 'bloqueo_tarea_id');
    }

    public function reemplazadaPor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reemplazada_por_tarea_id');
    }

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelada_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'secuencia' => 'integer',
            'tipo_movimiento' => TipoMovimiento::class,
            'estado' => EstadoTareaMovimiento::class,
            'prioridad' => PrioridadOperacional::class,
            'contexto' => 'array',
            'asumida_at' => 'datetime',
            'iniciada_at' => 'datetime',
            'completada_at' => 'datetime',
            'cancelada_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
