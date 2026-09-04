<?php

namespace App\Models;

use App\Enums\EstadoPosicion;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['camara_id', 'banda', 'posicion', 'nivel', 'etiqueta', 'estado'])]
class Posicion extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'posiciones';

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function ubicacionActual(): HasOne
    {
        return $this->hasOne(UbicacionActual::class);
    }

    public function ubicacionesActuales(): HasMany
    {
        return $this->hasMany(UbicacionActual::class);
    }

    public function movimientosOrigen(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'posicion_origen_id');
    }

    public function movimientosDestino(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'posicion_destino_id');
    }

    public function tareasMovimientoOrigen(): HasMany
    {
        return $this->hasMany(TareaMovimiento::class, 'posicion_origen_id');
    }

    public function tareasMovimientoDestino(): HasMany
    {
        return $this->hasMany(TareaMovimiento::class, 'posicion_destino_id');
    }

    public function reservaTareaActiva(): HasOne
    {
        return $this->hasOne(ReservaTareaMovimiento::class, 'bloqueo_posicion_id');
    }

    public function reservaPreparacionSagActiva(): HasOne
    {
        return $this->hasOne(ReservaPosicionInspeccionSag::class, 'clave_bloqueo');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoPosicion::class,
            'banda' => 'integer',
            'posicion' => 'integer',
            'nivel' => 'integer',
        ];
    }
}
