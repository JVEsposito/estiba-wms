<?php

namespace App\Models;

use App\Enums\EstadoReservaMaterial;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'detalle_despacho_material_id',
    'folio_id',
    'cantidad',
    'estado',
    'orden_fifo',
])]
class ReservaMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'reservas_materiales';

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(DetalleDespachoMaterial::class, 'detalle_despacho_material_id');
    }

    public function folioMaterial(): BelongsTo
    {
        return $this->belongsTo(FolioMaterial::class, 'folio_id');
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
