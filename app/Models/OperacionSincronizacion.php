<?php

namespace App\Models;

use App\Enums\EstadoOperacionSincronizacion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'id',
    'user_id',
    'dispositivo_id',
    'tipo',
    'estado',
    'payload_hash',
    'payload',
    'resultado',
    'codigo_error',
    'mensaje_error',
    'versiones_conocidas',
    'versiones_resultantes',
    'generada_dispositivo_at',
    'recibida_servidor_at',
    'procesada_at',
])]
class OperacionSincronizacion extends Model
{
    use HasUuids;

    protected $table = 'operaciones_sincronizacion';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function movimiento(): HasOne
    {
        return $this->hasOne(Movimiento::class, 'operacion_id');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoOperacionSincronizacion::class,
            'payload' => 'array',
            'resultado' => 'array',
            'versiones_conocidas' => 'array',
            'versiones_resultantes' => 'array',
            'generada_dispositivo_at' => 'datetime',
            'recibida_servidor_at' => 'datetime',
            'procesada_at' => 'datetime',
        ];
    }
}
