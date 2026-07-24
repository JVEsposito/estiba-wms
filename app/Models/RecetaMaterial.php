<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'temporada_id',
    'cliente_id',
    'item_salida_id',
    'nombre',
    'activa',
    'creado_por_user_id',
    'actualizado_por_user_id',
])]
class RecetaMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'recetas_materiales';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function itemSalida(): BelongsTo
    {
        return $this->belongsTo(ItemMaterial::class, 'item_salida_id');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(VersionRecetaMaterial::class, 'receta_material_id');
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
        return ['activa' => 'boolean'];
    }
}
