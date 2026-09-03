<?php

namespace App\Models;

use App\Enums\EstadoReservaTareaMovimiento;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tarea_movimiento_id',
    'posicion_destino_id',
    'bloqueo_tarea_id',
    'bloqueo_posicion_id',
    'estado',
    'user_id',
    'dispositivo_id',
    'reservada_at',
    'renovada_at',
    'vence_at',
    'liberada_at',
    'expirada_at',
    'completada_at',
    'motivo_liberacion',
    'version',
])]
class ReservaTareaMovimiento extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'reservas_tareas_movimiento';

    public function tareaMovimiento(): BelongsTo
    {
        return $this->belongsTo(TareaMovimiento::class);
    }

    public function posicionDestino(): BelongsTo
    {
        return $this->belongsTo(Posicion::class, 'posicion_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoReservaTareaMovimiento::class,
            'reservada_at' => 'datetime',
            'renovada_at' => 'datetime',
            'vence_at' => 'datetime',
            'liberada_at' => 'datetime',
            'expirada_at' => 'datetime',
            'completada_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
