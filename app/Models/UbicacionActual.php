<?php

namespace App\Models;

use App\Observers\ReplanificarConcentracionUbicacionObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([ReplanificarConcentracionUbicacionObserver::class])]
#[Fillable(['folio_id', 'camara_id', 'posicion_id', 'movimiento_id', 'ubicado_at'])]
class UbicacionActual extends Model
{
    use HasUuids;

    protected $table = 'ubicaciones_actuales';

    protected static function booted(): void
    {
        static::creating(function (UbicacionActual $ubicacion): void {
            if ($ubicacion->camara_id || ! $ubicacion->posicion_id) {
                return;
            }

            $ubicacion->camara_id = Posicion::query()
                ->whereKey($ubicacion->posicion_id)
                ->value('camara_id');
        });
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function posicion(): BelongsTo
    {
        return $this->belongsTo(Posicion::class);
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(Movimiento::class);
    }

    protected function casts(): array
    {
        return ['ubicado_at' => 'datetime'];
    }
}
