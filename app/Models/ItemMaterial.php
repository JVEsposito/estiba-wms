<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'nombre',
    'categoria',
    'unidad_medida',
    'codigo_externo',
    'origen_sistema',
    'sincronizado_at',
    'activo',
    'creado_por_user_id',
    'actualizado_por_user_id',
])]
class ItemMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'items_materiales';

    public function foliosMateriales(): HasMany
    {
        return $this->hasMany(FolioMaterial::class, 'item_material_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'sincronizado_at' => 'datetime',
        ];
    }
}
