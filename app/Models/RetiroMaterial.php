<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'detalle_despacho_material_id',
    'operacion_retiro_material_id',
    'folio_id',
    'cantidad_anterior',
    'cantidad_retirada',
    'cantidad_resultante',
    'camara_id',
    'posicion_id',
    'user_id',
    'dispositivo_id',
    'siguio_fifo',
    'retirado_at',
])]
class RetiroMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'retiros_materiales';

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(DetalleDespachoMaterial::class, 'detalle_despacho_material_id');
    }

    public function operacion(): BelongsTo
    {
        return $this->belongsTo(OperacionRetiroMaterial::class, 'operacion_retiro_material_id');
    }

    public function folioMaterial(): BelongsTo
    {
        return $this->belongsTo(FolioMaterial::class, 'folio_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad_anterior' => 'decimal:3',
            'cantidad_retirada' => 'decimal:3',
            'cantidad_resultante' => 'decimal:3',
            'siguio_fifo' => 'boolean',
            'retirado_at' => 'datetime',
        ];
    }
}
