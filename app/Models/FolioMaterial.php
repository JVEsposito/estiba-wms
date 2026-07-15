<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'folio_id',
    'item_material_id',
    'cantidad_inicial',
    'cantidad_actual',
    'cantidad_reservada',
    'unidad_medida',
    'lote',
    'proveedor',
    'observacion',
])]
class FolioMaterial extends Model
{
    use ImpideEliminacionFisica;

    protected $table = 'folios_materiales';

    protected $primaryKey = 'folio_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class, 'folio_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaterial::class, 'item_material_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(ReservaMaterial::class, 'folio_id');
    }

    public function retiros(): HasMany
    {
        return $this->hasMany(RetiroMaterial::class, 'folio_id');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventarioMaterial::class, 'folio_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad_inicial' => 'decimal:3',
            'cantidad_actual' => 'decimal:3',
            'cantidad_reservada' => 'decimal:3',
        ];
    }
}
