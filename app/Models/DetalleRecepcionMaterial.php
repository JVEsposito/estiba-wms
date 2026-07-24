<?php

namespace App\Models;

use App\Enums\CategoriaOperacionalMaterial;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'recepcion_material_id',
    'item_material_id',
    'categoria_operacional',
    'unidad_medida',
    'cantidad_documental',
    'cantidad_recibida',
    'cantidad_rechazada',
    'observacion',
])]
class DetalleRecepcionMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'detalles_recepciones_materiales';

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(RecepcionMaterial::class, 'recepcion_material_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaterial::class, 'item_material_id');
    }

    public function bultos(): HasMany
    {
        return $this->hasMany(BultoRecepcionMaterial::class, 'detalle_recepcion_material_id');
    }

    protected function casts(): array
    {
        return [
            'categoria_operacional' => CategoriaOperacionalMaterial::class,
            'cantidad_documental' => 'decimal:3',
            'cantidad_recibida' => 'decimal:3',
            'cantidad_rechazada' => 'decimal:3',
        ];
    }
}
