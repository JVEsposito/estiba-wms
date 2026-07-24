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
    'cantidad_producida',
    'es_salida_principal',
])]
class SalidaTransformacionMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'salidas_transformacion_materiales';

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
            'cantidad_producida' => 'decimal:3',
            'es_salida_principal' => 'boolean',
        ];
    }
}
