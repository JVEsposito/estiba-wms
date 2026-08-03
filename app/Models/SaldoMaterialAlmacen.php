<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'folio_id',
    'almacen_material_id',
    'cantidad_actual',
    'cantidad_reservada',
    'camara_id',
    'posicion_id',
    'version',
])]
class SaldoMaterialAlmacen extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'saldos_materiales_almacenes';

    public function folioMaterial(): BelongsTo
    {
        return $this->belongsTo(FolioMaterial::class, 'folio_id', 'folio_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(AlmacenMaterial::class, 'almacen_material_id');
    }

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function posicion(): BelongsTo
    {
        return $this->belongsTo(Posicion::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(ReservaMaterial::class, 'saldo_material_almacen_id');
    }

    public function cantidadDisponible(): float
    {
        return max(
            0,
            round(
                (float) $this->cantidad_actual - (float) $this->cantidad_reservada,
                3,
            ),
        );
    }

    protected function casts(): array
    {
        return [
            'cantidad_actual' => 'decimal:3',
            'cantidad_reservada' => 'decimal:3',
            'version' => 'integer',
        ];
    }
}
