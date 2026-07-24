<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'detalle_recepcion_material_id',
    'cantidad',
    'lote_proveedor',
    'fecha_fabricacion',
    'fecha_vencimiento',
    'bloqueado',
    'motivo_bloqueo',
])]
class BultoRecepcionMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'bultos_recepciones_materiales';

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(DetalleRecepcionMaterial::class, 'detalle_recepcion_material_id');
    }

    public function folioMaterial(): HasOne
    {
        return $this->hasOne(FolioMaterial::class, 'bulto_recepcion_material_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'fecha_fabricacion' => 'date',
            'fecha_vencimiento' => 'date',
            'bloqueado' => 'boolean',
        ];
    }
}
