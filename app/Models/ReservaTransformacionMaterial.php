<?php

namespace App\Models;

use App\Enums\EstadoReservaMaterial;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'orden_transformacion_material_id',
    'folio_id',
    'item_material_id',
    'cantidad',
    'estado',
    'orden_fifo',
])]
class ReservaTransformacionMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'reservas_transformacion_materiales';

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTransformacionMaterial::class, 'orden_transformacion_material_id');
    }

    public function folioMaterial(): BelongsTo
    {
        return $this->belongsTo(FolioMaterial::class, 'folio_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaterial::class, 'item_material_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'estado' => EstadoReservaMaterial::class,
            'orden_fifo' => 'integer',
        ];
    }
}
