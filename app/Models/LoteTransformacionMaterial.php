<?php

namespace App\Models;

use App\Enums\EstadoLoteTransformacionMaterial;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'orden_transformacion_material_id',
    'numero_lote',
    'estado',
    'cantidad_planificada_salida',
    'cantidad_real_salida',
    'salida_teorica',
    'merma_estandar',
    'merma_real',
    'desviacion_merma',
    'iniciado_por_user_id',
    'cerrado_por_user_id',
    'iniciado_at',
    'cerrado_at',
])]
class LoteTransformacionMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'lotes_transformacion_materiales';

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTransformacionMaterial::class, 'orden_transformacion_material_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(ConsumoTransformacionMaterial::class, 'lote_transformacion_material_id');
    }

    public function salidas(): HasMany
    {
        return $this->hasMany(SalidaTransformacionMaterial::class, 'lote_transformacion_material_id');
    }

    protected function casts(): array
    {
        return [
            'numero_lote' => 'integer',
            'estado' => EstadoLoteTransformacionMaterial::class,
            'cantidad_planificada_salida' => 'decimal:3',
            'cantidad_real_salida' => 'decimal:3',
            'salida_teorica' => 'decimal:3',
            'merma_estandar' => 'decimal:3',
            'merma_real' => 'decimal:3',
            'desviacion_merma' => 'decimal:3',
            'iniciado_at' => 'datetime',
            'cerrado_at' => 'datetime',
        ];
    }
}
