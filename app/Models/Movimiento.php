<?php

namespace App\Models;

use App\Enums\TipoMovimiento;
use App\Models\Concerns\ImpideEliminacionFisica;
use App\Services\Estiba\ValidadorMovimiento;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    'advertencias_confirmadas',
    'version_origen_anterior',
    'version_origen_resultante',
    'version_destino_anterior',
    'version_destino_resultante',
    'generado_dispositivo_at',
    'recibido_servidor_at',
])]
class Movimiento extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(
            fn (Movimiento $movimiento) => app(ValidadorMovimiento::class)->validar($movimiento),
        );

        static::updating(function (): never {
            throw new DomainException(
                'Los movimientos son inalterables; registre una reversión o un nuevo movimiento.',
            );
        });
    }

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

    public function ubicacionActual(): HasOne
    {
        return $this->hasOne(UbicacionActual::class);
    }

    protected function casts(): array
    {
        return [
            'tipo_movimiento' => TipoMovimiento::class,
            'advertencias_confirmadas' => 'array',
            'generado_dispositivo_at' => 'datetime',
            'recibido_servidor_at' => 'datetime',
            'version_origen_anterior' => 'integer',
            'version_origen_resultante' => 'integer',
            'version_destino_anterior' => 'integer',
            'version_destino_resultante' => 'integer',
        ];
    }
}
