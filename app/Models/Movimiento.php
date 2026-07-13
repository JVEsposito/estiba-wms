<?php

namespace App\Models;

use App\Enums\TipoMovimiento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operacion_id',
    'folio_id',
    'tipo_movimiento',
    'camara_origen_id',
    'posicion_origen_id',
    'camara_destino_id',
    'posicion_destino_id',
    'sesion_origen_id',
    'sesion_destino_id',
    'user_id',
    'dispositivo_id',
    'motivo',
    'version_origen_anterior',
    'version_origen_resultante',
    'version_destino_anterior',
    'version_destino_resultante',
    'generado_dispositivo_at',
    'recibido_servidor_at',
])]
class Movimiento extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public function operacion(): BelongsTo
    {
        return $this->belongsTo(OperacionSincronizacion::class, 'operacion_id');
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
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

    public function sesionOrigen(): BelongsTo
    {
        return $this->belongsTo(SesionEstiba::class, 'sesion_origen_id');
    }

    public function sesionDestino(): BelongsTo
    {
        return $this->belongsTo(SesionEstiba::class, 'sesion_destino_id');
    }

    protected function casts(): array
    {
        return [
            'tipo_movimiento' => TipoMovimiento::class,
            'generado_dispositivo_at' => 'datetime',
            'recibido_servidor_at' => 'datetime',
        ];
    }
}
