<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'codigo',
    'nombre',
    'fecha_inicio',
    'fecha_fin',
    'activa',
    'creado_por_user_id',
    'actualizado_por_user_id',
])]
class TemporadaMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'temporadas_materiales';

    public function clientes(): HasMany
    {
        return $this->hasMany(ClienteMaterial::class, 'temporada_material_id');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(
            ItemMaterial::class,
            ClienteMaterial::class,
            'temporada_material_id',
            'cliente_material_id',
        );
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
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'activa' => 'boolean',
        ];
    }
}
