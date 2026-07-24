<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lote_transformacion_material_id',
    'folio_id',
    'item_material_id',
    'cantidad_consumida',
    'cantidad_anterior',
    'cantidad_resultante',
    'siguio_fifo',
    'motivo_desviacion_fifo',
    'user_id',
    'dispositivo_id',
    'ocurrido_at',
])]
class ConsumoTransformacionMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'consumos_transformacion_materiales';

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteTransformacionMaterial::class, 'lote_transformacion_material_id');
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
            'cantidad_consumida' => 'decimal:3',
            'cantidad_anterior' => 'decimal:3',
            'cantidad_resultante' => 'decimal:3',
            'siguio_fifo' => 'boolean',
            'ocurrido_at' => 'datetime',
        ];
    }
}
