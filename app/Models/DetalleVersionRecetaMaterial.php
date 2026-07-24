<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'version_receta_material_id',
    'item_entrada_id',
    'cantidad_estandar',
    'unidad_medida',
    'es_componente_principal',
    'factor_conversion',
    'merma_estandar_porcentaje',
    'tolerancia_porcentaje',
])]
class DetalleVersionRecetaMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'detalles_versiones_recetas_materiales';

    public function versionReceta(): BelongsTo
    {
        return $this->belongsTo(VersionRecetaMaterial::class, 'version_receta_material_id');
    }

    public function itemEntrada(): BelongsTo
    {
        return $this->belongsTo(ItemMaterial::class, 'item_entrada_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad_estandar' => 'decimal:3',
            'es_componente_principal' => 'boolean',
            'factor_conversion' => 'decimal:6',
            'merma_estandar_porcentaje' => 'decimal:4',
            'tolerancia_porcentaje' => 'decimal:4',
        ];
    }
}
