<?php

namespace App\Models;

use App\Enums\EstadoVersionRecetaMaterial;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'receta_material_id',
    'numero_version',
    'estado',
    'cantidad_base_salida',
    'unidad_medida_salida',
    'snapshot',
    'creado_por_user_id',
    'activado_at',
    'retirado_at',
])]
class VersionRecetaMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'versiones_recetas_materiales';

    public function receta(): BelongsTo
    {
        return $this->belongsTo(RecetaMaterial::class, 'receta_material_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVersionRecetaMaterial::class, 'version_receta_material_id');
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenTransformacionMaterial::class, 'version_receta_material_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoVersionRecetaMaterial::class,
            'cantidad_base_salida' => 'decimal:3',
            'snapshot' => 'array',
            'activado_at' => 'datetime',
            'retirado_at' => 'datetime',
        ];
    }
}
